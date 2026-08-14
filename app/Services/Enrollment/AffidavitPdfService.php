<?php

namespace App\Services\Enrollment;

use setasign\Fpdi\Fpdi;

class AffidavitPdfService
{
    private const FIELDS = [
        'guardian_name' => [
            'top' => 10.95,
            'left' => 14.5,
            'width' => 50.7,
            'font_size' => 15,
            'bold' => true,
            'align' => 'center',
            'placeholder' => 'Name of Parent/Guardian',
            'required' => true,
        ],
        'guardian_address' => [
            'top' => 14.05,
            'left' => 14.6,
            'width' => 73.7,
            'font_size' => 15,
            'bold' => true,
            'align' => 'left',
            'placeholder' => 'Address',
            'required' => true,
        ],
        'student_name' => [
            'top' => 17.30,
            'left' => 34.5,
            'width' => 49.5,
            'font_size' => 15,
            'bold' => true,
            'align' => 'center',
            'placeholder' => 'Name of Learner',
            'required' => true,
        ],
        'missing_credential' => [
            'top' => 30.25,
            'left' => 58.3,
            'width' => 28.5,
            'font_size' => 14,
            'bold' => true,
            'align' => 'left',
            'placeholder' => 'Name of Previous School',
            'required' => true,
        ],
        'grade_level' => [
            'top' => 32.55,
            'left' => 43.4,
            'width' => 13.0,
            'font_size' => 15,
            'bold' => true,
            'align' => 'left',
            'placeholder' => 'Grade',
            'required' => true,
        ],
        'reason' => [
            'top' => 34.95,
            'left' => 25.8,
            'width' => 38.9,
            'font_size' => 13,
            'bold' => true,
            'align' => 'left',
            'placeholder' => 'Reason',
            'required' => true,
            'max_pdf_chars' => 55,
        ],
        'commitment_date' => [
            'top' => 49.45,
            'left' => 63.5,
            'width' => 12.0,
            'font_size' => 13,
            'bold' => true,
            'align' => 'left',
            'placeholder' => 'Date',
            'format' => 'date',
            'required' => true,
        ],
        'attested_day' => [
            'top' => 79.85,
            'left' => 22.2,
            'width' => 5.5,
            'font_size' => 12,
            'bold' => true,
            'align' => 'center',
            'placeholder' => 'day',
            'default' => 'current_day',
            'required' => true,
        ],
        'attested_month' => [
            'top' => 79.85,
            'left' => 33.1,
            'width' => 8.5,
            'font_size' => 12,
            'bold' => true,
            'align' => 'center',
            'placeholder' => 'month',
            'default' => 'current_month',
            'required' => true,
        ],
        'attested_place' => [
            'top' => 79.85,
            'left' => 43.6,
            'width' => 21.5,
            'font_size' => 12,
            'bold' => true,
            'align' => 'left',
            'placeholder' => 'Place',
            'required' => true,
        ],
        'govt_id_type' => [
            'top' => 89.05,
            'left' => 23.5,
            'width' => 6.0,
            'font_size' => 8,
            'bold' => true,
            'align' => 'left',
            'placeholder' => 'ID Type',
            'no_caps' => true,
            'fallback' => 'govt_id_presented',
        ],
        'govt_id_number' => [
            'top' => 90.20,
            'left' => 18.8,
            'width' => 13.0,
            'font_size' => 8,
            'bold' => true,
            'align' => 'left',
            'placeholder' => 'ID Number',
            'no_caps' => true,
            'fallback' => 'id_number',
        ],
        'govt_id_date' => [
            'top' => 91.35,
            'left' => 19.5,
            'width' => 12.0,
            'font_size' => 8,
            'bold' => true,
            'align' => 'left',
            'placeholder' => 'Date Issued',
            'no_caps' => true,
            'fallback' => 'date_issued',
        ],
    ];

    private const SIGNATURE_FIELD = [
        'top' => 82.1,
        'left' => 32.4,
        'width' => 35.0,
        'height' => 5.0,
    ];

