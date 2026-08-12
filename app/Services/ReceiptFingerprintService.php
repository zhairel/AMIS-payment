<?php

namespace App\Services;

use Symfony\Component\Process\Process;

class ReceiptFingerprintService
{
    public function differenceHash(string $path): ?string
    {
        if (! is_file($path)) {
            return null;
        }

        try {
            $process = new Process(['magick', $path, '-auto-orient', '-resize', '9x8!', '-colorspace', 'Gray', '-depth', '8', 'gray:-']);
            $process->setTimeout(8)->run();
            $pixels = $process->getOutput();
            if (! $process->isSuccessful() || strlen($pixels) < 72) {
                return null;
            }

            $bits = '';
            for ($row = 0; $row < 8; $row++) {
                for ($column = 0; $column < 8; $column++) {
                    $left = ord($pixels[($row * 9) + $column]);
                    $right = ord($pixels[($row * 9) + $column + 1]);
                    $bits .= $left > $right ? '1' : '0';
                }
            }

            return str_pad(base_convert($bits, 2, 16), 16, '0', STR_PAD_LEFT);
        } catch (\Throwable) {
            return null;
        }
    }

    public function hammingDistance(string $first, string $second): int
    {
        if (! preg_match('/^[a-f0-9]{16}$/i', $first) || ! preg_match('/^[a-f0-9]{16}$/i', $second)) {
            return 64;
        }

        $distance = 0;
        for ($index = 0; $index < 16; $index++) {
            $xor = hexdec($first[$index]) ^ hexdec($second[$index]);
            $distance += substr_count(decbin($xor), '1');
        }

        return $distance;
    }

    public function hasSuspiciousEditorMetadata(string $path): bool
    {
        try {
            $process = new Process(['magick', 'identify', '-format', '%[EXIF:Software] %[comment]', $path]);
            $process->setTimeout(5)->run();
            if (! $process->isSuccessful()) {
                return false;
            }

            return (bool) preg_match('/photoshop|gimp|canva|snapseed|pixlr|photopea/i', $process->getOutput());
        } catch (\Throwable) {
            return false;
        }
    }
}
