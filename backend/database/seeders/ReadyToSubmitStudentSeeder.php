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
use App\Services\AcademicGradeParser;
use Database\Seeders\Concerns\RestrictedToLocalEnvironment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * Seeds one student who already finished KYC + face/identity onboarding
 * and can open /student/documents under an active batch window.
 *
 * Also seeds a complete non-draft 4-slot package (pending_review) so the
 * grantee appears in staff Document Validation (/app/documents).
 *
 * Course History + Grade Slip include structured OCR (terms[], grade_summary)
 * matching the Brandon-style Summer GS fixture so Pending/Dropped counts are
 * correct without re-running the pipeline. PDFs are browser-valid (Catalog/Pages).
 *
 * Login: ready@tcc.edu.ph / password
 * Staff: staff@unifast.gov.ph / password
 *
 * Re-seed: C:\php84\php.exe artisan db:seed --class=ReadyToSubmitStudentSeeder
 */
class ReadyToSubmitStudentSeeder extends Seeder
{
    use RestrictedToLocalEnvironment;

    public const EMAIL = 'ready@tcc.edu.ph';

    public const PASSWORD = 'password';

    public const STUDENT_ID = '2026-READY01';

    public const GRADE_SLIP_TERM = '2025-2026 Summer';

    /**
     * Identity is verified once during onboarding, so the vault has 3 slots.
     *
     * @var array<string, string>
     */
    private const PACKAGE_SLOTS = [
        'course_history' => 'Course History',
        'grade_slip' => 'Grade Slip',
        'specimen_signatures' => 'ID (Back-to-Back) & Specimen',
    ];

