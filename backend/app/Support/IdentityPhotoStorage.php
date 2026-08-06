<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class IdentityPhotoStorage
{
    /**
     * Store an allowlisted identity photo on the private vault disk.
     * On-disk name is hashed; logical role is encoded as a suffix and kept in DB.
     */
    public static function storeNamed(UploadedFile $file, int $granteeId, string $filename): string
    {
        return VaultFileStorage::storeIdentity($file, $granteeId, $filename);
    }

    /**
     * Prefer time-limited signed URLs keyed by logical role filenames.
     * Falls back to legacy public URL only if signed generation is unavailable.
     */
    public static function url(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $relative = VaultFileStorage::tryNormalizeRelativePath($path);
        if ($relative === null) {
            return null;
        }

        if (preg_match('#^identity/(\d+)/([^/]+)$#', $relative, $matches) === 1) {
            $granteeId = (int) $matches[1];
            $basename = $matches[2];
            $roleFilename = self::roleFilenameForBasename($basename);
            if ($roleFilename !== null) {
                $signed = VaultFileStorage::signedIdentityUrl($granteeId, $roleFilename);
                if ($signed) {
                    return $signed;
                }
            }
        }

        // Legacy public objects only (avoid advertising private-disk paths).
        if (Storage::disk('public')->exists($relative)) {
            return Storage::disk('public')->url($relative);
        }

        return null;
    }

    private static function roleFilenameForBasename(string $basename): ?string
    {
        if (in_array($basename, VaultFileStorage::IDENTITY_FILENAMES, true)) {
            return $basename;
        }

        if (preg_match(
            '#^[a-f0-9]{32}_('.implode('|', VaultFileStorage::IDENTITY_ROLES).')\.[a-z0-9]{1,10}$#i',
            $basename,
            $matches,
        ) === 1) {
            return $matches[1].'.jpg';
        }

        return null;
    }
}
