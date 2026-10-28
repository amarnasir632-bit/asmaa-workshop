@extends('layouts.app')
@section('title', 'التقارير')
@section('content')
<style>
@media print {
    .no-print, aside, nav { display: none !important; }
    body, html { background: #fff !important; }
    main { padding: 0 !important; }
    .print-report { max-width: none !important; padding: 0 !important; }
    .report-card { break-inside: avoid; box-shadow: none !important; border-color: #bbb !important; }
    table { font-size: 10pt !important; }
    thead { display: table-header-group; }
}
</style>

@php
$statusTranslations = [
    'new' => 'طلب جديد',
    'in_progress' => 'قيد التفصيل والخياطة',
    'partially_received' => 'استلام جزئي من الترزي',
    'fully_received' => 'مستلم بالكامل من الترزي',
    'ready' => 'جاهز للتسليم',
    'delivered' => 'تم التسليم',
    'cancelled' => 'ملغي',
];

$stageTranslations = [
    'not_started' => 'لم يبدأ',
    'sewing' => 'قيد الخياطة',
    'with_tailor' => 'لدى الترزي',
    'waiting_completion' => 'بانتظار الإكمال',
    'completed' => 'مكتمل',
];

$movementTranslations = [
    'initial_stock' => 'رصيد افتتاحي',
    'purchase' => 'توريد / شراء',
    'fabric_issued' => 'صرف للإنتاج',
    'garment_received' => 'استلام ملابس جاهزة',
    'sale' => 'صرف بيع مباشر',
    'order_delivery' => 'تسليم طلب',
    'adjustment' => 'تسوية جردية',
    'return' => 'مرتجع',
    'loss' => 'تالف / مفقود',
];
@endphp

<div class="print-report mx-auto max-w-7xl space-y-6">
    <header class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-sm font-semibold text-[var(--brand-gold)]">مشغل أسماء للملابس المدرسية</p>
            <h1 class="text-2xl font-bold text-[var(--brand-navy)]">تقارير النشاط والتشغيل</h1>
            <p class="mt-1 text-xs text-slate-600 sm:text-sm">الفترة من {{ $from }} إلى {{ $to }}</p>
        </div>
        <div class="no-print flex flex-wrap gap-2">
            <a class="inline-flex min-h-[40px] items-center rounded-lg bg-[var(--brand-navy)] px-4 py-2 text-xs font-bold text-white shadow hover:opacity-95" href="{{ route('reports.pdf', request()->query()) }}">
                <svg class="ml-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                تنزيل PDF
            </a>
            <a class="inline-flex min-h-[40px] items-center rounded-lg border border-[var(--brand-navy)] bg-white px-4 py-2 text-xs font-bold text-[var(--brand-navy)] hover:bg-slate-50" href="{{ route('reports.tailor', request()->query()) }}">
                تقرير الترزية
            </a>
            <button class="inline-flex min-h-[40px] items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50" onclick="window.print()">
                <svg class="ml-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                طباعة
            </button>
        </div>
    </header>

    <!-- شريط التصفية والبحث -->
    <form class="no-print grid gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-5">
        <div>
            <label class="mb-1 block text-xs font-semibold text-slate-700">من تاريخ</label>
            <input type="date" name="from" value="{{ $from }}" class="w-full rounded-lg border border-slate-300 p-2 text-xs">
        </div>
        <div>
            <label class="mb-1 block text-xs font-semibold text-slate-700">إلى تاريخ</label>
            <input type="date" name="to" value="{{ $to }}" class="w-full rounded-lg border border-slate-300 p-2 text-xs">
        </div>
        <div>
            <label class="mb-1 block text-xs font-semibold text-slate-700">المدرسة</label>
            <select name="school_id" class="w-full rounded-lg border border-slate-300 p-2 text-xs">
                <option value="">كل المدارس</option>
                @foreach($schools as $school)
                    <option value="{{ $school->id }}" @selected(($filters['school_id'] ?? null) == $school->id)>{{ $school->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs font-semibold text-slate-700">ولي الأمر</label>
            <select name="guardian_id" class="w-full rounded-lg border border-slate-300 p-2 text-xs">
                <option value="">كل أولياء الأمور</option>
                @foreach($guardians as $guardian)
                    <option value="{{ $guardian->id }}" @selected(($filters['guardian_id'] ?? null) == $guardian->id)>{{ $guardian->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs font-semibold text-slate-700">حالة الطلب</label>
            <select name="status" class="w-full rounded-lg border border-slate-300 p-2 text-xs">
                <option value="">كل الحالات</option>
                @foreach(['new'=>'طلب جديد','in_progress'=>'قيد التفصيل والخياطة','partially_received'=>'استلام جزئي','fully_received'=>'مستلم بالكامل من الترزي','ready'=>'جاهز للتسليم','delivered'=>'تم التسليم','cancelled'=>'ملغي'] as $value=>$label)
                    <option value="{{ $value }}" @selected(($filters['status'] ?? null) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="sm:col-span-2 lg:col-span-5 flex justify-end">
            <button class="inline-flex min-h-[40px] items-center rounded-lg bg-[var(--brand-navy)] px-5 py-2 text-xs font-bold text-white hover:opacity-95">
                تطبيق التصفية
            </button>
        </div>
    </form>

    @can('reports.financial')
        <section class="report-card rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-4 text-lg font-bold text-[var(--brand-navy)]">الملخص المالي للفترة</h2>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs font-medium text-slate-600">إجمالي المبيعات المسجلة</p>
                    <p class="mt-1 text-xl font-bold text-slate-900">{{ number_format($financial['sales']/100, 2) }} <span class="text-xs font-normal">ج.س</span></p>
                    <p class="mt-1 text-xs text-[var(--brand-muted)]">إجمالي الطلبات: {{ number_format($financial['orders_count']) }} طلب</p>
                </article>

                <article class="rounded-xl border border-emerald-200 bg-emerald-50/60 p-4">
                    <p class="text-xs font-medium text-emerald-800">التحصيل المؤكد (كاش وبنك معتمد)</p>
                    <p class="mt-1 text-xl font-bold text-emerald-950">{{ number_format($financial['collections']/100, 2) }} <span class="text-xs font-normal">ج.س</span></p>
                    <p class="mt-1 text-xs text-emerald-800">كاش: {{ number_format($financial['cash_collections']/100, 2) }} · بنك معتمد: {{ number_format($financial['verified_bank_collections']/100, 2) }}</p>
                </article>

                <article class="rounded-xl border border-rose-200 bg-rose-50/60 p-4">
                    <p class="text-xs font-medium text-rose-800">المصروفات التشغيلية</p>
                    <p class="mt-1 text-xl font-bold text-rose-950">{{ number_format($financial['expenses']/100, 2) }} <span class="text-xs font-normal">ج.س</span></p>
                    <p class="mt-1 text-xs text-rose-700">المسجلة خلال الفترة</p>
                </article>

                <article class="rounded-xl border border-amber-200 bg-amber-50/60 p-4">
                    <p class="text-xs font-medium text-amber-800">إجمالي الديون الحالية المتبقية</p>
                    <p class="mt-1 text-xl font-bold text-amber-950">{{ number_format($financial['debts']/100, 2) }} <span class="text-xs font-normal">ج.س</span></p>
                    <p class="mt-1 text-xs text-amber-700">مستحقات غير محصلة</p>
                </article>
            </div>

            <!-- بطاقة صافي النقدية مع التنبيه المالي الواضح وإزالة كود FR-080 -->
            <div class="mt-5 rounded-xl border border-amber-300 bg-amber-50/70 p-4">
                <div class="flex items-center gap-2">
                    <span class="inline-flex rounded-full bg-amber-200 p-1 text-amber-900">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </span>
                    <h3 class="text-sm font-bold text-amber-950">صافي النقدية (قبل خصم تكلفة القماش والترزي)</h3>
                </div>
                <p class="mt-1 text-xs text-slate-700">
                    المعادلة: المبيعات المسجلة حسب تاريخ الطلب − الاستردادات المدفوعة فعلياً في الفترة − المصروفات المسجلة في الفترة.
                </p>
                <p class="mt-2 text-2xl font-bold text-slate-900">
                    {{ number_format($financial['estimated_profit']/100, 2) }} <span class="text-sm font-semibold">ج.س</span>
                </p>
                <div class="mt-3 rounded-lg border border-amber-200 bg-white/80 p-3 text-xs leading-relaxed text-amber-900">
                    <strong>تنبيه مالي مهم:</strong> هذا المبلغ يمثل مؤشراً مبسطاً لحركة النقدية وليس صافي الربح المحاسبي النهائي؛ إذ لا يشمل تكلفة شراء أمتار القماش ولا أجور الترزية المباشرة. عند اختيار مدرسة أو ولي أمر أو حالة، تظل المصروفات هي إجمالي مصروفات الفترة كاملة لعدم ربط المصروفات بطلب فردي محدد.
                </div>
                <p class="mt-2 text-xs text-slate-600">
                    الاستردادات الفعلية المدفوعة في الفترة: {{ number_format($financial['refunds']/100, 2) }} ج.س. إشعارات البنك المعلقة غير محسوبة ضمن التحصيل المؤكد.
                </p>
            </div>

            <h3 class="mt-6 text-base font-bold text-[var(--brand-navy)]">المبيعات حسب المدرسة والفترة</h3>
            <div class="mt-2 overflow-x-auto rounded-lg border border-slate-200">
                <table class="w-full text-right text-xs sm:text-sm">
                    <thead class="bg-slate-50 font-semibold text-slate-700">
                        <tr class="border-b">
                            <th class="p-3">المدرسة / النطاق</th>
                            <th class="p-3">عدد الطلبات</th>
                            <th class="p-3">إجمالي المبيعات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($financial['sales_by_school'] as $school=>$row)
                            <tr class="hover:bg-slate-50/60">
                                <td class="p-3 font-semibold">{{ $school }}</td>
                                <td class="p-3">{{ $row['orders'] }}</td>
                                <td class="p-3 font-bold text-[var(--brand-navy)]">{{ number_format($row['sales']/100, 2) }} ج.س</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="p-4 text-center text-slate-500">لا توجد مبيعات في الفترة المختارة.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endcan

    <!-- جدول الطلبات المسجلة -->
    <section class="report-card rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="mb-3 text-lg font-bold text-[var(--brand-navy)]">الطلبات المسجلة حسب الحالة</h2>
        
        <div class="flex flex-wrap gap-2">
            @foreach($orderStates as $state=>$count)
                <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                    <span>{{ $statusTranslations[$state] ?? $state }}:</span>
                    <strong class="text-slate-900">{{ $count }}</strong>
                </span>
            @endforeach
        </div>

        <div class="mt-4 overflow-x-auto rounded-lg border border-slate-200">
            <table class="w-full text-right text-xs sm:text-sm">
                <thead class="bg-slate-50 font-semibold text-slate-700">
                    <tr class="border-b">
                        <th class="p-3">رقم الطلب</th>
                        <th class="p-3">العميل / المدرسة</th>
                        <th class="p-3">تاريخ الطلب</th>
                        <th class="p-3">الحالة التشغيلية</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($orders as $order)
                        <tr class="hover:bg-slate-50/60">
                            <td class="p-3 font-bold text-[var(--brand-navy)]">
                                <a href="{{ route('orders.show', $order) }}" class="underline">{{ $order->number }}</a>
                            </td>
                            <td class="p-3">{{ $order->customer_name_snapshot }}</td>
                            <td class="p-3">{{ $order->created_on->format('d/m/Y') }}</td>
                            <td class="p-3">
                                <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-800">
                                    {{ $statusTranslations[$order->operational_status] ?? $order->operational_status }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="p-4 text-center text-slate-500">لا توجد طلبات في الفترة المختارة.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $orders->links() }}</div>
    </section>

    <!-- أعمال الترزي -->
    <section class="report-card rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="mb-3 text-lg font-bold text-[var(--brand-navy)]">أعمال الترزي والإرساليات</h2>
        <div class="overflow-x-auto rounded-lg border border-slate-200">
            <table class="w-full text-right text-xs sm:text-sm">
                <thead class="bg-slate-50 font-semibold text-slate-700">
                    <tr class="border-b">
                        <th class="p-3">رقم الطلب</th>
                        <th class="p-3">الصنف</th>
                        <th class="p-3">المرحلة</th>
                        <th class="p-3">الكمية المرسلة</th>
                        <th class="p-3">المستلم</th>
                        <th class="p-3">المتبقي لدى الترزي</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @php($hasTailorRows = false)
                    @foreach($productionJobs as $job)
                        @foreach($job->dispatches as $dispatch)
                            @foreach($dispatch->items as $dispatchItem)
                                @php($hasTailorRows = true)
                                @php($received = $dispatchItem->receipts->sum('quantity_received'))
                                <tr class="hover:bg-slate-50/60">
                                    <td class="p-3 font-semibold">{{ $job->order?->number ?? '—' }}</td>
                                    <td class="p-3">{{ $dispatchItem->orderItem?->description_snapshot ?? $job->orderItem?->description_snapshot ?? '—' }}</td>
                                    <td class="p-3">
                                        <span class="rounded bg-slate-100 px-2 py-0.5 text-xs">
                                            {{ $stageTranslations[$job->stage] ?? $job->stage }}
                                        </span>
                                    </td>
                                    <td class="p-3 font-semibold">{{ $dispatchItem->quantity_sent }}</td>
                                    <td class="p-3 text-emerald-700 font-semibold">{{ $received }}</td>
                                    <td class="p-3 font-bold {{ ($dispatchItem->quantity_sent - $received) > 0 ? 'text-amber-700' : 'text-slate-500' }}">
                                        {{ max(0, $dispatchItem->quantity_sent - $received) }}
                                    </td>
                                </tr>
                            @endforeach
                        @endforeach
                    @endforeach
                    @unless($hasTailorRows)
                        <tr>
                            <td colspan="6" class="p-4 text-center text-slate-500">لا توجد إرساليات للترزي في طلبات هذه الفترة.</td>
                        </tr>
                    @endunless
                </tbody>
            </table>
        </div>
    </section>

    <!-- المخزون المتاح -->
    <section class="report-card rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="mb-3 text-lg font-bold text-[var(--brand-navy)]">المخزون المتاح حالياً</h2>
        <div class="overflow-x-auto rounded-lg border border-slate-200">
            <table class="w-full text-right text-xs sm:text-sm">
                <thead class="bg-slate-50 font-semibold text-slate-700">
                    <tr class="border-b">
                        <th class="p-3">المدرسة</th>
                        <th class="p-3">الصنف</th>
                        <th class="p-3">المرحلة</th>
                        <th class="p-3">المقاس / اللون</th>
                        <th class="p-3">الرصيد المتاح</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($inventory as $variant)
                        <tr class="hover:bg-slate-50/60">
                            <td class="p-3 font-semibold">{{ $variant->school?->name ?? 'عام / بلا مدرسة' }}</td>
                            <td class="p-3">{{ $variant->garmentType?->name ?? '—' }}</td>
                            <td class="p-3">{{ $variant->grade?->name ?? '—' }}</td>
                            <td class="p-3">{{ $variant->size ?? '—' }} / {{ $variant->color ?? '—' }}</td>
                            <td class="p-3 font-bold text-[var(--brand-navy)]">{{ $variant->balance?->quantity_available ?? 0 }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-4 text-center text-slate-500">لا توجد أصناف في المخزون.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <!-- حركات المخزون -->
    <section class="report-card rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="mb-3 text-lg font-bold text-[var(--brand-navy)]">حركات المخزون في الفترة (آخر 100 حركة)</h2>
        <div class="overflow-x-auto rounded-lg border border-slate-200">
            <table class="w-full text-right text-xs sm:text-sm">
                <thead class="bg-slate-50 font-semibold text-slate-700">
                    <tr class="border-b">
                        <th class="p-3">التاريخ والوقت</th>
                        <th class="p-3">الصنف</th>
                        <th class="p-3">نوع الحركة</th>
                        <th class="p-3">التغير</th>
                        <th class="p-3">السبب</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($movements as $movement)
                        <tr class="hover:bg-slate-50/60">
                            <td class="p-3 text-slate-600">
                                {{ $movement->occurred_at?->timezone(config('app.display_timezone'))?->format('d/m/Y H:i') ?? '—' }}
                            </td>
                            <td class="p-3 font-semibold">
                                {{ $movement->variant?->school?->name ?? 'عام / بلا مدرسة' }} · {{ $movement->variant?->garmentType?->name ?? '—' }}
                            </td>
                            <td class="p-3">
                                <span class="rounded bg-slate-100 px-2 py-0.5 text-xs font-semibold">
                                    {{ $movementTranslations[$movement->movement_type] ?? $movement->movement_type }}
                                </span>
                            </td>
                            <td class="p-3 font-bold {{ $movement->quantity_change > 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                                {{ $movement->quantity_change > 0 ? '+'.$movement->quantity_change : $movement->quantity_change }}
                            </td>
                            <td class="p-3 text-slate-700">{{ $movement->reason ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-4 text-center text-slate-500">لا توجد حركات مخزنية في الفترة.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <p class="text-xs text-slate-500">يعرض هذا التقرير البيانات المسجلة حتى لحظة الاستعراض. للطباعة أو التصدير يمكن استخدام أزرار أعلى الصفحة.</p>
</div>
@endsection
