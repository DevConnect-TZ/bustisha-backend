<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

class UploadSanitizer
{
    protected const IMAGE_SIGNATURES = [
        'jpg' => ["\xFF\xD8\xFF"],
        'jpeg' => ["\xFF\xD8\xFF"],
        'png' => ["\x89\x50\x4E\x47\x0D\x0A\x1A\x0A"],
        'gif' => ["\x47\x49\x46\x38\x37\x61", "\x47\x49\x46\x38\x39\x61"],
        'webp' => ["\x52\x49\x46\x46"],
    ];

    protected const SUSPICIOUS_PATTERNS = [
        '/<\?[a-z]/i',
        '/<\?=/',
        '/<script\b/i',
        '/eval\s*\(/i',
        '/base64_decode\s*\(/i',
        '/system\s*\(/i',
        '/exec\s*\(/i',
        '/shell_exec\s*\(/i',
        '/passthru\s*\(/i',
        '/popen\s*\(/i',
        '/proc_open\s*\(/i',
        '/assert\s*\(/i',
        '/create_function/i',
        '/include\s*\(/i',
        '/require\s*\(/i',
        '/file_put_contents/i',
        '/fwrite\s*\(/i',
        '/chmod\s*\(/i',
        '/preg_replace\s*\(\s*[\'\"].*e[\'\"]\s*,/i',
        '/\bEICAR\b/i',
        '/\x4D\x5A/',
        '/\x7F\x45\x4C\x46/',
    ];

    public function sanitize(UploadedFile $file): array
    {
        $path = $file->getRealPath();

        if (!$path || !file_exists($path)) {
            return ['safe' => false, 'message' => 'File not found.'];
        }

        $content = file_get_contents($path);
        if ($content === false) {
            return ['safe' => false, 'message' => 'Could not read file.'];
        }

        $extension = strtolower($file->getClientOriginalExtension());

        if (!$this->checkMagicBytes($content, $extension)) {
            return ['safe' => false, 'message' => 'File content does not match its extension.'];
        }

        if ($this->scanForMalware($content)) {
            return ['safe' => false, 'message' => 'File contains suspicious content and was rejected.'];
        }

        if (extension_loaded('gd')) {
            $sanitized = $this->reEncodeImage($file, $extension);
            if ($sanitized === null) {
                return ['safe' => false, 'message' => 'File could not be sanitized.'];
            }
            return ['safe' => true, 'file' => $sanitized];
        }

        return ['safe' => true, 'file' => $file];
    }

    protected function checkMagicBytes(string $content, string $extension): bool
    {
        $signatures = self::IMAGE_SIGNATURES[$extension] ?? null;

        if (!$signatures) {
            return false;
        }

        foreach ($signatures as $sig) {
            if (str_starts_with($content, $sig)) {
                return true;
            }
        }

        return false;
    }

    protected function scanForMalware(string $content): bool
    {
        foreach (self::SUSPICIOUS_PATTERNS as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }

    protected function reEncodeImage(UploadedFile $file, string $extension): ?UploadedFile
    {
        $createFn = match ($extension) {
            'jpg', 'jpeg' => 'imagecreatefromjpeg',
            'png' => 'imagecreatefrompng',
            'gif' => 'imagecreatefromgif',
            'webp' => 'imagecreatefromwebp',
            default => null,
        };

        if (!$createFn || !function_exists($createFn)) {
            return null;
        }

        $image = @$createFn($file->getRealPath());
        if ($image === false) {
            return null;
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'sanitized_');

        $saved = match ($extension) {
            'jpg', 'jpeg' => imagejpeg($image, $tempPath, 90),
            'png' => imagepng($image, $tempPath, 9),
            'gif' => imagegif($image, $tempPath),
            'webp' => imagewebp($image, $tempPath, 90),
            default => false,
        };

        imagedestroy($image);

        if (!$saved) {
            @unlink($tempPath);
            return null;
        }

        return new UploadedFile(
            $tempPath,
            $file->getClientOriginalName(),
            $file->getClientMimeType(),
            null,
            true
        );
    }
}
