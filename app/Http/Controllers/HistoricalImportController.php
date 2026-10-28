<?php

namespace App\Http\Controllers;

use App\Models\HistoricalImportBatch;
use App\Models\HistoricalImportRecord;
use App\Models\HistoricalRecordIssue;
use App\Models\HistoricalRecordRevision;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class HistoricalImportController extends Controller
{
    public function index(): View
    {
        Gate::authorize('history.manage');

        return view('history.index', [
            'batches' => HistoricalImportBatch::query()->with('importer')->withCount(['records', 'issues'])->latest()->paginate(15),
            'types' => $this->types(),
        ]);
    }

    public function show(HistoricalImportBatch $batch): View
    {
        Gate::authorize('history.manage');
        $batch->load('importer');

        return view('history.show', [
            'batch' => $batch,
            'records' => $batch->records()->with(['enterer', 'reviewer', 'revisions.corrector'])->latest()->paginate(20),
            'issues' => $batch->issues()->with('resolver')->latest()->get(),
            'types' => $this->types(),
        ]);
    }

    public function storeBatch(Request $request): RedirectResponse
    {
        Gate::authorize('history.manage');
        $data = $request->validate([
            'source_description' => ['required', 'string', 'max:180'],
            'imported_on' => ['required', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $batch = HistoricalImportBatch::create([...$data, 'imported_by' => $request->user()->id]);
        app(AuditLogger::class)->record('history.batch_created', $batch, $batch->id, ['source_description', 'imported_on']);

        return redirect()->route('history.show', $batch)->with('status', 'أُنشئت دفعة الترحيل. أضف السجلات المنقولة من المصدر.');
    }

    public function storeRecord(Request $request, HistoricalImportBatch $batch): RedirectResponse
    {
        Gate::authorize('history.manage');
        $data = $request->validate([
            'record_type' => ['required', Rule::in(array_keys($this->types()))],
            'source_reference' => ['nullable', 'string', 'max:120'],
            'original_date' => ['nullable', 'date_format:Y-m-d'],
            'transcription' => ['required', 'string', 'max:50000'],
            'confidence' => ['required', Rule::in(['confirmed', 'incomplete', 'conflicting', 'unverified'])],
        ]);
        $fingerprint = $this->fingerprint($data);

        DB::transaction(function () use ($request, $batch, $data, $fingerprint): void {
            $batch->increment('records_seen');
            $existing = HistoricalImportRecord::query()->where('fingerprint', $fingerprint)->first();
            if ($existing) {
                $batch->increment('records_flagged');
                HistoricalRecordIssue::create([
                    'historical_import_batch_id' => $batch->id,
                    'record_type' => $data['record_type'],
                    'source_reference' => $data['source_reference'] ?? null,
                    'issue_type' => 'duplicate_candidate',
                    'description' => 'تطابق نص السجل وبيانات مصدره مع سجل مرحّل سابقاً؛ راجع التشابه قبل إضافة نسخة أخرى.',
                ]);
                app(AuditLogger::class)->record('history.duplicate_flagged', HistoricalRecordIssue::class, null, ['source_reference']);

                return;
            }

            $record = $batch->records()->create([
                ...$data,
                'fingerprint' => $fingerprint,
                'review_status' => 'pending',
                'entered_by' => $request->user()->id,
            ]);
            if ($data['confidence'] !== 'confirmed') {
                $batch->increment('records_flagged');
                HistoricalRecordIssue::create([
                    'historical_import_batch_id' => $batch->id,
                    'record_type' => $data['record_type'],
                    'source_reference' => $data['source_reference'] ?? null,
                    'issue_type' => $data['confidence'] === 'conflicting' ? 'conflicting' : ($data['confidence'] === 'incomplete' ? 'missing' : 'unverified'),
                    'description' => 'السجل يحتاج مراجعة المدير قبل اعتماده في الأرشيف التاريخي.',
                ]);
            }
            app(AuditLogger::class)->record('history.record_added', $record, $record->id, ['record_type', 'source_reference', 'original_date', 'confidence']);
        });

        return back()->with('status', 'عولج السجل. راجع قائمة المشكلات إن كان مكرراً أو غير مؤكد.');
    }

    public function review(Request $request, HistoricalImportRecord $record): RedirectResponse
    {
        Gate::authorize('history.manage');
        $data = $request->validate([
            'review_status' => ['required', Rule::in(['reviewed', 'needs_correction', 'rejected'])],
            'review_notes' => ['required', 'string', 'max:5000'],
        ]);
        DB::transaction(function () use ($request, $record, $data): void {
            $record->update([...$data, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
            app(AuditLogger::class)->record('history.record_reviewed', $record, $record->id, ['review_status', 'review_notes'], $data['review_notes']);
        });

        return back()->with('status', 'حُفظت نتيجة المراجعة في سجل التدقيق.');
    }

    public function correct(Request $request, HistoricalImportRecord $record): RedirectResponse
    {
        Gate::authorize('history.manage');
        $data = $request->validate([
            'transcription' => ['required', 'string', 'max:50000'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);
        DB::transaction(function () use ($request, $record, $data): void {
            $newFingerprint = $this->fingerprint([
                'record_type' => $record->record_type,
                'source_reference' => $record->source_reference,
                'original_date' => $record->original_date?->toDateString(),
                'transcription' => $data['transcription'],
            ]);
            if (HistoricalImportRecord::query()->where('fingerprint', $newFingerprint)->whereKeyNot($record->id)->exists()) {
                throw ValidationException::withMessages(['transcription' => 'يوجد سجل مطابق؛ لم يُحفظ التصحيح.']);
            }
            HistoricalRecordRevision::create([
                'historical_import_record_id' => $record->id,
                'previous_transcription' => $record->transcription,
                'corrected_transcription' => $data['transcription'],
                'reason' => $data['reason'],
                'corrected_by' => $request->user()->id,
                'corrected_at' => now(),
            ]);
            $record->update([
                'transcription' => $data['transcription'],
                'fingerprint' => $newFingerprint,
                'confidence' => 'unverified',
                'review_status' => 'pending',
                'reviewed_by' => null,
                'reviewed_at' => null,
                'review_notes' => null,
            ]);
            app(AuditLogger::class)->record('history.record_corrected', $record, $record->id, ['transcription', 'fingerprint', 'review_status'], $data['reason']);
        });

        return back()->with('status', 'أُسند التصحيح الجديد للمراجعة، وحُفظ النص السابق وسَبب التعديل.');
    }

    private function fingerprint(array $data): string
    {
        $transcription = preg_replace('/\s+/u', ' ', trim($data['transcription'])) ?? trim($data['transcription']);

        return hash('sha256', implode('|', [
            $data['record_type'],
            trim((string) ($data['source_reference'] ?? '')),
            $data['original_date'] ?? '',
            $transcription,
        ]));
    }

    private function types(): array
    {
        return [
            'customer' => 'عميل / طفل',
            'child_measurements' => 'مقاسات تاريخية',
            'order' => 'طلب',
            'payment' => 'دفعة',
            'refund' => 'استرداد',
            'expense' => 'مصروف',
            'inventory_observation' => 'ملاحظة مخزون تاريخية',
            'financial_balance' => 'رصيد مالي افتتاحي تاريخي',
        ];
    }
}
