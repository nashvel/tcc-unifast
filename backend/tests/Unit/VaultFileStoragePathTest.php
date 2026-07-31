<?php

namespace Tests\Unit;

use App\Support\VaultFileStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VaultFileStoragePathTest extends TestCase
{
    public function test_store_document_uses_grantee_batch_hashed_path(): void
    {
        Storage::fake('local');
        $file = UploadedFile::fake()->createWithContent('history.pdf', "%PDF-1.4\n%%EOF\n");

        $path = VaultFileStorage::storeDocument($file, 42, 7);

        $this->assertMatchesRegularExpression('#^documents/42/7/[a-f0-9]{32}\.pdf$#', $path);
        $this->assertTrue(Storage::disk('local')->exists($path));
        $this->assertTrue(VaultFileStorage::looksLikeStructuredDocumentPath($path));
    }

    public function test_store_identity_uses_hashed_role_suffix_path(): void
    {
        Storage::fake('local');
        $file = UploadedFile::fake()->image('face.jpg', 120, 120);

        $path = VaultFileStorage::storeIdentity($file, 9, 'id_reference_face.jpg');

        $this->assertMatchesRegularExpression('#^identity/9/[a-f0-9]{32}_id_reference_face\.jpg$#', $path);
        $this->assertTrue(Storage::disk('local')->exists($path));
        $this->assertTrue(VaultFileStorage::looksLikeStructuredIdentityPath($path));
        $this->assertFalse(Storage::disk('local')->exists('identity/9/id_reference_face.jpg'));
    }

    public function test_path_traversal_still_rejected(): void
    {
        $this->assertNull(VaultFileStorage::tryNormalizeRelativePath('../etc/passwd'));
        $this->assertNull(VaultFileStorage::tryNormalizeRelativePath('documents/1/2/../../secret.pdf'));
    }
}