    private const SIGNATURE_NAME_FIELD = [
        'top' => 85.25,
        'left' => 32.6,
        'width' => 35.0,
        'font_size' => 12,
    ];

    public function fields(): array
    {
        return self::FIELDS;
    }

    public function signatureField(): array
    {
        return self::SIGNATURE_FIELD;
    }

    public function signatureNameField(): array
    {
        return self::SIGNATURE_NAME_FIELD;
    }

    public function build(array $data): string
    {
        $templatePath = public_path('docs/Affidavit_enrollee.pdf');

        $pdf = new Fpdi;
        $pdf->setSourceFile($templatePath);
        $tplId = $pdf->importPage(1);
        $size = $pdf->getTemplateSize($tplId);
        $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
        $pdf->useTemplate($tplId);
        $pdf->SetTextColor(0, 0, 0);

        $pageWidth = (float) $size['width'];
        $pageHeight = (float) $size['height'];

        foreach (self::FIELDS as $name => $field) {
            $this->writeField($pdf, $pageWidth, $pageHeight, $field, $this->fieldValue($name, $field, $data));
        }

        $this->writeSignature($pdf, $pageWidth, $pageHeight, $data);
        $this->writeField($pdf, $pageWidth, $pageHeight, self::SIGNATURE_NAME_FIELD + [
            'bold' => true,
            'align' => 'center',
        ], $data['guardian_name']);

        return $pdf->Output('S');
    }

    private function fieldValue(string $name, array $field, array $data): string
    {
        $value = $data[$name] ?? '';

        if ($value === '' && isset($field['fallback'])) {
            $value = $data[$field['fallback']] ?? '';
        }

        if (($field['default'] ?? null) === 'current_day' && $value === '') {
            return now()->format('j');
        }

        if (($field['default'] ?? null) === 'current_month' && $value === '') {
            return strtoupper(now()->format('F'));
        }

        if (($field['format'] ?? null) === 'date') {
            return $this->formatOptionalDate($value);
        }

        if (isset($field['max_pdf_chars'])) {
            return mb_substr((string) $value, 0, $field['max_pdf_chars']);
        }

        return (string) $value;
    }

    private function writeField(Fpdi $pdf, float $pageWidth, float $pageHeight, array $field, string $value): void
    {
        $fontPointSize = max(6, ((float) $field['font_size']) * 0.75);
        $text = ! ($field['no_caps'] ?? false)
            ? strtoupper(trim($value))
            : trim($value);

        $pdf->SetFont('Helvetica', ! empty($field['bold']) ? 'B' : '', $fontPointSize);
        $pdf->SetXY(($field['left'] / 100) * $pageWidth, ($field['top'] / 100) * $pageHeight);
        $pdf->Cell(
            ($field['width'] / 100) * $pageWidth,
            max(3.5, $fontPointSize * 0.36),
            $text,
            0,
            0,
            strtoupper($field['align'][0] ?? 'L')
        );
    }

    private function writeSignature(Fpdi $pdf, float $pageWidth, float $pageHeight, array $data): void
    {
        if (empty($data['signature_data']) || ! str_starts_with($data['signature_data'], 'data:image/png;base64,')) {
            return;
        }

        $sigBase64 = str_replace('data:image/png;base64,', '', $data['signature_data']);
        $sigBinary = base64_decode($sigBase64);
        $tmpFile = tempnam(sys_get_temp_dir(), 'sig_').'.png';

        file_put_contents($tmpFile, $sigBinary);
        $pdf->Image(
            $tmpFile,
            (self::SIGNATURE_FIELD['left'] / 100) * $pageWidth,
            (self::SIGNATURE_FIELD['top'] / 100) * $pageHeight,
            (self::SIGNATURE_FIELD['width'] / 100) * $pageWidth,
            (self::SIGNATURE_FIELD['height'] / 100) * $pageHeight
        );
        @unlink($tmpFile);
    }

    private function formatOptionalDate(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        $timestamp = strtotime($value);

        return $timestamp
            ? strtoupper(date('F', $timestamp)).date(' j, Y', $timestamp)
            : $value;
    }
}
