<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class IdentityPhotoStorage
{
    public static function storeNamed(UploadedFile $file, int $granteeId, string $filename): string
    {
        $directory = "identity/{$granteeId}";
        $path = "{$directory}/{$filename}";
        Storage::disk('public')->put($path, file_get_contents($file->getRealPath()));

        return $path;
    }

    public static function url(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }
}
