<?php

namespace App\Services\Receipts;

use App\Models\ReceiptSubmission;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ReceiptOrganizerService
{
    /**
     * Organize an approved receipt into permanent structured storage:
     * PAYMENT_RECEIPTS/APPROVED/{SchoolYear}/{ProviderGroup}/{Year-Month}/{Filename}
     */
    public function organizeApprovedReceipt(ReceiptSubmission $receipt): string
    {
        $currentPath = $receipt->processed_receipt_path ?: $receipt->original_receipt_path;

        if (!$currentPath || !Storage::disk('local')->exists($currentPath)) {
            Log::warning("Receipt file not found for submission {$receipt->submission_id} at path {$currentPath}");
            return $currentPath ?? '';
        }

        // 1. Determine dynamic folder path
        $transactionDate = $receipt->transaction_date ?: Carbon::now();
        $schoolYear = $this->calculateSchoolYear($transactionDate);
        $providerGroup = $this->determineProviderGroup($receipt->provider);
        $yearMonth = $transactionDate->format('Y-m');

        $targetFolder = "PAYMENT_RECEIPTS/APPROVED/{$schoolYear}/{$providerGroup}/{$yearMonth}";
        Storage::disk('local')->makeDirectory($targetFolder);

        // 2. Determine standardized filename
        $filename = $this->generateStandardizedFilename($receipt, $transactionDate);
        $targetPath = "{$targetFolder}/{$filename}";

        // 3. Collision protection (append payment ID if file already exists)
        if (Storage::disk('local')->exists($targetPath) && $targetPath !== $currentPath) {
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $basename = pathinfo($filename, PATHINFO_FILENAME);
            $targetPath = "{$targetFolder}/{$basename}_PAY-{$receipt->id}.{$extension}";
        }

        // 4. Move physical file (only 1 single copy stored!)
        if ($currentPath !== $targetPath) {
            Storage::disk('local')->move($currentPath, $targetPath);
        }

        // 5. Update database record
        $receipt->forceFill([
            'processed_receipt_path' => $targetPath,
        ])->save();

        Log::info("Receipt {$receipt->submission_id} organized to: {$targetPath}");

        return $targetPath;
    }

    /**
     * Organize a rejected receipt into rejection storage:
     * PAYMENT_RECEIPTS/REJECTED/{Year-Month}/{Filename}
     */
    public function organizeRejectedReceipt(ReceiptSubmission $receipt, ?string $reason = null): string
    {
        $currentPath = $receipt->processed_receipt_path ?: $receipt->original_receipt_path;

        if (!$currentPath || !Storage::disk('local')->exists($currentPath)) {
            return $currentPath ?? '';
        }

        $date = $receipt->transaction_date ?: Carbon::now();
        $yearMonth = $date->format('Y-m');

        $targetFolder = "PAYMENT_RECEIPTS/REJECTED/{$yearMonth}";
        Storage::disk('local')->makeDirectory($targetFolder);

        $filename = $this->generateStandardizedFilename($receipt, $date, true);
        $targetPath = "{$targetFolder}/{$filename}";

        if (Storage::disk('local')->exists($targetPath) && $targetPath !== $currentPath) {
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $basename = pathinfo($filename, PATHINFO_FILENAME);
            $targetPath = "{$targetFolder}/{$basename}_PAY-{$receipt->id}.{$extension}";
        }

        if ($currentPath !== $targetPath) {
            Storage::disk('local')->move($currentPath, $targetPath);
        }

        $receipt->forceFill([
            'processed_receipt_path' => $targetPath,
            'review_reason' => $reason ?: $receipt->review_reason,
        ])->save();

        Log::info("Rejected receipt {$receipt->submission_id} archived to: {$targetPath}");

        return $targetPath;
    }

    /**
     * Calculate School Year dynamically (June 1 - May 31 cycle)
     */
    private function calculateSchoolYear(Carbon $date): string
    {
        $year = $date->year;
        // In Philippine schools, June starts the new academic year
        if ($date->month < 6) {
            return ($year - 1) . '-' . $year;
        }

        return $year . '-' . ($year + 1);
    }

    /**
     * Map provider name to standardized Provider Group folder name
     */
    private function determineProviderGroup(?string $provider): string
    {
        if (!$provider) {
            return 'OTHER';
        }

        $normalized = Str::lower(trim($provider));

        if (str_contains($normalized, 'gcash')) {
            return 'GCASH';
        }

        if (str_contains($normalized, 'maya') || str_contains($normalized, 'paymaya')) {
            return 'MAYA';
        }

        if (str_contains($normalized, 'bdo')) {
            return 'BDO';
        }

        if (
            str_contains($normalized, 'western') ||
            str_contains($normalized, 'moneygram') ||
            str_contains($normalized, 'cebuana') ||
            str_contains($normalized, 'palawan') ||
            str_contains($normalized, 'remittance') ||
            str_contains($normalized, 'transferwise') ||
            str_contains($normalized, 'remit')
        ) {
            return 'REMITTANCE';
        }

        if (str_contains($normalized, 'cash') || str_contains($normalized, 'onsite')) {
            return 'CASH';
        }

        return 'OTHER';
    }

    /**
     * Generate standardized filename:
     * - Single student: LASTNAME_GRADE_DATE_REFERENCE.ext
     * - Multi children / Family: FAMILYID_MULTI_DATE_REFERENCE.ext
     */
    private function generateStandardizedFilename(ReceiptSubmission $receipt, Carbon $date, bool $isRejected = false): string
    {
        $extension = pathinfo($receipt->original_filename, PATHINFO_EXTENSION);
        if (!$extension) {
            $extension = 'jpg';
        }
        $extension = strtolower($extension);

        $formattedDate = $date->format('Y-m-d');
        $reference = $this->sanitizeSegment($receipt->reference_number ?: "PAY-{$receipt->id}");

        $submission = $receipt->paymentSubmission;
        $student = $receipt->student;
        $user = $receipt->user;

        // Check if multi-children / family payment
        $isFamilyMulti = false;
        if ($submission && $submission->payments()->count() > 1) {
            $isFamilyMulti = true;
        }

        if ($isFamilyMulti) {
            $familyId = $submission->family_id ?? ($user ? "FAM-{$user->id}" : "FAMILY");
            $familySegment = $this->sanitizeSegment($familyId);
            $basename = "{$familySegment}_MULTI_{$formattedDate}_{$reference}";
        } elseif ($student) {
            $lastName = $this->sanitizeSegment($student->last_name ?: 'STUDENT');
            $grade = $this->sanitizeSegment($student->grade_level ?: 'GRADE');
            $basename = "{$lastName}_{$grade}_{$formattedDate}_{$reference}";
        } else {
            $lastName = $this->sanitizeSegment($user ? $user->name : 'PAYMENT');
            $basename = "{$lastName}_{$formattedDate}_{$reference}";
        }

        if ($isRejected) {
            $basename = "REJ_{$basename}";
        }

        return "{$basename}.{$extension}";
    }

    /**
     * Sanitize string segments for filenames (uppercase, alphanum & underscores only)
     */
    private function sanitizeSegment(string $value): string
    {
        $sanitized = Str::upper(trim($value));
        $sanitized = preg_replace('/[^A-Z0-9]+/', '_', $sanitized);
        return trim($sanitized, '_') ?: 'UNKNOWN';
    }
}