    public function run(): void
    {
        $this->assertLocalEnvironment();

        AcademicProgram::query()->updateOrCreate(
            ['code' => 'BSIT'],
            [
                'name' => 'Bachelor of Science in Information Technology',
                'pass_grade' => 3.0,
                'is_active' => true,
            ],
        );
        PolicySetting::setValue('max_failed_subjects_per_semester', '3');
        PolicySetting::setValue('default_pass_grade', '3.0');

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
                'submission_status' => 'docs_submitted',
            ],
        );

        KycProfile::query()->updateOrCreate(
            ['grantee_id' => $grantee->id],
            [
                'user_id' => $user->id,
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

        $this->seedStaffVisiblePackage($grantee, $batch, $faceDescriptor);

        $this->command?->info('Ready-to-submit student seeded.');
        $this->command?->info('Email: '.self::EMAIL);
        $this->command?->info('Password: '.self::PASSWORD);
        $this->command?->info('Student ID: '.self::STUDENT_ID);
        $this->command?->info('Staff package: 4/4 slots pending_review (visible at /app/documents).');
        $this->command?->info('CH OCR: GS-anchored to '.self::GRADE_SLIP_TERM.' (Pending Summer+current; older Dropped).');
        $this->command?->info('Tip: in browser console run localStorage.setItem("tcc_student_identity_verified","1") so Documents shows in the nav.');
    }

    /**
     * Complete non-draft package for DocumentSubmissionController::packages().
     * List requires DISTINCT slot_key count == 4 and status != draft.
     *
     * @param  list<float>  $faceDescriptor
     */
    private function seedStaffVisiblePackage(Grantee $grantee, Batch $batch, array $faceDescriptor): void
    {
        // Delete prior stub files so orphaned invalid PDFs do not linger.
        $existing = DocumentSubmission::query()
            ->where('grantee_id', $grantee->id)
            ->where('batch_id', $batch->id)
            ->get();
        foreach ($existing as $doc) {
            if (is_string($doc->stored_path) && $doc->stored_path !== '') {
                Storage::disk('local')->delete($doc->stored_path);
            }
        }

        $parser = new AcademicGradeParser;
        $chTerms = $this->courseHistoryTerms();
        $gsCourses = $this->gradeSlipCourses();
        $chParsed = $parser->parse(
            'COURSE HISTORY Ready Submit Tester',
            'BSIT',
            null,
            'course_history',
            $chTerms,
            self::GRADE_SLIP_TERM,
        );
        $gsParsed = $parser->parse(
            'GRADE SLIP Ready Submit Tester',
            'BSIT',
            $gsCourses,
            'grade_slip',
        );
        $mismatches = $parser->crossCheckChBlanksAgainstGradeSlip(
            $chParsed['terms'] ?? $chTerms,
            $chParsed['courses'] ?? null,
            $gsCourses,
            self::GRADE_SLIP_TERM,
        );
        $maxFailed = PolicySetting::maxFailedSubjects();
        $chGradeSummary = $this->buildGradeSummary($chParsed, 'course_history', $maxFailed, $mismatches);
        $gsGradeSummary = $this->buildGradeSummary($gsParsed, 'grade_slip', $maxFailed, []);

        foreach (self::PACKAGE_SLOTS as $slotKey => $label) {
            $hash = bin2hex(random_bytes(16));
            $storedPath = "documents/{$grantee->id}/{$batch->id}/{$hash}.pdf";
            $pdfBytes = $this->previewablePdf($label);
            Storage::disk('local')->put($storedPath, $pdfBytes);

            $attrs = [
                'student_id' => self::STUDENT_ID,
                'grantee_id' => $grantee->id,
                'batch_id' => $batch->id,
                'slot_key' => $slotKey,
                'student_name' => 'Ready Submit Tester',
                'document_type' => $label,
                'original_name' => "{$slotKey}.pdf",
                'stored_path' => $storedPath,
                'mime_type' => 'application/pdf',
                'file_size' => strlen($pdfBytes),
                'status' => 'pending_review',
                'risk_level' => 'low',
                'extracted_text' => null,
                'ocr_payload' => null,
                'metadata_payload' => null,
                'face_descriptor_payload' => null,
            ];

            if ($slotKey === 'course_history') {
                $attrs['extracted_text'] = $this->formatTermsTable($chParsed['terms'] ?? $chTerms);
                $attrs['ocr_payload'] = [
                    'engine' => 'seed',
                    'method' => 'ready_submit_fixture',
                    'result' => [
                        'combined_text' => $attrs['extracted_text'],
                        'formatted_table_text' => $attrs['extracted_text'],
                        'courses' => $chParsed['courses'] ?? [],
                        'terms' => $chParsed['terms'] ?? $chTerms,
                        'grade_summary' => $chGradeSummary,
                    ],
                ];
                $attrs['metadata_payload'] = [
                    'grade_summary' => $chGradeSummary,
                    'seed_fixture' => 'ready_submit_gs_anchor',
                ];
            }

            if ($slotKey === 'grade_slip') {
                $attrs['extracted_text'] = $this->formatCoursesTable($gsCourses);
                $attrs['ocr_payload'] = [
                    'engine' => 'seed',
                    'method' => 'ready_submit_fixture',
                    'result' => [
                        'combined_text' => $attrs['extracted_text'],
                        'formatted_table_text' => $attrs['extracted_text'],
                        'courses' => $gsParsed['courses'] ?? $gsCourses,
                        'terms' => [],
                        'grade_summary' => $gsGradeSummary,
                        // Intentionally omit academic_year / semester_label so UI/pipeline
                        // rely on course-overlap inference against CH Summer electives.
                    ],
                ];
                $attrs['metadata_payload'] = [
                    'grade_summary' => $gsGradeSummary,
                    'seed_fixture' => 'ready_submit_gs_anchor',
                ];
            }

            DocumentSubmission::query()->updateOrCreate(
                [
                    'grantee_id' => $grantee->id,
                    'batch_id' => $batch->id,
                    'slot_key' => $slotKey,
                ],
                $attrs,
            );
        }
    }

    /**
     * Ready-tester CH: older blank (Dropped) + Summer IT Elec blanks (Pending) +
     * current enrollment blanks (Pending) when anchored to Summer GS.
     *
     * @return list<array<string, mixed>>
     */
    private function courseHistoryTerms(): array
    {
        return [
            [
                'academic_term' => '2024-2025 2nd',
                'program_code' => 'BSIT',
                'program_raw' => 'BSIT',
                'year_level' => 'Year 2nd',
                'courses' => [
                    ['code' => 'OLD BLANK', 'description' => 'Older unfinished subject', 'units' => '3', 'grade' => null, 'instructor' => 'Staff', 'remarks' => null],
                    ['code' => 'OLD PASS', 'description' => 'Older passed subject', 'units' => '3', 'grade' => '1.5', 'instructor' => 'Staff', 'remarks' => 'Passed'],
                ],
            ],
            [
                'academic_term' => self::GRADE_SLIP_TERM,
                'program_code' => 'BSIT',
                'program_raw' => 'BSIT',
                'year_level' => 'Year 3rd',
                'courses' => [
                    ['code' => 'IT Elec 4', 'description' => 'Fundamentals of Data Warehousing&Data Mining', 'units' => '3', 'grade' => null, 'instructor' => 'Perez, Nikko', 'remarks' => null],
                    ['code' => 'IT Elec 5', 'description' => 'Multimedia System', 'units' => '3', 'grade' => null, 'instructor' => 'Romero, Marlo', 'remarks' => null],
                    ['code' => 'IT Elec 6', 'description' => 'Management Information System(MIS)', 'units' => '3', 'grade' => '1.5', 'instructor' => 'Senara, Alex', 'remarks' => 'Passed'],
                ],
            ],
            [
                'academic_term' => '2026-2027 1st',
                'program_code' => 'BSIT',
                'program_raw' => 'BSIT',
                'year_level' => 'Year 3rd',
                'enrollment_status' => 'ENROLLED',
                'courses' => [
                    ['code' => 'IT 128', 'description' => 'Current enrollment 1', 'units' => '3', 'grade' => null, 'instructor' => 'Staff', 'remarks' => null],
                    ['code' => 'IT 129', 'description' => 'Current enrollment 2', 'units' => '3', 'grade' => null, 'instructor' => 'Staff', 'remarks' => null],
                    ['code' => 'IT 130', 'description' => 'Current enrollment 3', 'units' => '3', 'grade' => null, 'instructor' => 'Staff', 'remarks' => null],
                    ['code' => 'IT 131', 'description' => 'Current enrollment 4', 'units' => '3', 'grade' => null, 'instructor' => 'Staff', 'remarks' => null],
                    ['code' => 'IT 132', 'description' => 'Current enrollment 5', 'units' => '3', 'grade' => null, 'instructor' => 'Staff', 'remarks' => null],
                ],
            ],
        ];
    }

    /**
     * GS table-only OCR (no year/semester) — matches Summer IT Elec electives on CH.
     *
     * @return list<array<string, mixed>>
     */
    private function gradeSlipCourses(): array
    {
        return [
            ['code' => 'IT Elec 4', 'description' => 'Fundamentals of Data Warehousing&Data Mining', 'units' => '3', 'grade' => null, 'instructor' => 'Perez, Nikko', 'remarks' => null],
            ['code' => 'IT Elec 5', 'description' => 'Multimedia System', 'units' => '3', 'grade' => null, 'instructor' => 'Romero, Marlo', 'remarks' => null],
            ['code' => 'IT Elec 6', 'description' => 'Management Information System(MIS)', 'units' => '3', 'grade' => '1.5', 'instructor' => 'Senara, Alex', 'remarks' => 'Passed'],
        ];
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @param  list<array{code: string, ch_grade: mixed, gs_grade: mixed}>  $mismatches
     * @return array<string, mixed>
     */
    private function buildGradeSummary(array $parsed, string $documentType, int $maxFailed, array $mismatches): array
    {
        $retention = (int) ($parsed['retention_count'] ?? 0);
        $pendingCount = (int) ($parsed['pending_count'] ?? 0);
        $isCh = $documentType === 'course_history';
        $gsTerm = $isCh ? self::GRADE_SLIP_TERM : null;

        $message = null;
        if ($isCh) {
            $message = $retention >= $maxFailed
                ? sprintf(
                    'Not eligible under retention: %d failed + %d dropped = %d (max %d). Pending blanks use Grade Slip term "%s" plus any newer Course History enrollment.',
                    (int) $parsed['failed_count'],
                    (int) $parsed['dropped_count'],
                    $retention,
                    $maxFailed,
                    self::GRADE_SLIP_TERM,
                )
                : sprintf(
                    'Pending blanks anchored to Grade Slip term "%s"%s.',
                    self::GRADE_SLIP_TERM,
                    $pendingCount > 0 ? sprintf(' (%d pending)', $pendingCount) : '',
                );
            if ($mismatches !== []) {
                $codes = implode(', ', array_column($mismatches, 'code'));
                $message = trim($message.' Staff flag: Course History blank but Grade Slip has a grade for: '.$codes.'.');
            }
        }

        return [
            'blank_count' => (int) ($parsed['blank_count'] ?? 0),
            'pending_count' => $pendingCount,
            'failed_count' => (int) ($parsed['failed_count'] ?? 0),
            'dropped_count' => (int) ($parsed['dropped_count'] ?? 0),
            'numeric_failed_count' => (int) ($parsed['numeric_failed_count'] ?? 0),
            'retention_count' => $retention,
            'pass_grade' => $parsed['pass_grade'] ?? 3.0,
            'program_code' => $parsed['program_code'] ?? 'BSIT',
            'max_failed' => $maxFailed,
            'document_type' => $documentType,
            'blanks_count_as_fails' => false,
            'blanks_count_as_dropped' => $isCh,
            'pending_term_window' => AcademicGradeParser::PENDING_TERM_WINDOW,
            'grade_slip_term' => $gsTerm,
            'terms_detected' => $isCh,
            'over_limit' => $retention >= $maxFailed,
            'term_count' => is_array($parsed['terms'] ?? null) ? count($parsed['terms']) : 0,
            'cross_check' => $isCh ? 'grade_slip_term' : null,
            'grade_mismatches' => $mismatches,
            'grade_mismatch_count' => count($mismatches),
            'message' => $message,
        ];
    }

    /**
     * Minimal PDF with Catalog/Pages/Page so browser PDF viewers can open it.
     */
    private function previewablePdf(string $title): string
    {
        $safe = preg_replace('/[^A-Za-z0-9 ._-]/', '', $title) ?: 'Document';
        $body = "BT /F1 18 Tf 60 720 Td ({$safe}) Tj 0 -28 Td /F1 12 Tf (Ready Submit Tester) Tj 0 -18 Td (Student ID: ".self::STUDENT_ID.') Tj 0 -18 Td (Preview fixture) Tj ET';
        $len = strlen($body);

        return "%PDF-1.4\n"
            ."1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
            ."2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
            ."3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]/Contents 4 0 R/Resources<</Font<</F1 5 0 R>>>>>>endobj\n"
            ."4 0 obj<</Length {$len}>>stream\n{$body}\nendstream\nendobj\n"
            ."5 0 obj<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>endobj\n"
            ."xref\n0 6\n0000000000 65535 f \n"
            ."trailer<</Size 6/Root 1 0 R>>\n"
            ."startxref\n0\n%%EOF\n";
    }

    /**
     * @param  list<array<string, mixed>>  $terms
     */
    private function formatTermsTable(array $terms): string
    {
        $blocks = [];
        foreach ($terms as $term) {
            if (! is_array($term)) {
                continue;
            }
            $header = trim(
                ($term['academic_term'] ?? 'Term').' '
                .($term['program_raw'] ?? $term['program_code'] ?? '')
                .(isset($term['year_level']) ? ' — '.$term['year_level'] : '')
            );
            $blocks[] = $header."\n".$this->formatCoursesTable(is_array($term['courses'] ?? null) ? $term['courses'] : []);
        }

        return implode("\n\n", $blocks);
    }

    /**
     * @param  list<array<string, mixed>>  $courses
     */
    private function formatCoursesTable(array $courses): string
    {
        $lines = [
            'Code | Description | Units | Grade | Instructor | Remarks',
            '-----+-------------+-------+-------+------------+--------',
        ];
        foreach ($courses as $course) {
            if (! is_array($course)) {
                continue;
            }
            $grade = $course['grade'] ?? '—';
            if ($grade === null || $grade === '') {
                $grade = '—';
            }
            $lines[] = sprintf(
                '%s | %s | %s | %s | %s | %s',
                (string) ($course['code'] ?? ''),
                (string) ($course['description'] ?? ''),
                (string) ($course['units'] ?? ''),
                (string) $grade,
                (string) ($course['instructor'] ?? ''),
                (string) ($course['remarks'] ?? ''),
            );
        }

        return implode("\n", $lines);
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
