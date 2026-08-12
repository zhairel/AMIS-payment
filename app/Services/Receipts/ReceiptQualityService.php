<?php

namespace App\Services\Receipts;

use Symfony\Component\Process\Process;

class ReceiptQualityService
{
    public function assess(string $path): array
    {
        $result = [
            'quality_score' => 0, 'blur' => 'unknown', 'brightness' => 'unknown',
            'resolution' => 'unknown', 'rotation' => 0.0, 'contrast' => 'unknown',
            'noise' => 'unknown', 'possible_crop' => false, 'readability' => 'unreadable',
            'width' => null, 'height' => null, 'warnings' => [],
        ];
        if (! is_file($path)) {
            return $result;
        }

        try {
            $identify = new Process(['magick', $path.'[0]', '-auto-orient', '-colorspace', 'Gray', '-format', '%w|%h|%[fx:mean]|%[fx:standard_deviation]', 'info:']);
            $identify->setTimeout(15)->run();
            if (! $identify->isSuccessful()) {
                $result['warnings'][] = 'IMAGE_METADATA_UNAVAILABLE';

                return $result;
            }
            [$width, $height, $mean, $deviation] = array_map('floatval', explode('|', trim($identify->getOutput())));
            $result['width'] = (int) $width;
            $result['height'] = (int) $height;
            $result['brightness_value'] = round($mean, 4);
            $result['contrast_value'] = round($deviation, 4);
            $result['resolution'] = min($width, $height) >= 900 ? 'good' : (min($width, $height) >= 600 ? 'moderate' : 'low');
            $result['brightness'] = $mean < .12 ? 'dark' : ($mean > .985 ? 'bright' : 'good');
            $result['contrast'] = $deviation >= .18 ? 'good' : ($deviation >= .10 ? 'moderate' : 'low');

            $edge = new Process(['magick', $path.'[0]', '-auto-orient', '-colorspace', 'Gray', '-edge', '1', '-clamp', '-format', '%[fx:mean]', 'info:']);
            $edge->setTimeout(15)->run();
            $edgeMean = $edge->isSuccessful() ? (float) trim($edge->getOutput()) : null;
            $result['edge_score'] = $edgeMean !== null ? round($edgeMean, 4) : null;
            // ImageMagick's edge density is retained as an audit signal, but
            // it is not treated as a reliable blur verdict. Paddle/OpenCV OCR
            // confidence and missing fields decide whether fallback is needed.
            $result['blur'] = 'unknown';
            $result['possible_crop'] = ($width / max(1, $height)) > 3.5 || ($height / max(1, $width)) > 5.5;

            $score = 100;
            if ($result['resolution'] === 'low') {
                $score -= 28;
            } elseif ($result['resolution'] === 'moderate') {
                $score -= 10;
            }
            if ($result['brightness'] === 'dark') {
                $score -= 22;
            } elseif ($result['brightness'] === 'bright') {
                $score -= 10;
            }
            if ($result['contrast'] === 'low') {
                $score -= 24;
            } elseif ($result['contrast'] === 'moderate') {
                $score -= 10;
            }
            if ($result['possible_crop']) {
                $score -= 15;
            }
            $result['quality_score'] = max(0, min(100, $score));
            $result['readability'] = $score < 25 ? 'unreadable' : ($score < 50 ? 'poor' : ($score < 75 ? 'acceptable' : 'good'));
            $result['noise'] = 'not_detected';
        } catch (\Throwable $e) {
            $result['warnings'][] = 'QUALITY_ASSESSMENT_FAILED';
        }

        return $result;
    }
}
