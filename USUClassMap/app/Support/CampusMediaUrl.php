<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class CampusMediaUrl
{
    /**
     * Resolve relative static public/images paths, /storage paths, or Storage public disk paths to a web URL.
     */
    public static function resolve(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        $trimmed = trim($path);
        if ($trimmed === '') {
            return null;
        }

        if (str_starts_with($trimmed, 'http://') || str_starts_with($trimmed, 'https://')) {
            return $trimmed;
        }

        $clean = ltrim(str_replace('\\', '/', $trimmed), '/');

        if (str_starts_with($clean, 'images/') || str_starts_with($clean, 'storage/')) {
            return asset($clean);
        }

        return Storage::disk('public')->url($clean);
    }

    /**
     * Resolve relative static public/images paths or Storage public disk paths to an absolute file path on disk.
     */
    public static function publicFilePath(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        $trimmed = trim($path);
        if ($trimmed === '' || str_starts_with($trimmed, 'http://') || str_starts_with($trimmed, 'https://')) {
            return null;
        }

        $clean = ltrim(str_replace('\\', '/', $trimmed), '/');

        if (str_starts_with($clean, 'images/')) {
            return public_path($clean);
        }

        if (str_starts_with($clean, 'storage/')) {
            return public_path($clean);
        }

        return Storage::disk('public')->path($clean);
    }

    /**
     * True when the path references a versioned static asset under public/images/.
     */
    public static function isPublicStaticImage(?string $path): bool
    {
        if ($path === null) {
            return false;
        }

        $clean = ltrim(str_replace('\\', '/', trim($path)), '/');

        return str_starts_with($clean, 'images/');
    }
}
