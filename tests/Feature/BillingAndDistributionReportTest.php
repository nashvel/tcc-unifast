<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\BillingReport;
use App\Models\BillingReportItem;
use App\Models\Grantee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BillingAndDistributionReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('The pdo_sqlite extension is required for billing report tests.');
        }

        parent::setUp();
    }

    public function test_admin_can_generate_call_for_billing_with_verified_only(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['role' => 'admin', 'account_status' => 'active']);
        $batch = $this->makeBatch();
        $this->seedGrantees($batch);

        $response = $this->actingAs($admin)
            ->postJson('/api/billing-reports', ['batch_id' => $batch->id])
            ->assertCreated()
            ->assertJsonPath('data.type', BillingReport::TYPE_CALL_FOR_BILLING)
            ->assertJsonPath('data.total_grantees', 2)
            ->assertJsonPath('data.total_amount', 20000);

        $reportId = $response->json('data.id');
        $this->assertDatabaseHas('billing_reports', [
            'id' => $reportId,
            'type' => BillingReport::TYPE_CALL_FOR_BILLING,
            'total_grantees' => 2,
        ]);
        $this->assertSame(2, BillingReportItem::query()->where('billing_report_id', $reportId)->count());
        $this->assertTrue(
            AuditLog::query()->where('module', 'Billing')->where('action', 'Generated call-for-billing report')->exists()
        );

        $path = $response->json('data.file_path');
        Storage::disk('public')->assertExists($path);

        $this->actingAs($admin)
            ->get("/api/billing-reports/{$reportId}/download")
            ->assertOk();
    }

    public function test_admin_can_generate_distribution_report_with_exclusions(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['role' => 'admin', 'account_status' => 'active']);
        $batch = $this->makeBatch();
        $this->seedGrantees($batch);

        $response = $this->actingAs($admin)
            ->postJson('/api/distribution-reports', ['batch_id' => $batch->id])
            ->assertCreated()
            ->assertJsonPath('data.type', BillingReport::TYPE_DISTRIBUTION)
            ->assertJsonPath('data.total_grantees', 2)
            ->assertJsonPath('data.excluded_count', 2)
            ->assertJsonPath('data.total_amount', 20000);

        $reportId = $response->json('data.id');
        $this->assertSame(4, BillingReportItem::query()->where('billing_report_id', $reportId)->count());
        $this->assertTrue(
            AuditLog::query()->where('module', 'Distribution')->where('action', 'Generated distribution report')->exists()
        );

        $this->actingAs($admin)
            ->get("/api/distribution-reports/{$reportId}/download")
            ->assertOk();
    }

    public function test_staff_cannot_generate_but_can_list(): void
    {
        Storage::fake('public');

        $staff = User::factory()->create(['role' => 'staff', 'account_status' => 'active']);
        $batch = $this->makeBatch();

        $this->actingAs($staff)
            ->postJson('/api/billing-reports', ['batch_id' => $batch->id])
            ->assertForbidden();

        $this->actingAs($staff)
            ->getJson('/api/billing-reports')
            ->assertOk();
    }

    private function makeBatch(): Batch
    {
        return Batch::create([
            'name' => 'AY 2026-2027 Sem 1',
            'academic_year' => 'AY 2026-2027',
            'semester' => '1st Semester',
            'status' => 'active',
            'window_status' => 'closed',
            'is_active' => false,
        ]);
    }

    private function seedGrantees(Batch $batch): void
    {
        Grantee::create([
            'batch_id' => $batch->id,
            'student_id' => 'STU-001',
            'student_number' => '2026-0001',
            'full_name' => 'Maria Santos',
            'email' => 'maria@example.com',
            'program' => 'BSIT',
            'status' => 'verified',
        ]);
        Grantee::create([
            'batch_id' => $batch->id,
            'student_id' => 'STU-002',
            'student_number' => '2026-0002',
            'full_name' => 'Juan Cruz',
            'email' => 'juan@example.com',
            'program' => 'BSBA',
            'status' => 'eligible',
        ]);
        Grantee::create([
            'batch_id' => $batch->id,
            'student_id' => 'STU-003',
            'student_number' => '2026-0003',
            'full_name' => 'Ana Reyes',
            'email' => 'ana@example.com',
            'program' => 'BSED',
            'status' => 'pending',
        ]);
        Grantee::create([
            'batch_id' => $batch->id,
            'student_id' => 'STU-004',
            'student_number' => '2026-0004',
            'full_name' => 'Pedro Lim',
            'email' => 'pedro@example.com',
            'program' => 'BSCE',
            'status' => 'non_compliant',
        ]);
    }
}
