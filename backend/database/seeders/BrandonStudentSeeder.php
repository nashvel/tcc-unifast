<?php

namespace Database\Seeders;

use App\Models\AcademicProgram;
use App\Models\Batch;
use App\Models\DocumentSubmission;
use App\Models\Grantee;
use App\Models\GranteeIdentityProfile;
use App\Models\KycProfile;
use App\Models\MasterlistImport;
use App\Models\MasterlistRow;
use App\Models\PolicySetting;
use App\Models\User;
use Database\Seeders\Concerns\RestrictedToLocalEnvironment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * Seeds Brandon Reyes — identity onboarding already complete, vault unlocked.
 *
 * Identity is verified once during onboarding, so the vault holds only the three
 * document slots. Brandon uploads:
 *   - Course History (PDF)
 *   - Grade Slip (PDF)
 *   - 3 Specimen Signatures (image)
 *
 * Login: brandon@tcc.edu.ph / password
 * Student ID: 2026-BRANDON
 * Upload: /student/documents
 *
 * Re-seed: C:\php84\php.exe artisan db:seed --class=BrandonStudentSeeder
 */
class BrandonStudentSeeder extends Seeder
{
    use RestrictedToLocalEnvironment;

    public const EMAIL = 'brandon@tcc.edu.ph';

    public const PASSWORD = 'password';

    public const STUDENT_ID = '2026-BRANDON';

    public const FULL_NAME = 'Brandon Reyes';

    public const PROGRAM = 'BSIT';

    /** Slots left empty for Brandon to upload. */
    private const UPLOAD_SLOTS = [
        'course_history',
        'grade_slip',
        'specimen_signatures',
    ];

    public function run(): void
    {
        $this->assertLocalEnvironment();

        AcademicProgram::query()->updateOrCreate(
            ['code' => self::PROGRAM],
            [
                'name' => 'Bachelor of Science in Information Technology',
                'pass_grade' => 3.0,
                'is_active' => true,
            ],
        );
        PolicySetting::setValue('max_failed_subjects_per_semester', '3');
        PolicySetting::setValue('default_pass_grade', '3.0');

        $batch = Batch::query()->updateOrCreate(
            ['name' => 'TES AY 2026-2027 1st (Brandon Upload Test)'],
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
                'name' => self::FULL_NAME,
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
                'full_name' => self::FULL_NAME,
                'email' => self::EMAIL,
                'program' => self::PROGRAM,
                'year_level' => '3',
                'status' => 'verified',
                'submission_status' => 'not_submitted',
                'submitted_at' => null,
            ],
        );

        KycProfile::query()->updateOrCreate(
            ['grantee_id' => $grantee->id],
            [
                'user_id' => $user->id,
                'year_level' => '3',
                'status' => 'verified',
            ],
        );

        $import = MasterlistImport::query()->updateOrCreate(
            [
                'batch_id' => $batch->id,
                'original_name' => 'brandon-upload-seed.csv',
            ],
            [
                'uploaded_by' => $user->id,
                'stored_path' => 'masterlist-imports/brandon-upload-seed.csv',
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
                'full_name' => self::FULL_NAME,
                'email' => self::EMAIL,
                'program' => self::PROGRAM,
                'year_level' => '3',
                'status' => 'valid',
            ],
        );

        $idRefHash = bin2hex(random_bytes(16));
        $selfieHash = bin2hex(random_bytes(16));
        $idRefPath = "identity/{$grantee->id}/{$idRefHash}_id_reference_face.jpg";
        $selfiePath = "identity/{$grantee->id}/{$selfieHash}_onboarding_selfie.jpg";
        $this->ensureIdentityPhotos($idRefPath, $selfiePath);

        // Unit vector shared by the ID reference and onboarding selfie so the
        // identity profile reads as a clean auto-pass.
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
                    'extracted_name' => self::FULL_NAME,
                    'full_name' => self::FULL_NAME,
                    'student_id' => self::STUDENT_ID,
                    'program' => self::PROGRAM,
                ],
                'id_qr_payload' => 'https://registrar.tcc.edu.ph/verify/'.self::STUDENT_ID,
            ],
        );

        $this->clearUploadSlots($grantee, $batch);

        $this->command?->info('');
        $this->command?->info('=== Brandon Reyes — ready to upload requirements ===');
        $this->command?->info('Email: '.self::EMAIL);
        $this->command?->info('Password: '.self::PASSWORD);
        $this->command?->info('Student ID: '.self::STUDENT_ID);
        $this->command?->info('Name: '.self::FULL_NAME);
        $this->command?->info('account_status: active (identity onboarding pre-completed)');
        $this->command?->info('Batch: '.$batch->name.' (window open)');
        $this->command?->info('');
        $this->command?->info('Vault slots (3): Course History (PDF), Grade Slip (PDF), ID Back-to-Back & Specimen (PDF or image)');
        $this->command?->info('Login → /login then upload at /student/documents');
        $this->command?->info('');
    }

    /**
     * Remove CH / GS / specimen so Brandon can upload fresh.
     */
    private function clearUploadSlots(Grantee $grantee, Batch $batch): void
    {
        $existing = DocumentSubmission::query()
            ->where('grantee_id', $grantee->id)
            ->where('batch_id', $batch->id)
            ->whereIn('slot_key', self::UPLOAD_SLOTS)
            ->get();

        foreach ($existing as $doc) {
            if (is_string($doc->stored_path) && $doc->stored_path !== '') {
                Storage::disk('local')->delete($doc->stored_path);
            }
            $doc->delete();
        }
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
