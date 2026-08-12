<?php

namespace App\Http\Controllers;

use App\Models\ReceiptSubmission;
use App\Services\FamilyPaymentAllocationService;
use App\Services\Receipts\ReceiptFieldNormalizer;
use App\Services\Receipts\ReceiptValidationService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

use App\Services\Receipts\ReceiptOrganizerService;

class FinanceReceiptController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->string('status')->value();
        $receipts = ReceiptSubmission::query()->with('user')
            ->when($status, fn ($query) => $query->where('status', $status))
            ->latest()->paginate(25)->withQueryString();

        return view('finance.receipts.index', compact('receipts', 'status'));
    }

    public function show(ReceiptSubmission $receipt)
    {
        return view('finance.receipts.show', ['receipt' => $receipt->load(['user', 'student', 'ocrResults', 'auditLogs.user'])]);
    }

    public function update(Request $request, ReceiptSubmission $receipt, ReceiptFieldNormalizer $normalizer, ReceiptValidationService $validator)
    {
        $validated = $request->validate([
            'provider' => 'nullable|string|max:120', 'reference_number' => 'nullable|string|max:150',
            'amount' => 'nullable|numeric|min:0.01|max:99999999.99',
            'currency' => ['nullable', Rule::in(ReceiptFieldNormalizer::CURRENCIES)],
            'transaction_date' => 'nullable|date', 'transaction_time' => 'nullable|date_format:H:i',
            'sender_name' => 'nullable|string|max:180', 'receiver_name' => 'nullable|string|max:180',
            'transaction_status' => 'nullable|string|max:80', 'notes' => 'required|string|max:2000',
        ]);
        $before = $receipt->only(array_keys(Arr::except($validated, ['notes'])));
        $fields = Arr::except($validated, ['notes']);
        $fields['normalized_reference'] = $normalizer->normalizeReference($fields['reference_number'] ?? null);
        $receipt->fill($fields);
        $receipt->validation_results = $validator->validate($receipt->toArray());
        $receipt->save();
        $changes = collect($fields)->filter(fn ($value, $key) => ($before[$key] ?? null) != $value)->all();
        $receipt->auditLogs()->create([
            'user_id' => $request->user()->id, 'event' => 'manual_fields_updated',
            'from_status' => $receipt->status, 'to_status' => $receipt->status,
            'changes' => ['before' => $before, 'after' => $changes], 'notes' => $validated['notes'],
            'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', 'Extracted receipt information was updated and logged.');
    }

    public function action(Request $request, ReceiptSubmission $receipt, FamilyPaymentAllocationService $allocator, ReceiptOrganizerService $organizer)
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['approve', 'reject', 'request_reupload'])],
            'notes' => 'required|string|max:2000',
        ]);
        $status = match ($validated['action']) {
            'approve' => ReceiptSubmission::APPROVED,
            'reject' => ReceiptSubmission::REJECTED,
            default => ReceiptSubmission::REUPLOAD_REQUIRED,
        };
        if ($status === ReceiptSubmission::APPROVED && ! ($receipt->validation_results['valid'] ?? false)) {
            return back()->withErrors(['action' => 'Resolve critical validation errors before approving this receipt.']);
        }

        $submission = $receipt->paymentSubmission;
        if ($status === ReceiptSubmission::APPROVED && ! $submission) {
            return back()->withErrors([
                'action' => 'This receipt has not been submitted as a family payment yet. Ask the parent to finish the receipt submission first.',
            ]);
        }
        if ($submission?->status === 'verified' && $status !== ReceiptSubmission::APPROVED) {
            return back()->withErrors([
                'action' => 'This payment has already been posted. Use a controlled Finance reversal instead of changing the receipt decision.',
            ]);
        }

        $allocationResult = DB::transaction(function () use ($request, $receipt, $submission, $status, $validated, $allocator, $organizer) {
            $result = null;

            if ($status === ReceiptSubmission::APPROVED && $submission) {
                $transactionAt = $submission->transaction_at;
                if ($receipt->transaction_date) {
                    $transactionAt = Carbon::parse(
                        $receipt->transaction_date->format('Y-m-d').' '.($receipt->transaction_time ?: '12:00'),
                        config('finance.timezone', 'Asia/Manila')
                    );
                }

                $submission->forceFill([
                    'reference_no' => $receipt->reference_number ?: $submission->reference_no,
                    'reference_normalized' => $receipt->normalized_reference ?: $submission->reference_normalized,
                    'total_amount' => $receipt->amount ?? $submission->total_amount,
                    'transaction_date' => $receipt->transaction_date ?? $submission->transaction_date,
                    'transaction_at' => $transactionAt,
                ])->save();

                $result = $allocator->allocateVerifiedSubmission($submission, $request->user());

                // Automatically organize physical receipt into permanent APPROVED folder
                $organizer->organizeApprovedReceipt($receipt);
            } elseif ($submission && in_array($status, [ReceiptSubmission::REJECTED, ReceiptSubmission::REUPLOAD_REQUIRED], true)) {
                $submission->forceFill(['status' => 'rejected', 'remarks' => $validated['notes']])->save();
                $submission->payments()->where('status', 'pending')->update([
                    'status' => 'rejected',
                    'remarks' => $validated['notes'],
                ]);

                if ($status === ReceiptSubmission::REJECTED) {
                    // Automatically move physical receipt into REJECTED archive folder
                    $organizer->organizeRejectedReceipt($receipt, $validated['notes']);
                }
            }

            $receipt->forceFill([
                'verified_by' => $request->user()->id,
                'verified_at' => now(),
                'review_reason' => $status === ReceiptSubmission::REUPLOAD_REQUIRED ? $validated['notes'] : $receipt->review_reason,
            ])->save();
            $receipt->transitionTo($status, 'finance_'.$validated['action'], $request->user()->id, [], $validated['notes']);

            return $result;
        });

        if ($status === ReceiptSubmission::APPROVED) {
            $count = $allocationResult['allocations']->count();
            $credit = (float) $allocationResult['advance_credit'];

            return back()->with('success', "Receipt approved. Payment was automatically applied oldest-first across {$count} family balance(s)."
                .($credit > 0 ? ' Advance credit: ₱'.number_format($credit, 2).'.' : ''));
        }

        return back()->with('success', 'Finance action saved. The family payment was not applied to any balance.');
    }
}
