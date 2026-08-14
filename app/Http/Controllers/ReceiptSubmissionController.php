<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessReceiptSubmission;
use App\Models\PaymentSubmission;
use App\Models\ReceiptSubmission;
use App\Services\ReceiptFingerprintService;
use App\Services\Receipts\ReceiptFieldNormalizer;
use App\Services\Receipts\ReceiptValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ReceiptSubmissionController extends Controller
{
    public function store(Request $request, ReceiptFingerprintService $fingerprints): JsonResponse
    {
        $validated = $request->validate([
            'receipt' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png', 'mimetypes:image/jpeg,image/png', 'max:10240'],
            'student_id' => ['nullable', 'integer', 'exists:students,id'],
            'retry_submission_id' => ['nullable', 'integer', 'exists:payment_submissions,id'],
        ]);
        $user = $request->user();
        if (! empty($validated['student_id']) && ! $user->students()->whereKey($validated['student_id'])->exists()) {
            abort(403);
        }
        $retrySubmission = filled($validated['retry_submission_id'] ?? null)
            ? PaymentSubmission::query()
                ->whereKey($validated['retry_submission_id'])
                ->where('user_id', $user->id)
                ->where('status', 'rejected')
                ->firstOrFail()
            : null;

        $file = $request->file('receipt');
        $id = (string) Str::uuid();
        $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin');
        $path = $file->storeAs("receipts/original/{$id}", "original.{$extension}", 'local');
        if (! $path) {
            abort(500, 'The original receipt could not be stored.');
        }

        $receipt = ReceiptSubmission::create([
            'submission_id' => $id,
            'user_id' => $user->id,
            'student_id' => $validated['student_id'] ?? null,
            'status' => ReceiptSubmission::UPLOADED,
            'original_filename' => Str::limit($file->getClientOriginalName(), 255, ''),
            'original_mime' => $file->getMimeType() ?: 'application/octet-stream',
            'original_size' => $file->getSize(),
            'original_receipt_path' => $path,
            'receipt_hash' => hash_file('sha256', Storage::disk('local')->path($path)),
            'perceptual_hash' => $fingerprints->differenceHash(Storage::disk('local')->path($path)),
        ]);
        $receipt->auditLogs()->create([
            'user_id' => $user->id, 'event' => 'receipt_uploaded', 'to_status' => ReceiptSubmission::UPLOADED,
            'changes' => ['filename' => $receipt->original_filename, 'mime' => $receipt->original_mime, 'size' => $receipt->original_size],
            'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(),
        ]);
        ProcessReceiptSubmission::dispatch($receipt->id, $retrySubmission?->id)->afterResponse();

        return response()->json([
            'submission_id' => $receipt->submission_id,
            'status' => $receipt->status,
            'status_url' => route('payment.receipts.show', $receipt->submission_id),
        ], 202);
    }

    public function show(Request $request, ReceiptSubmission $receipt): JsonResponse
    {
        $canSeeDiagnostics = in_array($request->user()->role, ['admin', 'staff'], true);
        abort_unless($receipt->user_id === $request->user()->id || $canSeeDiagnostics, 403);
        if ($canSeeDiagnostics) {
            $receipt->load('ocrResults');
        }

        $response = [
            'submission_id' => $receipt->submission_id,
            'status' => $receipt->status,
            'processing' => in_array($receipt->status, [ReceiptSubmission::UPLOADED, ReceiptSubmission::PROCESSING, ReceiptSubmission::OCR_COMPLETED], true),
            'provider' => $receipt->provider,
            'detected_ref' => $receipt->reference_number,
            'detected_amount' => $receipt->amount !== null ? (float) $receipt->amount : null,
            'currency' => $receipt->currency,
            'detected_date' => $receipt->transaction_date?->format('Y-m-d'),
            'detected_time' => $receipt->transaction_time,
            'detected_sender' => $receipt->sender_name,
            'detected_receiver' => $receipt->receiver_name,
            'transaction_status' => $receipt->transaction_status,
            'document_type' => data_get($receipt->validation_results, 'classification.type', 'uncertain'),
            'document_message' => data_get($receipt->validation_results, 'classification.message') ?: $receipt->review_reason,
            'duplicate_status' => $receipt->duplicate_status,
            'review_reason' => $receipt->review_reason,
            'quality' => [
                'readability' => data_get($receipt->quality_assessment, 'readability'),
                'message' => data_get($receipt->quality_assessment, 'user_message'),
            ],
        ];

        if ($canSeeDiagnostics) {
            $response['confidence'] = $receipt->ocr_confidence !== null ? (float) $receipt->ocr_confidence : null;
            $response['quality'] = $receipt->quality_assessment;
            $response['validation'] = $receipt->validation_results;
            $response['uncertain_fields'] = $receipt->uncertain_fields ?? [];
            $response['ocr_attempts'] = $receipt->ocrResults->map(fn ($result) => [
                'engine' => $result->engine, 'status' => $result->status,
                'confidence' => $result->confidence !== null ? (float) $result->confidence : null,
            ]);
        }

        return response()->json($response);
    }

    public function original(Request $request, ReceiptSubmission $receipt)
    {
        abort_unless($receipt->user_id === $request->user()->id || in_array($request->user()->role, ['admin', 'staff', 'finance'], true), 403);
        abort_unless(Storage::disk('local')->exists($receipt->original_receipt_path), 404);

        return Storage::disk('local')->response($receipt->original_receipt_path, $receipt->original_filename, [
            'Content-Type' => $receipt->original_mime,
            'Cache-Control' => 'private, no-store',
            'Content-Disposition' => 'inline; filename="'.str_replace('"', '', $receipt->original_filename).'"',
        ]);
    }

    public function downloadJpg(Request $request, ReceiptSubmission $receipt)
    {
        abort_unless($receipt->user_id === $request->user()->id || in_array($request->user()->role, ['admin', 'staff', 'finance'], true), 403);

        $path = $receipt->processed_receipt_path ?: $receipt->original_receipt_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        $fullPath = Storage::disk('local')->path($path);

        $student = $receipt->student;
        $dateStr = $receipt->transaction_date ? $receipt->transaction_date->format('Y-m-d') : now()->format('Y-m-d');
        $refStr = $receipt->reference_number ?: "PAY-{$receipt->id}";

        if ($student) {
            $lastName = Str::upper(preg_replace('/[^A-Z0-9]+/', '_', trim($student->last_name ?: 'STUDENT')));
            $grade = Str::upper(preg_replace('/[^A-Z0-9]+/', '_', trim($student->grade_level ?: 'GRADE')));
            $refSanitized = Str::upper(preg_replace('/[^A-Z0-9]+/', '_', trim($refStr)));
            $filename = "{$lastName}_{$grade}_{$dateStr}_{$refSanitized}.jpg";
        } else {
            $filename = "AMIS_RECEIPT_{$receipt->submission_id}.jpg";
        }

        $extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

        if (in_array($extension, ['jpg', 'jpeg'], true)) {
            return Storage::disk('local')->download($path, $filename, [
                'Content-Type' => 'image/jpeg',
                'Cache-Control' => 'private, no-store',
            ]);
        }

        try {
            $fileContent = Storage::disk('local')->get($path);
            $img = @imagecreatefromstring($fileContent);
            if ($img !== false) {
                $tempPath = storage_path("app/private/temp_" . Str::uuid() . ".jpg");
                $width = imagesx($img);
                $height = imagesy($img);
                $bg = imagecreatetruecolor($width, $height);
                $white = imagecolorallocate($bg, 255, 255, 255);
                imagefill($bg, 0, 0, $white);
                imagecopy($bg, $img, 0, 0, 0, 0, $width, $height);
                imagejpeg($bg, $tempPath, 95);
                imagedestroy($img);
                imagedestroy($bg);

                return response()->download($tempPath, $filename, [
                    'Content-Type' => 'image/jpeg',
                    'Cache-Control' => 'private, no-store',
                ])->deleteFileAfterSend(true);
            }
        } catch (\Throwable) {
        }

        return Storage::disk('local')->download($path, $filename, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function downloadPdf(Request $request, ReceiptSubmission $receipt)
    {
        abort_unless($receipt->user_id === $request->user()->id || in_array($request->user()->role, ['admin', 'staff', 'finance'], true), 403);
        $path = $receipt->processed_receipt_path ?: $receipt->original_receipt_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        $student = $receipt->student;
        $dateStr = $receipt->transaction_date ? $receipt->transaction_date->format('Y-m-d') : now()->format('Y-m-d');
        $refStr = $receipt->reference_number ?: "PAY-{$receipt->id}";

        if ($student) {
            $lastName = Str::upper(preg_replace('/[^A-Z0-9]+/', '_', trim($student->last_name ?: 'STUDENT')));
            $grade = Str::upper(preg_replace('/[^A-Z0-9]+/', '_', trim($student->grade_level ?: 'GRADE')));
            $refSanitized = Str::upper(preg_replace('/[^A-Z0-9]+/', '_', trim($refStr)));
            $filename = "{$lastName}_{$grade}_{$dateStr}_{$refSanitized}.pdf";
        } else {
            $filename = "AMIS_RECEIPT_{$receipt->submission_id}.pdf";
        }

        return Storage::disk('local')->download($path, $filename, [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function storeClientFallback(
        Request $request,
        ReceiptSubmission $receipt,
        ReceiptFieldNormalizer $normalizer,
        ReceiptValidationService $validator
    ): JsonResponse {
        abort_unless($receipt->user_id === $request->user()->id, 403);
        $validated = $request->validate([
            'raw_text' => 'required|string|max:50000', 'confidence' => 'nullable|numeric|min:0|max:1',
            'reference_number' => 'nullable|string|max:150', 'amount' => 'nullable|numeric|min:0|max:99999999',
            'currency' => ['nullable', Rule::in(ReceiptFieldNormalizer::CURRENCIES)],
            'transaction_date' => 'nullable|date_format:Y-m-d', 'transaction_time' => 'nullable|date_format:H:i',
            'provider' => 'nullable|string|max:120', 'sender_name' => 'nullable|string|max:180',
            'receiver_name' => 'nullable|string|max:180', 'transaction_status' => 'nullable|string|max:80',
        ]);
        $candidate = [
            'provider' => $validated['provider'] ?? null,
            'reference_number' => $validated['reference_number'] ?? null,
            'normalized_reference' => $normalizer->normalizeReference($validated['reference_number'] ?? null),
            'amount' => isset($validated['amount']) ? round((float) $validated['amount'], 2) : null,
            'currency' => $validated['currency'] ?? null,
            'transaction_date' => $validated['transaction_date'] ?? null,
            'transaction_time' => isset($validated['transaction_time']) ? $validated['transaction_time'].':00' : null,
            'sender_name' => $validated['sender_name'] ?? null,
            'receiver_name' => $validated['receiver_name'] ?? null,
            'transaction_status' => $validated['transaction_status'] ?? null,
        ];
        $current = $receipt->structured_ocr ?? [];
        $uncertain = $receipt->uncertain_fields ?? [];
        foreach ($candidate as $field => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $existing = $current[$field] ?? null;
            if ($existing !== null && (string) $existing !== (string) $value && in_array($field, ['normalized_reference', 'amount', 'transaction_date'], true)) {
                $uncertain[] = $field === 'normalized_reference' ? 'reference_number' : $field;
                $current[$field] = null;
            } elseif ($existing === null || $existing === '') {
                $current[$field] = $value;
            }
        }
        $uncertain = array_values(array_unique($uncertain));
        $validation = $validator->validate($current, $uncertain);
        $receipt->ocrResults()->create([
            'engine' => 'Tesseract', 'attempt_number' => ((int) $receipt->ocrResults()->max('attempt_number')) + 1,
            'source_variant' => 'browser_processed', 'status' => 'processed',
            'raw_text' => $validated['raw_text'], 'structured_json' => $candidate,
            'confidence' => $validated['confidence'] ?? null, 'warnings' => ['EXECUTED_IN_BROWSER'],
        ]);
        $receipt->forceFill(array_merge([
            'structured_ocr' => $current, 'uncertain_fields' => $uncertain,
            'validation_results' => $validation,
        ], collect($current)->only([
            'provider', 'reference_number', 'normalized_reference', 'amount', 'currency',
            'transaction_date', 'transaction_time', 'sender_name', 'receiver_name', 'transaction_status',
        ])->all()))->save();
        $criticalReadable = filled($current['normalized_reference'] ?? null) && is_numeric($current['amount'] ?? null);
        if ($criticalReadable
            && data_get($receipt->quality_assessment, 'readability') !== 'unreadable'
            && ! data_get($receipt->quality_assessment, 'reupload_required', false)) {
            $receipt->transitionTo(ReceiptSubmission::NEEDS_REVIEW, 'browser_tesseract_recorded', $request->user()->id, [
                'confidence' => $validated['confidence'] ?? null, 'uncertain_fields' => $uncertain,
            ], 'Browser Tesseract recovered fields that require Finance confirmation.');
        }

        return $this->show($request, $receipt->fresh());
    }
}
