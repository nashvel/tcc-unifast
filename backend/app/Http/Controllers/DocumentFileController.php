<?php

namespace App\Http\Controllers;

use App\Models\DocumentSubmission;
use App\Models\Grantee;
use App\Support\SecureUpload;
use App\Support\VaultFileStorage;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentFileController extends Controller
{
    public function showSigned(Request $request, DocumentSubmission $submission, string $variant = 'primary'): BinaryFileResponse
    {
        // Signature validated by `signed` middleware. Optional uid claim narrows leaked links.
        if ($request->query->has('uid')) {
            $uid = (int) $request->query('uid');
            abort_unless($uid > 0, 403);
        }

        return $this->streamSubmission($submission, $variant);
    }

    public function showAuthenticated(Request $request, DocumentSubmission $submission, ?string $variant = 'primary'): BinaryFileResponse
    {
        $submission->loadMissing('grantee');
        $user = $request->user();
        if ($user->role === 'student') {
            abort_unless(
                $submission->student_id === $user->student_id
                    || (int) $submission->grantee?->user_id === (int) $user->id,
                403,
            );
        } elseif (! in_array($user->role, ['developer', 'admin', 'head', 'staff'], true)) {
            abort(403);
        }

        return $this->streamSubmission($submission, $variant ?: 'primary');
    }

    public function showIdentityPhoto(Request $request, Grantee $grantee, string $filename): BinaryFileResponse
    {
        $filename = basename($filename);
        abort_unless(in_array($filename, VaultFileStorage::IDENTITY_FILENAMES, true), 404);

        if ($request->query->has('uid')) {
            abort_unless((int) $request->query('uid') > 0, 403);
        }

        $path = VaultFileStorage::resolveIdentityRelativePath($grantee->id, $filename);
        abort_unless(is_string($path) && VaultFileStorage::exists($path), 404);

        $absolute = VaultFileStorage::absolutePath($path);
        $mime = SecureUpload::detectMime($absolute);

        return $this->streamFile($absolute, $mime, $filename);
    }

    public function showOwnIdentityPhoto(Request $request, string $filename): BinaryFileResponse
    {
        $filename = basename($filename);
        abort_unless(in_array($filename, VaultFileStorage::IDENTITY_FILENAMES, true), 404);

        $user = $request->user();
        $grantee = Grantee::query()->where('user_id', $user->id)->first();
        if (! $grantee && $user->student_id) {
            $grantee = Grantee::query()
                ->where('student_id', $user->student_id)
                ->whereNull('user_id')
                ->first();
        }
        abort_unless($grantee, 404);

        $path = VaultFileStorage::resolveIdentityRelativePath($grantee->id, $filename);
        abort_unless(is_string($path) && VaultFileStorage::exists($path), 404);

        $absolute = VaultFileStorage::absolutePath($path);
        $mime = SecureUpload::detectMime($absolute);

        return $this->streamFile($absolute, $mime, $filename);
    }

    public function showStaffIdentityPhoto(Request $request, Grantee $grantee, string $filename): BinaryFileResponse
    {
        $filename = basename($filename);
        abort_unless(in_array($filename, VaultFileStorage::IDENTITY_FILENAMES, true), 404);
        abort_unless(in_array($request->user()->role, ['developer', 'admin', 'head', 'staff'], true), 403);

        $path = VaultFileStorage::resolveIdentityRelativePath($grantee->id, $filename);
        abort_unless(is_string($path) && VaultFileStorage::exists($path), 404);

        $absolute = VaultFileStorage::absolutePath($path);
        $mime = SecureUpload::detectMime($absolute);

        return $this->streamFile($absolute, $mime, $filename);
    }

    private function streamSubmission(DocumentSubmission $submission, string $variant): BinaryFileResponse
    {
        $variant = $variant === 'secondary' ? 'secondary' : 'primary';
        $path = $variant === 'secondary' ? $submission->secondary_stored_path : $submission->stored_path;
        $mime = $variant === 'secondary' ? $submission->secondary_mime_type : $submission->mime_type;
        $name = $variant === 'secondary' ? $submission->secondary_original_name : $submission->original_name;

        abort_unless(is_string($path) && $path !== '', 404);
        abort_unless(VaultFileStorage::exists($path), 404);

        $absolute = VaultFileStorage::absolutePath($path);
        $detected = SecureUpload::detectMime($absolute);
        $safeMime = is_string($mime) && $mime !== '' ? $mime : $detected;

        return $this->streamFile(
            $absolute,
            $safeMime,
            SecureUpload::sanitizeOriginalName($name, 'document'),
        );
    }

    private function streamFile(string $absolute, string $mime, string $downloadName): BinaryFileResponse
    {
        return response()->file($absolute, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.$downloadName.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
            'X-Frame-Options' => 'SAMEORIGIN',
        ]);
    }
}
