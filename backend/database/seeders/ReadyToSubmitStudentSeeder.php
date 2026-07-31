<?php

namespace Database\Seeders;

use App\Models\Batch;
use App\Models\Grantee;
use App\Models\GranteeIdentityProfile;
use App\Models\KycProfile;
use App\Models\MasterlistImport;
use App\Models\MasterlistRow;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * Seeds one student who already finished KYC + face/identity onboarding
 * and can open /student/documents under an active batch window.
 *
 * Login: ready@tcc.edu.ph / password
 */
class ReadyToSubmitStudentSeeder extends Seeder
{
    public const EMAIL = 'ready@tcc.edu.ph';

    public const PASSWORD = 'password';

    public const STUDENT_ID = '2026-READY01';

    public function run(): void
    {
        $batch = Batch::query()->updateOrCreate(
            ['name' => 'TES AY 2026-2027 1st (Ready Submit Test)'],
            [
                'academic_year' => '2026-2027',
                'semester' => '1st Semester',
                'status' => 'active',
                'window_status' => 'active',
                'is_active' => true,
                'submission_deadline' => now()->addDays(30),
            ],
        );

        $user = User::query()->updateOrCreate(
            ['email' => self::EMAIL],
            [
                'name' => 'Ready Submit Tester',
                'role' => 'student',
                'student_id' => self::STUDENT_ID,
                'account_status' => 'active',
                'password' => Hash::make(self::PASSWORD),
                'email_verified_at' => now(),
                'activated_at' => now(),
            ],
        );

        $grantee = Grantee::query()->updateOrCreate(
            ['student_id' => self::STUDENT_ID, 'batch_id' => $batch->id],
            [
                'user_id' => $user->id,
                'student_number' => self::STUDENT_ID,
                'full_name' => 'Ready Submit Tester',
                'email' => self::EMAIL,
                'program' => 'BSIT',
                'year_level' => '2',
                'status' => 'verified',
                'submission_status' => 'not_submitted',
            ],
        );

        KycProfile::query()->updateOrCreate(
            ['grantee_id' => $grantee->id],
            [
                'user_id' => $user->id,
                'full_name' => 'Ready Submit Tester',
                'student_id' => self::STUDENT_ID,
                'program' => 'BSIT',
                'year_level' => '2',
                'status' => 'verified',
            ],
        );

        $import = MasterlistImport::query()->updateOrCreate(
            [
                'batch_id' => $batch->id,
                'original_name' => 'ready-submit-seed.csv',
            ],
            [
                'uploaded_by' => $user->id,
                'stored_path' => 'masterlist-imports/ready-submit-seed.csv',
                'status' => 'imported',
                'total_rows' => 1,
                'valid_rows' => 1,
                'imported_rows' => 1,
            ],
        );

        MasterlistRow::query()->updateOrCreate(
            [
                'masterlist_import_id' => $import->id,
                'student_id' => self::STUDENT_ID,
            ],
            [
                'row_number' => 2,
                'full_name' => 'Ready Submit Tester',
                'email' => self::EMAIL,
                'program' => 'BSIT',
                'year_level' => '2',
                'status' => 'valid',
            ],
        );

        $idRefHash = bin2hex(random_bytes(16));
        $selfieHash = bin2hex(random_bytes(16));
        $idRefPath = "identity/{$grantee->id}/{$idRefHash}_id_reference_face.jpg";
        $selfiePath = "identity/{$grantee->id}/{$selfieHash}_onboarding_selfie.jpg";
        $this->ensureIdentityPhotos($idRefPath, $selfiePath);

        // Unit L2 vector (matches FaceDescriptorMath + confirm face-bind). Same vector must
        // be written onto Slot 1 school_id.face_descriptor_payload when seeding drafts.
        $faceDescriptor = $this->seedFaceDescriptor(0);

        GranteeIdentityProfile::query()->updateOrCreate(
            ['grantee_id' => $grantee->id],
            [
                'user_id' => $user->id,
                'status' => 'completed',
                'id_reference_face_path' => $idRefPath,
                'onboarding_selfie_path' => $selfiePath,
                'id_reference_face_descriptor' => $faceDescriptor,
                'onboarding_selfie_descriptor' => $faceDescriptor,
                'onboarding_face_distance' => 0.0,
                'onboarding_challenge_sequence' => ['blink', 'turn_left', 'turn_right'],
                'id_scan_completed_at' => now()->subHours(2),
                'onboarding_completed_at' => now()->subHour(),
                'id_ocr_payload' => [
                    'extracted_name' => 'Ready Submit Tester',
                    'full_name' => 'Ready Submit Tester',
                    'student_id' => self::STUDENT_ID,
                    'program' => 'BSIT',
                ],
                'id_qr_payload' => 'https://registrar.tcc.edu.ph/verify/'.self::STUDENT_ID,
            ],
        );

        $this->command?->info('Ready-to-submit student seeded.');
        $this->command?->info('Email: '.self::EMAIL);
        $this->command?->info('Password: '.self::PASSWORD);
        $this->command?->info('Student ID: '.self::STUDENT_ID);
        $this->command?->info('Tip: in browser console run localStorage.setItem("tcc_student_identity_verified","1") so Documents shows in the nav.');
    }

    private function ensureIdentityPhotos(string $idRefPath, string $selfiePath): void
    {
        $candidates = [
            base_path('../frontend/src/assets/sample-student-id.png'),
            base_path('apps/react-mock/src/assets/dashboard-header.jpg'),
            public_path('favicon-32x32.png'),
        ];

        $source = null;
        foreach ($candidates as $path) {
            if (is_file($path)) {
                $source = $path;
                break;
            }
        }

        $bytes = $source ? file_get_contents($source) : $this->tinyJpeg();

        // Prefer private local disk (VaultFileStorage); keep public copy for legacy reads.
        Storage::disk('local')->put($idRefPath, $bytes);
        Storage::disk('local')->put($selfiePath, $bytes);
        Storage::disk('public')->put($idRefPath, $bytes);
        Storage::disk('public')->put($selfiePath, $bytes);
    }

    /**
     * @return list<float>
     */
    private function seedFaceDescriptor(int $hotIndex = 0): array
    {
        $raw = array_fill(0, 128, 0.0);
        $raw[$hotIndex % 128] = 1.0;

        return $raw;
    }

    private function tinyJpeg(): string
    {
        return base64_decode(
            '/9j/4AAQSkZJRgABAQAAAQABAAD/2wCEAAkGBxAQEBUQEBAVFRUVFRUVFRUVFRUWFxUVFRUYHSggGBolGxUVITEhJSkrLi4uFx8zODMtNygtLisBCgoKDg0OGxAQGy0lHyUtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLf/AABEIAAEAAQMBIgACEQEDEQH/xAAbAAACAwEBAQAAAAAAAAAAAAADBAECBQYAB//EADUQAAIBAgQDBgUDBQEAAAAAAAECAwQRAAUSITFBBhMiUWFxMoGRocEHQlKhFRYjcoLh/8QAGQEAAwEBAQAAAAAAAAAAAAAAAAECAwQF/8QAIREAAgICAgMBAQEAAAAAAAAAAAECEQMhEjFBBBNRImH/2gAMAwEAAhEDEQA/APn+iiigAooooAKKKKACiiigD//Z'
        );
    }
}
