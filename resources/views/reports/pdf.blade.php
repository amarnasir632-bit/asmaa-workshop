<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: dejavusans, sans-serif; direction: rtl; font-size: 10pt; color: #24313d; }
        h1, h2, h3 { color: #1f4e79; }
        h1 { font-size: 20pt; }
        h2 { margin-top: 22px; font-size: 14pt; }
        p { margin: 5px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 0.5px solid #cbd5e1; padding: 5px; text-align: right; }
        th { background-color: #eef2f6; color: #1f4e79; }
        .muted { color: #64748b; font-size: 8pt; }
        .summary { background-color: #f7f8fa; }
    </style>
</head>
<body>
    <h1>مشغل أسماء · التقارير</h1>
    <p>الفترة: {{ $from }} إلى {{ $to }}</p>
    @if(($filters['school_id'] ?? null) || ($filters['guardian_id'] ?? null) || ($filters['status'] ?? null))
        <p class="muted">التقرير مصفّى وفق المدرسة وولي الأمر وحالة الطلب المختارة.</p>
    @endif

    @if($financial)
        <h2>الملخص المالي</h2>
        <table class="summary"><tbody>
            <tr><th>المبيعات المسجلة</th><td>{{ number_format($financial['sales'] / 100, 2) }} ج.س</td><th>عدد الطلبات</th><td>{{ number_format($financial['orders_count']) }}</td></tr>
            <tr><th>التحصيل المؤكد</th><td>{{ number_format($financial['collections'] / 100, 2) }} ج.س</td><th>النقد</th><td>{{ number_format($financial['cash_collections'] / 100, 2) }} ج.س</td></tr>
            <tr><th>التحويلات المعتمدة</th><td>{{ number_format($financial['verified_bank_collections'] / 100, 2) }} ج.س</td><th>الاستردادات المدفوعة</th><td>{{ number_format($financial['refunds'] / 100, 2) }} ج.س</td></tr>
            <tr><th>المصروفات</th><td>{{ number_format($financial['expenses'] / 100, 2) }} ج.س</td><th>الديون الحالية</th><td>{{ number_format($financial['debts'] / 100, 2) }} ج.س</td></tr>
            <tr><th>الربح التقديري</th><td colspan="3">{{ number_format($financial['estimated_profit'] / 100, 2) }} ج.س</td></tr>
        </tbody></table>
        <p class="muted">الربح مؤشر مبسط، وليس صافي ربح محاسبياً. تبقى المصروفات إجمالي الفترة لأن النظام لا يوزعها على الطلبات.</p>
        <h3>المبيعات حسب المدرسة</h3>
        <table><thead><tr><th>المدرسة / النطاق</th><th>عدد الطلبات</th><th>المبيعات</th></tr></thead><tbody>
            @foreach($financial['sales_by_school'] as $school => $row)
                <tr><td>{{ $school }}</td><td>{{ $row['orders'] }}</td><td>{{ number_format($row['sales'] / 100, 2) }} ج.س</td></tr>
            @endforeach
        </tbody></table>
    @endif

    <h2>الطلبات</h2>
    <table><thead><tr><th>رقم الطلب</th><th>العميل / المدرسة</th><th>التاريخ</th><th>النوع</th><th>الحالة التشغيلية</th><th>المبلغ</th></tr></thead><tbody>
        @forelse($orders as $order)
            <tr><td>{{ $order->number }}</td><td>{{ $order->customer_name_snapshot }}</td><td>{{ $order->created_on->format('Y-m-d') }}</td><td>{{ $order->type }}</td><td>{{ $order->operational_status }}</td><td>@if($financial){{ number_format((float) $order->approved_total, 2) }} ج.س@else—@endif</td></tr>
        @empty
            <tr><td colspan="6">لا توجد طلبات للفترة.</td></tr>
        @endforelse
    </tbody></table>
    @if($orders->count() >= 500)<p class="muted">يعرض هذا الملف أول 500 طلب من النتائج المطابقة.</p>@endif

    <h2>أعمال الترزي</h2>
    <table><thead><tr><th>الطلب</th><th>الصنف</th><th>المرحلة</th><th>أرسل</th><th>استلم</th><th>المتبقي</th></tr></thead><tbody>
        @php($hasTailorRows = false)
        @foreach($productionJobs as $job)
            @foreach($job->dispatches as $dispatch)
                @foreach($dispatch->items as $item)
                    @php($hasTailorRows = true)
                    @php($received = $item->receipts->sum('quantity_received'))
                    <tr><td>{{ $job->order->number }}</td><td>{{ $item->orderItem->description_snapshot }}</td><td>{{ $job->stage }}</td><td>{{ $item->quantity_sent }}</td><td>{{ $received }}</td><td>{{ max(0, $item->quantity_sent - $received) }}</td></tr>
                @endforeach
            @endforeach
        @endforeach
        @unless($hasTailorRows)<tr><td colspan="6">لا توجد إرساليات للترزي.</td></tr>@endunless
    </tbody></table>

    <h2>المخزون المتاح</h2>
    <table><thead><tr><th>المدرسة</th><th>الصنف</th><th>المرحلة</th><th>المقاس / اللون</th><th>المتاح</th></tr></thead><tbody>
        @forelse($inventory as $variant)
            <tr><td>{{ $variant->school->name }}</td><td>{{ $variant->garmentType->name }}</td><td>{{ $variant->grade?->name ?? '—' }}</td><td>{{ $variant->size ?? '—' }} / {{ $variant->color ?? '—' }}</td><td>{{ $variant->balance?->quantity_available ?? 0 }}</td></tr>
        @empty<tr><td colspan="5">لا توجد أصناف مخزنية.</td></tr>@endforelse
    </tbody></table>

    <h2>حركات المخزون في الفترة</h2>
    <table><thead><tr><th>التاريخ</th><th>الصنف</th><th>الحركة</th><th>التغير</th><th>السبب</th></tr></thead><tbody>
        @forelse($movements as $movement)
            <tr><td>{{ $movement->occurred_at->timezone(config('app.display_timezone'))->format('Y-m-d H:i') }}</td><td>{{ $movement->variant->school->name }} · {{ $movement->variant->garmentType->name }}</td><td>{{ $movement->movement_type }}</td><td>{{ $movement->quantity_change }}</td><td>{{ $movement->reason }}</td></tr>
        @empty<tr><td colspan="5">لا توجد حركات مخزون.</td></tr>@endforelse
    </tbody></table>
    <p class="muted">أُنشئ هذا التقرير في {{ now(config('app.display_timezone'))->format('Y-m-d H:i') }} بتوقيت الخرطوم. النتائج التشغيلية محدودة بأول 500 طلب، وحركات المخزون بآخر 100 حركة في الفترة.</p>
</body>
</html>
