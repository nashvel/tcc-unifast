<?php

namespace App\Services\Continuity;

use App\Models\ContinuityFile;
use App\Models\GoogleWorkspaceConnection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class DriveFileStorage
{
    public function __construct(private GoogleWorkspaceClient $google) {}

    public function enabled(): bool
    {
        return config('continuity.enabled') && GoogleWorkspaceConnection::where('storage_enabled', true)->exists();
    }

    public function upload(string $relative, string $absolute): ContinuityFile
    {
        abort_unless($relative !== '' && strlen($relative) <= 255 && ! str_contains($relative, '..') && ! str_starts_with($relative, '/'), 422);
        abort_unless(is_file($absolute) && filesize($absolute) <= 25 * 1024 * 1024, 422, 'File exceeds the continuity upload limit.');

        return Cache::lock('continuity:file:'.hash('sha256', $relative), 180)->block(5, function () use ($relative, $absolute) {
            $connection = GoogleWorkspaceConnection::firstOrFail();
            abort_unless($connection->storage_folder_id && $connection->drive_id, 409, 'Configure the private storage folder first.');
            $sha = hash_file('sha256', $absolute);
            if ($existing = ContinuityFile::where('path', $relative)->first()) {
                abort_unless($existing->sha256 === $sha, 409, 'Stored file content changed; use a new file path.');

                return $existing;
            }
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($absolute);
            abort_unless(in_array($mime, ['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/plain', 'text/csv'], true), 422, 'Unsupported business document type.');
            $folder = $this->google->api('GET', 'drive', 'files/'.$connection->storage_folder_id, ['query' => ['supportsAllDrives' => 'true', 'fields' => 'id,driveId,trashed,mimeType']]);
            abort_unless(($folder['driveId'] ?? null) === $connection->drive_id && ($folder['mimeType'] ?? '') === 'application/vnd.google-apps.folder' && ! ($folder['trashed'] ?? false), 409, 'Storage folder is no longer valid.');
            $marker = hash('sha256', $relative);
            $existingFiles = $this->google->api('GET', 'drive', 'files', ['query' => [
                'q' => "trashed=false and appProperties has { key='unifast_path' and value='".$marker."' }",
                'corpora' => 'drive', 'driveId' => $connection->drive_id, 'includeItemsFromAllDrives' => 'true', 'supportsAllDrives' => 'true', 'fields' => 'files(id,sha256Checksum,size)',
            ]]);
            abort_if(count($existingFiles['files'] ?? []) > 1, 409, 'Duplicate stored files require review.');
            $file = $existingFiles['files'][0] ?? null;
            if (! $file) {
                $boundary = 'unifast_'.bin2hex(random_bytes(16));
                $metadata = json_encode(['name' => basename($relative), 'parents' => [$connection->storage_folder_id], 'appProperties' => ['unifast_path' => $marker]], JSON_THROW_ON_ERROR);
                // Bounded multipart request. No original student path is sent in metadata.
                $body = '--'.$boundary."\r\nContent-Type: application/json; charset=UTF-8\r\n\r\n".$metadata."\r\n--".$boundary."\r\nContent-Type: ".$mime."\r\n\r\n".file_get_contents($absolute)."\r\n--".$boundary.'--';
                try {
                    $response = Http::withToken($this->google->token())->withoutRedirecting()->connectTimeout(5)->timeout(120)
                        ->withBody($body, 'multipart/related; boundary='.$boundary)
                        ->post('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&supportsAllDrives=true&fields=id,sha256Checksum,size');
                } catch (ConnectionException) {
                    abort(503, 'Google Drive upload is unavailable. Retry later.');
                }
                abort_unless($response->successful(), 503, 'Google Drive could not store the file.');
                $file = $response->json();
            }
            abort_unless(is_string($file['id'] ?? null) && ($file['sha256Checksum'] ?? '') === $sha && (int) ($file['size'] ?? -1) === filesize($absolute), 409, 'Google Drive file verification failed.');

            return ContinuityFile::create(['path' => $relative, 'drive_id' => $connection->drive_id, 'file_id' => $file['id'], 'sha256' => $sha, 'mime_type' => $mime, 'size' => filesize($absolute)]);
        });
    }

    public function materialize(string $relative): ?string
    {
        if (! config('continuity.enabled')) {
            return null;
        }
        $mapping = ContinuityFile::where('path', $relative)->where('status', 'verified')->first();
        if (! $mapping) {
            return null;
        }

        return Cache::lock('continuity:download:'.$mapping->id, 180)->block(5, function () use ($mapping) {
            $path = 'continuity-cache/'.$mapping->sha256;
            $disk = Storage::disk('local');
            $disk->makeDirectory('continuity-cache');
            if ($disk->exists($path) && hash_file('sha256', $disk->path($path)) === $mapping->sha256) {
                return $disk->path($path);
            }
            $meta = $this->google->api('GET', 'drive', 'files/'.$mapping->file_id, ['query' => ['supportsAllDrives' => 'true', 'fields' => 'driveId,trashed,size,sha256Checksum']]);
            abort_unless(($meta['driveId'] ?? '') === $mapping->drive_id && ! ($meta['trashed'] ?? false) && ($meta['sha256Checksum'] ?? '') === $mapping->sha256 && (int) ($meta['size'] ?? -1) === (int) $mapping->size, 409, 'The stored file changed. Administrator review is required.');
            $temporary = tempnam($disk->path('continuity-cache'), 'download_');
            try {
                $response = Http::withToken($this->google->token())->withoutRedirecting()->connectTimeout(5)->timeout(120)
                    ->sink($temporary)->get('https://www.googleapis.com/drive/v3/files/'.$mapping->file_id, ['alt' => 'media', 'supportsAllDrives' => 'true']);
                abort_unless($response->successful() && hash_file('sha256', $temporary) === $mapping->sha256, 503, 'The document could not be verified.');
                rename($temporary, $disk->path($path));
                chmod($disk->path($path), 0600);

                return $disk->path($path);
            } catch (ConnectionException) {
                abort(503, 'Google Drive download is unavailable.');
            } finally {
                if (is_file($temporary)) {
                    unlink($temporary);
                }
            }
        });
    }
}
