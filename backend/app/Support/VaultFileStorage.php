<?php

namespace App\Support;

use App\Models\DocumentSubmission;
use App\Models\GranteeIdentityProfile;
use App\Models\RequirementIdentityCheck;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class VaultFileStorage
{
    public const DISK = 'local';

    public const LEGACY_DISK = 'public';

    /** @var list<string> */
    public const IDENTITY_FILENAMES = [
        'id_reference_face.jpg',
        'onboarding_selfie.jpg',
        'liveness_challenge_1.jpg',
        'liveness_challenge_2.jpg',
        'id_onboarding_frame.jpg',
        'id_onboarding_back.jpg',
        'id_scan_submission.jpg',
        'submission_selfie.jpg',
    ];

    /** Regex alternation for route `{filename}` constraints — keep in sync with IDENTITY_FILENAMES. */
    public static function identityFilenameRoutePattern(): string
    {
        return implode('|', array_map(
            static fn (string $name): string => preg_quote($name, '/'),
            self::IDENTITY_FILENAMES,
        ));
    }

    /** @var list<string> */
    public const IDENTITY_ROLES = [
        'id_reference_face',
        'onboarding_selfie',
        'liveness_challenge_1',
        'liveness_challenge_2',
        'id_onboarding_frame',
        'id_onboarding_back',
        'id_scan_submission',
        'submission_selfie',
    ];

    /**
     * @deprecated Prefer storeDocument() / storeIdentity() for structured private paths.
     */
    public static function store(UploadedFile $file, string $directory = 'submissions'): string
    {
        return $file->store($directory, self::DISK);
    }

    /**
     * Vault requirement file: documents/{grantee_id}/{batch_id}/{hash}.{ext}
     */
    public static function storeDocument(UploadedFile $file, int $granteeId, int $batchId): string
    {
        $ext = self::extensionForUpload($file);
        $hash = bin2hex(random_bytes(16));
        $directory = 'documents/'.$granteeId.'/'.$batchId;
        $path = $directory.'/'.$hash.'.'.$ext;
        Storage::disk(self::DISK)->put($path, file_get_contents($file->getRealPath()));

        return $path;
    }

    /**
     * Identity photo: identity/{grantee_id}/{hash}_{role}.{ext}
     * $roleOrFilename is a logical role (id_reference_face) or allowlisted filename.
     */
    public static function storeIdentity(UploadedFile $file, int $granteeId, string $roleOrFilename): string
    {
        $role = self::normalizeIdentityRole($roleOrFilename);
        if ($role === null) {
            throw new \InvalidArgumentException('Identity role is not allowlisted.');
        }

        $ext = self::extensionForUpload($file);
        $hash = bin2hex(random_bytes(16));
        $directory = 'identity/'.$granteeId;
        $path = $directory.'/'.$hash.'_'.$role.'.'.$ext;
        Storage::disk(self::DISK)->put($path, file_get_contents($file->getRealPath()));

        return $path;
    }

    /**
     * @deprecated Use storeIdentity(); kept for older call sites.
     */
    public static function storeNamed(UploadedFile $file, int $granteeId, string $filename): string
    {
        return self::storeIdentity($file, $granteeId, $filename);
    }

    public static function exists(string $relativePath): bool
    {
        $relativePath = self::tryNormalizeRelativePath($relativePath);
        if ($relativePath === null) {
            return false;
        }

        return Storage::disk(self::DISK)->exists($relativePath)
            || Storage::disk(self::LEGACY_DISK)->exists($relativePath);
    }

    /** Delete a vault-owned relative path after replace; ignores invalid/unknown paths. */
    public static function deleteIfOwned(string $relativePath): void
    {
        $relativePath = self::tryNormalizeRelativePath($relativePath);
        if ($relativePath === null) {
            return;
        }

        foreach ([self::DISK, self::LEGACY_DISK] as $disk) {
            if (Storage::disk($disk)->exists($relativePath)) {
                Storage::disk($disk)->delete($relativePath);
            }
        }
    }

    public static function absolutePath(string $relativePath): string
    {
        $relativePath = self::normalizeRelativePath($relativePath);

        foreach ([self::DISK, self::LEGACY_DISK] as $disk) {
            if (! Storage::disk($disk)->exists($relativePath)) {
                continue;
            }

            $candidate = Storage::disk($disk)->path($relativePath);
            $real = realpath($candidate);
            $root = realpath(Storage::disk($disk)->path(''));

            if ($real === false || $root === false) {
                abort(404);
            }

            $rootPrefix = rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
            if ($real !== $root && ! str_starts_with($real, $rootPrefix)) {
                abort(404);
            }

            return $real;
        }

        abort(404);
    }

    public const SIGNED_TTL_MINUTES = 3;

    public static function signedSubmissionUrl(
        DocumentSubmission $submission,
        string $variant = 'primary',
        ?int $userId = null,
        int $minutes = self::SIGNED_TTL_MINUTES,
    ): ?string {
        $path = $variant === 'secondary' ? $submission->secondary_stored_path : $submission->stored_path;
        if (! $path) {
            return null;
        }

        $params = [
            'submission' => $submission->id,
            'variant' => $variant === 'secondary' ? 'secondary' : 'primary',
        ];
        if ($userId !== null) {
            $params['uid'] = $userId;
        }

        $absolute = URL::temporarySignedRoute(
            'signed.document-files.show',
            now()->addMinutes($minutes),
            $params,
        );

        // Prefer relative path so Vite/SPA proxies keep preview same-origin (avoids X-Frame-Options blocks).
        $path = parse_url($absolute, PHP_URL_PATH);
        $query = parse_url($absolute, PHP_URL_QUERY);
        if (! is_string($path) || $path === '') {
            return $absolute;
        }

        return $path.($query ? '?'.$query : '');
    }

    public static function signedIdentityUrl(
        int $granteeId,
        string $filename,
        ?int $userId = null,
        int $minutes = self::SIGNED_TTL_MINUTES,
    ): ?string {
        $filename = basename($filename);
        if (! in_array($filename, self::IDENTITY_FILENAMES, true)) {
            return null;
        }

        $path = self::resolveIdentityRelativePath($granteeId, $filename);
        if ($path === null || ! self::exists($path)) {
            return null;
        }

        $params = [
            'grantee' => $granteeId,
            'filename' => $filename,
        ];
        if ($userId !== null) {
            $params['uid'] = $userId;
        }

        return URL::temporarySignedRoute(
            'signed.identity-photos.show',
            now()->addMinutes($minutes),
            $params,
        );
    }

    /**
     * Resolve the on-disk relative path for a logical identity role filename.
     * Prefers DB references; falls back to legacy fixed-name paths.
     */
    public static function resolveIdentityRelativePath(int $granteeId, string $filename): ?string
    {
        $filename = basename($filename);
        if (! in_array($filename, self::IDENTITY_FILENAMES, true)) {
            return null;
        }

        $fromDb = self::identityPathFromDatabase($granteeId, $filename);
        if (is_string($fromDb) && $fromDb !== '' && self::tryNormalizeRelativePath($fromDb) !== null) {
            return self::tryNormalizeRelativePath($fromDb);
        }

        $legacy = 'identity/'.$granteeId.'/'.$filename;
        if (self::exists($legacy)) {
            return $legacy;
        }

        return null;
    }

    public static function authSubmissionUrl(DocumentSubmission $submission, string $variant = 'primary'): ?string
    {
        $path = $variant === 'secondary' ? $submission->secondary_stored_path : $submission->stored_path;
        if (! $path) {
            return null;
        }

        $variant = $variant === 'secondary' ? 'secondary' : 'primary';

        return '/api/document-submissions/'.$submission->id.'/file/'.$variant;
    }

    public static function authStudentSubmissionUrl(DocumentSubmission $submission, string $variant = 'primary'): ?string
    {
        $path = $variant === 'secondary' ? $submission->secondary_stored_path : $submission->stored_path;
        if (! $path) {
            return null;
        }

        $variant = $variant === 'secondary' ? 'secondary' : 'primary';

        return '/api/student/requirement-vault/files/'.$submission->id.'/'.$variant;
    }

    public static function authIdentityUrl(string $filename): string
    {
        return '/api/student/identity-onboarding/photos/'.basename($filename);
    }

    public static function authStaffIdentityUrl(int $granteeId, string $filename): string
    {
        return '/api/grantees/'.$granteeId.'/identity-photos/'.basename($filename);
    }

    public static function normalizeRelativePath(string $relativePath): string
    {
        $normalized = self::tryNormalizeRelativePath($relativePath);
        if ($normalized === null) {
            abort(404);
        }

        return $normalized;
    }

    public static function tryNormalizeRelativePath(string $relativePath): ?string
    {
        $relativePath = str_replace('\\', '/', trim($relativePath));
        $relativePath = ltrim($relativePath, '/');

        if ($relativePath === '' || str_contains($relativePath, '..') || str_contains($relativePath, "\0")) {
            return null;
        }

        return $relativePath;
    }

    public static function looksLikeStructuredDocumentPath(string $relativePath): bool
    {
        return (bool) preg_match('#^documents/\d+/\d+/[a-f0-9]{32}\.[a-z0-9]{1,10}$#i', $relativePath);
    }

    public static function looksLikeStructuredIdentityPath(string $relativePath): bool
    {
        return (bool) preg_match(
            '#^identity/\d+/[a-f0-9]{32}_('.implode('|', self::IDENTITY_ROLES).')\.[a-z0-9]{1,10}$#i',
            $relativePath,
        );
    }

    public static function normalizeIdentityRole(string $roleOrFilename): ?string
    {
        $value = basename(str_replace('\\', '/', trim($roleOrFilename)));
        $value = Str::lower($value);
        if (str_ends_with($value, '.jpg') || str_ends_with($value, '.jpeg') || str_ends_with($value, '.png') || str_ends_with($value, '.webp')) {
            $value = pathinfo($value, PATHINFO_FILENAME);
        }

        return in_array($value, self::IDENTITY_ROLES, true) ? $value : null;
    }

    private static function extensionForUpload(UploadedFile $file): string
    {
        $path = $file->getRealPath();
        if (is_string($path) && is_readable($path)) {
            $mime = SecureUpload::detectMime($path);

            return match ($mime) {
                'application/pdf' => 'pdf',
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                default => 'bin',
            };
        }

        $ext = strtolower((string) $file->getClientOriginalExtension());

        return preg_match('/^[a-z0-9]{1,10}$/', $ext) === 1 ? $ext : 'bin';
    }

    private static function identityPathFromDatabase(int $granteeId, string $filename): ?string
    {
        $profile = GranteeIdentityProfile::query()->where('grantee_id', $granteeId)->first();

        return match ($filename) {
            'id_reference_face.jpg' => $profile?->id_reference_face_path,
            'onboarding_selfie.jpg' => $profile?->onboarding_selfie_path,
            'liveness_challenge_1.jpg' => $profile?->liveness_challenge_1_path,
            'liveness_challenge_2.jpg' => $profile?->liveness_challenge_2_path,
            'id_onboarding_frame.jpg' => is_string(data_get($profile?->id_ocr_payload, 'frame_path'))
                ? (string) data_get($profile->id_ocr_payload, 'frame_path')
                : null,
            'id_scan_submission.jpg' => DocumentSubmission::query()
                ->where('grantee_id', $granteeId)
                ->where('slot_key', 'school_id')
                ->orderByDesc('id')
                ->value('stored_path'),
            'submission_selfie.jpg' => RequirementIdentityCheck::query()
                ->where('grantee_id', $granteeId)
                ->orderByDesc('id')
                ->value('selfie_path'),
            default => null,
        };
    }
}
