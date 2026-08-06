<?php

namespace App\Console\Commands;

use App\Models\DocumentSubmission;
use App\Models\GranteeIdentityProfile;
use App\Models\RequirementIdentityCheck;
use App\Support\VaultFileStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MigrateVaultPrivateStorageCommand extends Command
{
    protected $signature = 'vault:migrate-private-storage
                            {--delete-public : Remove public copies after a successful private copy}
                            {--restructure : Move legacy paths into documents/{grantee}/{batch}/ and identity/{grantee}/{hash}_{role}.ext}
                            {--dry-run : Report moves without writing}';

    protected $description = 'Move vault/identity files to private local storage and optionally restructure hashed paths';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $deletePublic = (bool) $this->option('delete-public');
        $restructure = (bool) $this->option('restructure');

        $moved = 0;
        $skipped = 0;
        $missing = 0;
        $restructured = 0;

        $paths = $this->collectReferencedPaths();

        foreach ($paths->unique()->values() as $relative) {
            $normalized = VaultFileStorage::tryNormalizeRelativePath((string) $relative);
            if ($normalized === null) {
                $this->warn("Skipping unsafe path: {$relative}");
                $skipped++;
                continue;
            }

            $onPrivate = Storage::disk(VaultFileStorage::DISK)->exists($normalized);
            $onPublic = Storage::disk(VaultFileStorage::LEGACY_DISK)->exists($normalized);

            if ($onPrivate && ! $onPublic) {
                $skipped++;
                continue;
            }

            if (! $onPublic) {
                $this->line("Missing on public disk: {$normalized}");
                $missing++;
                continue;
            }

            if ($dryRun) {
                $this->info("[dry-run] Would move {$normalized}");
                $moved++;
                continue;
            }

            $contents = Storage::disk(VaultFileStorage::LEGACY_DISK)->get($normalized);
            Storage::disk(VaultFileStorage::DISK)->put($normalized, $contents);

            if (! Storage::disk(VaultFileStorage::DISK)->exists($normalized)) {
                $this->error("Failed to write private copy: {$normalized}");

                return self::FAILURE;
            }

            if ($deletePublic) {
                Storage::disk(VaultFileStorage::LEGACY_DISK)->delete($normalized);
            }

            $this->info(($deletePublic ? 'Moved' : 'Copied').": {$normalized}");
            $moved++;
        }

        if ($restructure) {
            $result = $this->restructurePaths($dryRun);
            $restructured = $result['restructured'];
            $skipped += $result['skipped'];
            $missing += $result['missing'];
            if ($result['failed']) {
                return self::FAILURE;
            }
        }

        $this->newLine();
        $this->table(
            ['metric', 'count'],
            [
                ['moved_or_copied', $moved],
                ['restructured', $restructured],
                ['already_private_or_skipped', $skipped],
                ['missing_on_disk', $missing],
            ],
        );

        if (! $dryRun && $deletePublic) {
            $this->comment('Public copies deleted where migration succeeded. Restart web server if /storage is cached.');
        } elseif (! $dryRun && ! $restructure) {
            $this->comment('Public copies retained. Re-run with --delete-public after verifying private reads.');
            $this->comment('Add --restructure to rewrite paths to documents/{grantee}/{batch}/{hash}.ext layout.');
        }

        return self::SUCCESS;
    }

    /**
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function collectReferencedPaths()
    {
        $paths = collect();

        DocumentSubmission::query()
            ->select(['id', 'stored_path', 'secondary_stored_path', 'metadata_payload'])
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($paths): void {
                foreach ($rows as $row) {
                    foreach ([$row->stored_path, $row->secondary_stored_path] as $path) {
                        if (is_string($path) && $path !== '') {
                            $paths->push($path);
                        }
                    }
                    $frame = data_get($row->metadata_payload, 'frame_path');
                    if (is_string($frame) && $frame !== '') {
                        $paths->push($frame);
                    }
                }
            });

        GranteeIdentityProfile::query()
            ->select(['id', 'id_reference_face_path', 'onboarding_selfie_path', 'id_ocr_payload'])
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($paths): void {
                foreach ($rows as $row) {
                    foreach ([$row->id_reference_face_path, $row->onboarding_selfie_path] as $path) {
                        if (is_string($path) && $path !== '') {
                            $paths->push($path);
                        }
                    }
                    $frame = data_get($row->id_ocr_payload, 'frame_path');
                    if (is_string($frame) && $frame !== '') {
                        $paths->push($frame);
                    }
                }
            });

        RequirementIdentityCheck::query()
            ->select(['id', 'selfie_path'])
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($paths): void {
                foreach ($rows as $row) {
                    if (is_string($row->selfie_path) && $row->selfie_path !== '') {
                        $paths->push($row->selfie_path);
                    }
                }
            });

        return $paths;
    }

    /**
     * @return array{restructured: int, skipped: int, missing: int, failed: bool}
     */
    private function restructurePaths(bool $dryRun): array
    {
        $restructured = 0;
        $skipped = 0;
        $missing = 0;

        DocumentSubmission::query()
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($dryRun, &$restructured, &$skipped, &$missing): void {
                foreach ($rows as $row) {
                    foreach (['stored_path', 'secondary_stored_path'] as $column) {
                        $current = $row->{$column};
                        if (! is_string($current) || $current === '') {
                            continue;
                        }

                        $role = $this->documentRoleForColumn($row, $column);
                        $target = $this->targetDocumentPath($row, $current, $role);
                        $result = $this->relocateAndUpdate(
                            $current,
                            $target,
                            function (string $newPath) use ($row, $column): void {
                                $row->{$column} = $newPath;
                                $row->save();
                            },
                            $dryRun,
                        );
                        $restructured += $result['restructured'];
                        $skipped += $result['skipped'];
                        $missing += $result['missing'];
                    }

                    $frame = data_get($row->metadata_payload, 'frame_path');
                    if (is_string($frame) && $frame !== '') {
                        $target = $this->targetDocumentPath($row, $frame, 'id_frame');
                        $result = $this->relocateAndUpdate(
                            $frame,
                            $target,
                            function (string $newPath) use ($row): void {
                                $payload = $row->metadata_payload ?? [];
                                $payload['frame_path'] = $newPath;
                                $row->metadata_payload = $payload;
                                $row->save();
                            },
                            $dryRun,
                        );
                        $restructured += $result['restructured'];
                        $skipped += $result['skipped'];
                        $missing += $result['missing'];
                    }
                }
            });

        GranteeIdentityProfile::query()
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($dryRun, &$restructured, &$skipped, &$missing): void {
                foreach ($rows as $row) {
                    foreach ([
                        'id_reference_face_path' => 'id_reference_face',
                        'onboarding_selfie_path' => 'onboarding_selfie',
                    ] as $column => $role) {
                        $current = $row->{$column};
                        if (! is_string($current) || $current === '') {
                            continue;
                        }
                        $target = $this->targetIdentityPath((int) $row->grantee_id, $current, $role);
                        $result = $this->relocateAndUpdate(
                            $current,
                            $target,
                            function (string $newPath) use ($row, $column): void {
                                $row->{$column} = $newPath;
                                $row->save();
                            },
                            $dryRun,
                        );
                        $restructured += $result['restructured'];
                        $skipped += $result['skipped'];
                        $missing += $result['missing'];
                    }

                    $frame = data_get($row->id_ocr_payload, 'frame_path');
                    if (is_string($frame) && $frame !== '') {
                        $target = $this->targetIdentityPath((int) $row->grantee_id, $frame, 'id_onboarding_frame');
                        $result = $this->relocateAndUpdate(
                            $frame,
                            $target,
                            function (string $newPath) use ($row): void {
                                $payload = $row->id_ocr_payload ?? [];
                                $payload['frame_path'] = $newPath;
                                $row->id_ocr_payload = $payload;
                                $row->save();
                            },
                            $dryRun,
                        );
                        $restructured += $result['restructured'];
                        $skipped += $result['skipped'];
                        $missing += $result['missing'];
                    }
                }
            });

        RequirementIdentityCheck::query()
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($dryRun, &$restructured, &$skipped, &$missing): void {
                foreach ($rows as $row) {
                    $current = $row->selfie_path;
                    if (! is_string($current) || $current === '') {
                        continue;
                    }
                    $target = $this->targetIdentityPath((int) $row->grantee_id, $current, 'submission_selfie');
                    $result = $this->relocateAndUpdate(
                        $current,
                        $target,
                        function (string $newPath) use ($row): void {
                            $row->selfie_path = $newPath;
                            $row->save();
                        },
                        $dryRun,
                    );
                    $restructured += $result['restructured'];
                    $skipped += $result['skipped'];
                    $missing += $result['missing'];
                }
            });

        return [
            'restructured' => $restructured,
            'skipped' => $skipped,
            'missing' => $missing,
            'failed' => false,
        ];
    }

    /**
     * @param  callable(string): void  $persist
     * @return array{restructured: int, skipped: int, missing: int}
     */
    private function relocateAndUpdate(string $current, ?string $target, callable $persist, bool $dryRun): array
    {
        $normalized = VaultFileStorage::tryNormalizeRelativePath($current);
        if ($normalized === null) {
            $this->warn("Skipping unsafe path: {$current}");

            return ['restructured' => 0, 'skipped' => 1, 'missing' => 0];
        }

        if ($target === null || $target === $normalized) {
            return ['restructured' => 0, 'skipped' => 1, 'missing' => 0];
        }

        $contents = $this->readBytes($normalized);
        if ($contents === null) {
            $this->line("Missing on disk for restructure: {$normalized}");

            return ['restructured' => 0, 'skipped' => 0, 'missing' => 1];
        }

        if ($dryRun) {
            $this->info("[dry-run] Would restructure {$normalized} -> {$target}");

            return ['restructured' => 1, 'skipped' => 0, 'missing' => 0];
        }

        Storage::disk(VaultFileStorage::DISK)->put($target, $contents);
        if (! Storage::disk(VaultFileStorage::DISK)->exists($target)) {
            $this->error("Failed to write restructured path: {$target}");

            return ['restructured' => 0, 'skipped' => 0, 'missing' => 1];
        }

        $persist($target);

        if ($target !== $normalized) {
            VaultFileStorage::deleteIfOwned($normalized);
        }

        $this->info("Restructured: {$normalized} -> {$target}");

        return ['restructured' => 1, 'skipped' => 0, 'missing' => 0];
    }

    private function readBytes(string $relative): ?string
    {
        foreach ([VaultFileStorage::DISK, VaultFileStorage::LEGACY_DISK] as $disk) {
            if (Storage::disk($disk)->exists($relative)) {
                $bytes = Storage::disk($disk)->get($relative);

                return is_string($bytes) ? $bytes : null;
            }
        }

        return null;
    }

    private function documentRoleForColumn(DocumentSubmission $row, string $column): ?string
    {
        if ($column === 'stored_path' && $row->slot_key === 'school_id') {
            return 'id_scan_submission';
        }

        return null;
    }

    private function targetDocumentPath(DocumentSubmission $row, string $current, ?string $identityRole = null): ?string
    {
        $normalized = VaultFileStorage::tryNormalizeRelativePath($current);
        if ($normalized === null) {
            return null;
        }

        // School ID face crops belong under identity/, not documents/.
        if ($identityRole === 'id_scan_submission') {
            return $this->targetIdentityPath((int) ($row->grantee_id ?: 0), $normalized, 'id_scan_submission');
        }

        if (VaultFileStorage::looksLikeStructuredDocumentPath($normalized)) {
            return $normalized;
        }

        $granteeId = (int) ($row->grantee_id ?: 0);
        $batchId = (int) ($row->batch_id ?: 0);
        if ($granteeId < 1 || $batchId < 1) {
            $this->warn("Cannot restructure without grantee/batch on submission #{$row->id}: {$normalized}");

            return null;
        }

        $ext = pathinfo($normalized, PATHINFO_EXTENSION) ?: 'bin';
        $ext = preg_match('/^[a-z0-9]{1,10}$/i', $ext) === 1 ? strtolower($ext) : 'bin';
        $hash = bin2hex(random_bytes(16));

        return "documents/{$granteeId}/{$batchId}/{$hash}.{$ext}";
    }

    private function targetIdentityPath(int $granteeId, string $current, string $role): ?string
    {
        $normalized = VaultFileStorage::tryNormalizeRelativePath($current);
        if ($normalized === null) {
            return null;
        }

        if (VaultFileStorage::looksLikeStructuredIdentityPath($normalized)) {
            return $normalized;
        }

        if ($granteeId < 1) {
            $this->warn("Cannot restructure identity path without grantee: {$normalized}");

            return null;
        }

        $role = VaultFileStorage::normalizeIdentityRole($role);
        if ($role === null) {
            return null;
        }

        $ext = pathinfo($normalized, PATHINFO_EXTENSION) ?: 'jpg';
        $ext = preg_match('/^[a-z0-9]{1,10}$/i', $ext) === 1 ? strtolower($ext) : 'jpg';
        $hash = bin2hex(random_bytes(16));

        return "identity/{$granteeId}/{$hash}_{$role}.{$ext}";
    }
}
