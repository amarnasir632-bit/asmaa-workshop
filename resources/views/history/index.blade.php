@extends('layouts.app')

@section('title', 'الترحيل التاريخي')

@section('content')
    <div class="mx-auto max-w-6xl space-y-6">
        <header>
            <h1 class="text-2xl font-bold">ترحيل السجلات التاريخية</h1>
            <p class="mt-1 text-sm text-slate-600">سجّل ما في الدفاتر أو الملفات كما هو، مع مرجعه وتاريخه، ثم راجعه وصححه بأثر تدقيق.</p>
        </header>

        <div role="note" class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950">
            هذه مساحة أرشفة ومراجعة منفصلة. السجلات هنا لا تنشئ طلبات أو دفعات أو حركات مخزون، ولا تدخل في أرصدة وتقارير التشغيل. لا تستنتج أو تخمّن القيم الناقصة.
        </div>

        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-bold">إنشاء دفعة مصدر</h2>
            <form method="POST" action="{{ route('history.batches.store') }}" class="mt-4 grid gap-4 md:grid-cols-3">
                @csrf
                <label class="text-sm font-semibold">وصف المصدر
                    <input name="source_description" required maxlength="180" value="{{ old('source_description') }}" placeholder="مثال: دفتر الطلبات الورقي، المجلد 2" class="mt-1 w-full rounded-lg border-slate-300">
                </label>
                <label class="text-sm font-semibold">تاريخ إدخال الدفعة
                    <input type="date" name="imported_on" required value="{{ old('imported_on', now(config('app.display_timezone'))->toDateString()) }}" class="mt-1 w-full rounded-lg border-slate-300">
                </label>
                <label class="text-sm font-semibold">ملاحظات
                    <input name="notes" maxlength="5000" value="{{ old('notes') }}" class="mt-1 w-full rounded-lg border-slate-300">
                </label>
                <div class="md:col-span-3"><button class="rounded-lg bg-[var(--brand-navy)] px-4 py-2 font-semibold text-white">إنشاء الدفعة</button></div>
            </form>
        </section>

        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <h2 class="border-b border-slate-200 px-5 py-4 text-lg font-bold">دفعات الترحيل</h2>
            <div class="divide-y divide-slate-100">
                @forelse($batches as $batch)
                    <a href="{{ route('history.show', $batch) }}" class="block p-5 hover:bg-slate-50">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="font-semibold">{{ $batch->source_description }}</span>
                            <span class="text-sm text-slate-600">{{ $batch->imported_on->format('Y-m-d') }}</span>
                        </div>
                        <p class="mt-2 text-sm text-slate-600">{{ $batch->records_count }} سجل · {{ $batch->issues_count }} مسألة مراجعة · أُدخلت بواسطة {{ $batch->importer->name }}</p>
                    </a>
                @empty
                    <p class="p-5 text-sm text-slate-600">لا توجد دفعات بعد.</p>
                @endforelse
            </div>
            <div class="p-4">{{ $batches->links() }}</div>
        </section>
    </div>
@endsection
