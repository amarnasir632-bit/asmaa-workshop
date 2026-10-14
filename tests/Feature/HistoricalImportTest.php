<?php

namespace Tests\Feature;

use App\Models\HistoricalImportBatch;
use App\Models\HistoricalImportRecord;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HistoricalImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->withoutVite();
    }

    public function test_only_manager_can_access_historical_import_interface(): void
    {
        $employee = $this->staff('history.employee', 'employee');
        $this->actingAs($employee)->get(route('history.index'))->assertForbidden();
    }

    public function test_manager_can_stage_review_and_correct_history_without_changing_operational_ledgers(): void
    {
        $manager = $this->staff('history.manager', 'manager');
        $this->actingAs($manager);

        $this->post(route('history.batches.store'), [
            'source_description' => 'دفتر قديم صناعي',
            'imported_on' => '2026-10-14',
            'notes' => 'بيانات اختبار مصطنعة',
        ])->assertSessionHasNoErrors();
        $batchId = (int) DB::table('historical_import_batches')->value('id');
        $batch = HistoricalImportBatch::findOrFail($batchId);
        $payload = [
            'record_type' => 'financial_balance',
            'source_reference' => 'صفحة 17',
            'original_date' => '2022-05-03',
            'transcription' => 'رصيد مدون 1250 دون مستند تحصيل.',
            'confidence' => 'incomplete',
        ];
        $this->post(route('history.records.store', $batch), $payload)->assertSessionHasNoErrors();
        $record = HistoricalImportRecord::query()->firstOrFail();
        $this->assertSame('pending', $record->review_status);
        $this->assertDatabaseHas('historical_record_issues', ['historical_import_batch_id' => $batch->id, 'issue_type' => 'missing']);

        $this->patch(route('history.records.review', $record), [
            'review_status' => 'needs_correction',
            'review_notes' => 'المستند المشار إليه غير موجود.',
        ])->assertSessionHasNoErrors();
        $this->patch(route('history.records.correct', $record), [
            'transcription' => 'رصيد مدون 1250؛ مستند التحصيل غير موجود.',
            'reason' => 'تصحيح صياغة التوثيق فقط.',
        ])->assertSessionHasNoErrors();

        $record->refresh();
        $this->assertSame('pending', $record->review_status);
        $this->assertSame(1, $record->revisions()->count());
        $this->assertSame(0, DB::table('payments')->count());
        $this->assertSame(0, DB::table('inventory_movements')->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'history.record_corrected', 'subject_id' => $record->id]);
        $this->get(route('history.show', $batch))->assertOk()->assertSee('رصيد مالي افتتاحي تاريخي');
    }

    public function test_identical_transcription_is_flagged_as_duplicate_instead_of_stored_twice(): void
    {
        $manager = $this->staff('history.duplicate.manager', 'manager');
        $this->actingAs($manager);
        $this->post(route('history.batches.store'), ['source_description' => 'دفتر 1', 'imported_on' => '2026-10-14']);
        $batch = HistoricalImportBatch::query()->firstOrFail();
        $payload = [
            'record_type' => 'order', 'source_reference' => 'صفحة 1', 'original_date' => '2023-01-02',
            'transcription' => 'طلب تجريبي غير حقيقي 001', 'confidence' => 'confirmed',
        ];
        $this->post(route('history.records.store', $batch), $payload)->assertSessionHasNoErrors();
        $this->post(route('history.records.store', $batch), $payload)->assertSessionHasNoErrors();

        $this->assertSame(1, HistoricalImportRecord::query()->count());
        $this->assertDatabaseHas('historical_record_issues', ['issue_type' => 'duplicate_candidate']);
        $this->assertSame(2, $batch->fresh()->records_seen);
        $this->assertSame(1, $batch->fresh()->records_flagged);
    }

    private function staff(string $username, string $roleName): User
    {
        $user = User::factory()->create(['username' => $username]);
        $user->roles()->attach(Role::query()->where('name', $roleName)->value('id'));

        return $user;
    }
}
