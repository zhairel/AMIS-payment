<?php

namespace App\Services\Receipts;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class ReceiptPreprocessor
{
    public function process(string $sourcePath, string $submissionId): ?string
    {
        $relative = "receipts/processed/{$submissionId}.png";
        $target = Storage::disk('local')->path($relative);
        if (! is_dir(dirname($target))) {
            mkdir(dirname($target), 0775, true);
        }

        $input = $sourcePath;
        $pdfRaster = null;
        if (strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION)) === 'pdf') {
            $pdfBase = dirname($target)."/{$submissionId}-pdf-source";
            $rasterize = new Process(['pdftoppm', '-f', '1', '-singlefile', '-png', '-r', '220', $sourcePath, $pdfBase]);
            $rasterize->setTimeout(30)->run();
            $pdfRaster = $pdfBase.'.png';
            if (! $rasterize->isSuccessful() || ! is_file($pdfRaster)) {
                return null;
            }
            $input = $pdfRaster;
        }

        $python = (string) config('services.paddle_ocr.python', 'python3');
        $probe = new Process([$python, '-c', 'import cv2']);
        $probe->setTimeout(4)->run();
        $script = base_path('scripts/receipt_preprocess.py');
        if ($probe->isSuccessful() && is_file($script)) {
            $openCv = new Process([$python, $script, $input, $target]);
            $openCv->setTimeout(30)->run();
            if ($openCv->isSuccessful() && is_file($target)) {
                if ($pdfRaster && is_file($pdfRaster)) {
                    unlink($pdfRaster);
                }

                return $relative;
            }
        }

        $process = new Process([
            'magick', $input.'[0]', '-auto-orient', '-background', 'white', '-alpha', 'remove',
            '-strip', '-colorspace', 'Gray', '-resize', '2400x2400>', '-contrast-stretch', '0.5%x0.5%',
            '-filter', 'Lanczos', '-unsharp', '0x0.6+0.6+0.02', $target,
        ]);
        $process->setTimeout(30)->run();
        if ($pdfRaster && is_file($pdfRaster)) {
            unlink($pdfRaster);
        }

        return $process->isSuccessful() && is_file($target) ? $relative : null;
    }
}
