@extends('layouts.app')

@section('title', 'دفعة ترحيل تاريخي')

@section('content')
    <div class="mx-auto max-w-7xl space-y-6">
        <a href="{{ route('history.index') }}" class="text-sm font-semibold text-[var(--brand-navy)]">→ كل الدفعات</a>
        <header>
            <h1 class="mt-2 text-2xl font-bold">{{ $batch->source_description }}</h1>
            <p class="mt-1 text-sm text-slate-600">تاريخ الإدخال {{ $batch->imported_on->format('Y-m-d') }} · {{ $batch->records_seen }} محاولة سجل · {{ $batch->records_flagged }} بحاجة إلى انتباه</p>
            @if($batch->notes)<p class="mt-2 text-sm">{{ $batch->notes }}</p>@endif
        </header>

        <div role="note" class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950">الأرشيف معزول عن التشغيل الحالي. «رصيد مالي افتتاحي تاريخي» و«ملاحظة مخزون تاريخية» للتوثيق فقط؛ لا تغيّر المال أو الكميات المتاحة.</div>

        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-bold">إضافة سجل من المصدر</h2>
            <form method="POST" action="{{ route('history.records.store', $batch) }}" class="mt-4 grid gap-4 md:grid-cols-2">
                @csrf
                <label class="text-sm font-semibold">نوع السجل
                    <select name="record_type" required class="mt-1 w-full rounded-lg border-slate-300">
                        @foreach($types as $key => $label)<option value="{{ $key }}" @selected(old('record_type') === $key)>{{ $label }}</option>@endforeach
                    </select>
                </label>
                <label class="text-sm font-semibold">مرجع المصدر (رقم صفحة/إيصال مثلاً)
                    <input name="source_reference" maxlength="120" value="{{ old('source_reference') }}" class="mt-1 w-full rounded-lg border-slate-300">
                </label>
                <label class="text-sm font-semibold">تاريخ السجل الأصلي إن كان واضحاً
                    <input type="date" name="original_date" value="{{ old('original_date') }}" class="mt-1 w-full rounded-lg border-slate-300">
                </label>
                <label class="text-sm font-semibold">درجة وضوح المصدر
                    <select name="confidence" required class="mt-1 w-full rounded-lg border-slate-300">
                        <option value="unverified">غير متحقق</option><option value="confirmed">واضح كما هو</option><option value="incomplete">ناقص</option><option value="conflicting">متعارض</option>
                    </select>
                </label>
                <label class="text-sm font-semibold md:col-span-2">نص السجل كما ورد في المصدر
                    <textarea name="transcription" required maxlength="50000" rows="5" class="mt-1 w-full rounded-lg border-slate-300" placeholder="أدخل النص دون افتراض القيم الغائبة">{{ old('transcription') }}</textarea>
                </label>
                <div class="md:col-span-2"><button class="rounded-lg bg-[var(--brand-navy)] px-4 py-2 font-semibold text-white">حفظ للمراجعة</button></div>
            </form>
        </section>

        @if($issues->isNotEmpty())
            <section class="rounded-xl border border-amber-300 bg-white p-5">
                <h2 class="text-lg font-bold">سجلات تحتاج انتباهاً</h2>
                <ul class="mt-3 list-inside list-disc space-y-2 text-sm">
                    @foreach($issues as $issue)<li><strong>{{ $issue->source_reference ?: 'بلا مرجع' }} · {{ $issue->issue_type }}</strong>: {{ $issue->description }}</li>@endforeach
                </ul>
            </section>
        @endif

        <section class="space-y-4">
            <h2 class="text-lg font-bold">السجلات والمراجعات</h2>
            @forelse($records as $record)
                <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-wrap justify-between gap-2">
                        <h3 class="font-bold">{{ $types[$record->record_type] ?? $record->record_type }} · {{ $record->source_reference ?: 'بلا مرجع' }}</h3>
                        <span class="text-sm text-slate-600">{{ $record->review_status }} · {{ $record->confidence }} · {{ $record->original_date?->format('Y-m-d') ?? 'بلا تاريخ' }}</span>
                    </div>
                    <p class="mt-3 whitespace-pre-wrap rounded-lg bg-slate-50 p-3 text-sm">{{ $record->transcription }}</p>
                    <p class="mt-2 text-xs text-slate-500">أُدخل بواسطة {{ $record->enterer->name }} @if($record->reviewer) · راجعه {{ $record->reviewer->name }} @endif</p>
                    @if($record->review_notes)<p class="mt-2 text-sm">ملاحظة المراجعة: {{ $record->review_notes }}</p>@endif

                    <div class="mt-4 grid gap-4 border-t border-slate-100 pt-4 lg:grid-cols-2">
                        <form method="POST" action="{{ route('history.records.review', $record) }}" class="space-y-2">
                            @csrf @method('PATCH')
                            <h4 class="font-semibold">مراجعة المدير</h4>
                            <select name="review_status" required class="w-full rounded-lg border-slate-300"><option value="reviewed">تمت المراجعة</option><option value="needs_correction">يحتاج تصحيحاً</option><option value="rejected">مرفوض من الأرشفة</option></select>
                            <textarea name="review_notes" required maxlength="5000" rows="2" placeholder="سبب ونتيجة المراجعة" class="w-full rounded-lg border-slate-300"></textarea>
                            <button class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold">حفظ المراجعة</button>
                        </form>
                        <form method="POST" action="{{ route('history.records.correct', $record) }}" class="space-y-2">
                            @csrf @method('PATCH')
                            <h4 class="font-semibold">تصحيح مع حفظ النسخة السابقة</h4>
                            <textarea name="transcription" required maxlength="50000" rows="2" placeholder="النص المصحح" class="w-full rounded-lg border-slate-300"></textarea>
                            <input name="reason" required maxlength="2000" placeholder="سبب التصحيح" class="w-full rounded-lg border-slate-300">
                            <button class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold">حفظ التصحيح وإعادته للمراجعة</button>
                        </form>
                    </div>
                    @if($record->revisions->isNotEmpty())
                        <details class="mt-4 text-sm"><summary class="cursor-pointer font-semibold">سجل النسخ والتصحيحات ({{ $record->revisions->count() }})</summary>
                            <ol class="mt-2 space-y-3">@foreach($record->revisions as $revision)<li class="rounded bg-slate-50 p-3"><p><strong>قبل:</strong> {{ $revision->previous_transcription }}</p><p><strong>بعد:</strong> {{ $revision->corrected_transcription }}</p><p class="text-slate-600">{{ $revision->reason }} · {{ $revision->corrector->name }} · {{ $revision->corrected_at->format('Y-m-d H:i') }}</p></li>@endforeach</ol>
                        </details>
                    @endif
                </article>
            @empty
                <p class="rounded-xl bg-white p-5 text-sm text-slate-600">لم تُسجل عناصر في هذه الدفعة.</p>
            @endforelse
            <div>{{ $records->links() }}</div>
        </section>
    </div>
@endsection
