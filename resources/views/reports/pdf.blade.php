<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>تقرير مشغل أسماء</title>
    <style>
        body {
            direction: rtl;
            text-align: right;
            font-family: 'dejavusans', sans-serif;
            font-size: 11px;
            color: #1e293b;
            line-height: 1.5;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            border-bottom: 2px solid #1f4e79;
            padding-bottom: 8px;
        }
        .header-table td {
            border: none;
            padding: 4px 0;
            vertical-align: middle;
        }
        h1 {
            color: #1f4e79;
            font-size: 18px;
            margin: 0 0 4px 0;
            font-weight: bold;
        }
        h2 {
            color: #1f4e79;
            font-size: 13px;
            margin: 16px 0 8px 0;
            padding-bottom: 4px;
            border-bottom: 1.5px solid #cbd5e1;
            font-weight: bold;
        }
        h3 {
            color: #334155;
            font-size: 12px;
            margin: 12px 0 6px 0;
            font-weight: bold;
        }
        p {
            margin: 3px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }
        th {
            background-color: #1f4e79;
            color: #ffffff;
            font-weight: bold;
            padding: 7px 8px;
            border: 1px solid #1f4e79;
            text-align: right;
            font-size: 10.5px;
        }
        td {
            padding: 6px 8px;
            border: 1px solid #cbd5e1;
            text-align: right;
            vertical-align: middle;
            font-size: 10px;
        }
        tr:nth-child(even) td {
            background-color: #f8fafc;
        }
        .summary-table th {
            background-color: #f1f5f9;
            color: #1e293b;
            font-weight: bold;
            border: 1px solid #cbd5e1;
            width: 25%;
        }
        .summary-table td {
            border: 1px solid #cbd5e1;
            width: 25%;
        }
        .highlight-row th, .highlight-row td {
            background-color: #fef3c7 !important;
            color: #78350f !important;
            font-weight: bold;
        }
        .text-center {
            text-align: center;
        }
        .text-left {
            text-align: left;
        }
        .muted {
            color: #64748b;
            font-size: 9px;
        }
        .notice-box {
            background-color: #fffbeb;
            border: 1px solid #fde68a;
            color: #92400e;
            padding: 8px 10px;
            font-size: 9.5px;
            margin-top: 6px;
            margin-bottom: 12px;
            border-radius: 4px;
        }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 9px;
            background-color: #f1f5f9;
            color: #334155;
        }
    </style>
</head>
<body>
@php
$statusTranslations = [
    'new' => 'طلب جديد',
    'in_progress' => 'قيد التفصيل والخياطة',
    'partially_received' => 'استلام جزئي',
    'fully_received' => 'مستلم بالكامل',
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
    'fabric_issued' => 'صرف إنتاج',
    'garment_received' => 'استلام ملابس',
    'sale' => 'صرف بيع مباشر',
    'order_delivery' => 'تسليم طلب',
    'adjustment' => 'تسوية جردية',
    'return' => 'مرتجع',
    'loss' => 'تالف / مفقود',
];
@endphp

    <table class="header-table">
        <tr>
            <td style="width: 65%;">
                <h1>مشغل أسماء · تقارير النشاط والتشغيل</h1>
                <p><strong>الفترة المحددة:</strong> من {{ $from }} إلى {{ $to }}</p>
                @if(($filters['school_id'] ?? null) || ($filters['guardian_id'] ?? null) || ($filters['status'] ?? null))
                    <p class="muted">التقرير مصفّى وفق معايير المدرسة، ولي الأمر، أو حالة الطلب المختارة.</p>
                @endif
            </td>
            <td style="width: 35%; text-align: left;" class="text-left">
                <p class="muted">تاريخ الإصدار: {{ now(config('app.display_timezone'))->format('d/m/Y - h:i A') }}</p>
                <p class="muted">النظام: مشغل أسماء للملابس المدرسية</p>
            </td>
        </tr>
    </table>

    @if($financial)
        <h2>الملخص المالي للفترة</h2>
        <table class="summary-table">
            <tbody>
                <tr>
                    <th>إجمالي المبيعات المسجلة</th>
                    <td><strong>{{ number_format($financial['sales'] / 100, 2) }} ج.س</strong></td>
                    <th>عدد الطلبات المسجلة</th>
                    <td>{{ number_format($financial['orders_count']) }} طلب</td>
                </tr>
                <tr>
                    <th>التحصيل المؤكد (كاش وبنك معتمد)</th>
                    <td><strong>{{ number_format($financial['collections'] / 100, 2) }} ج.س</strong></td>
                    <th>المقبوض نقداً</th>
                    <td>{{ number_format($financial['cash_collections'] / 100, 2) }} ج.س</td>
                </tr>
                <tr>
                    <th>التحويلات البنكية المعتمدة</th>
                    <td>{{ number_format($financial['verified_bank_collections'] / 100, 2) }} ج.س</td>
                    <th>الاستردادات المدفوعة</th>
                    <td>{{ number_format($financial['refunds'] / 100, 2) }} ج.س</td>
                </tr>
                <tr>
                    <th>إجمالي المصروفات</th>
                    <td>{{ number_format($financial['expenses'] / 100, 2) }} ج.س</td>
                    <th>إجمالي الديون الحالية</th>
                    <td><strong>{{ number_format($financial['debts'] / 100, 2) }} ج.س</strong></td>
                </tr>
                <tr class="highlight-row">
                    <th>صافي النقدية (قبل خصم تكلفة القماش والترزي)</th>
                    <td colspan="3">
                        <strong style="font-size: 13px;">{{ number_format($financial['estimated_profit'] / 100, 2) }} ج.س</strong>
                    </td>
                </tr>
            </tbody>
        </table>

        <div class="notice-box">
            <strong>تنبيه مالي:</strong> صافي النقدية = المبيعات المسجلة − الاستردادات الفعلية − المصروفات المسجلة. هذا مؤشر تشغيلي مبسط لحركة السيولة وليس صافي ربح محاسبياً؛ ولا يشمل تكلفة شراء أمتار القماش ولا أجور الخياطة والترزية المباشرة. عند تحديد مدرسة أو عميل تبقى المصروفات إجمالي الفترة لعدم تخصيصها لكل طلب.
        </div>

        <h3>المبيعات حسب المدرسة والفترة</h3>
        <table>
            <thead>
                <tr>
                    <th>المدرسة / النطاق</th>
                    <th style="width: 120px; text-align: center;">عدد الطلبات</th>
                    <th style="width: 180px;">إجمالي المبيعات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($financial['sales_by_school'] as $school => $row)
                    <tr>
                        <td><strong>{{ $school }}</strong></td>
                        <td class="text-center">{{ $row['orders'] }}</td>
                        <td><strong>{{ number_format($row['sales'] / 100, 2) }} ج.س</strong></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="text-center muted">لا توجد مبيعات في الفترة المحددة.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    @endif

    <h2>سجل الطلبات المسجلة</h2>
    <table>
        <thead>
            <tr>
                <th style="width: 80px;">رقم الطلب</th>
                <th>العميل / المدرسة</th>
                <th style="width: 75px; text-align: center;">التاريخ</th>
                <th style="width: 55px; text-align: center;">النوع</th>
                <th style="width: 105px; text-align: center;">الحالة التشغيلية</th>
                <th style="width: 95px;">المبلغ</th>
            </tr>
        </thead>
        <tbody>
            @forelse($orders as $order)
                <tr>
                    <td><strong>{{ $order->number }}</strong></td>
                    <td>{{ $order->customer_name_snapshot }}</td>
                    <td class="text-center">{{ $order->created_on->format('d/m/Y') }}</td>
                    <td class="text-center">{{ $order->type === 'custom' ? 'تفصيل' : 'جاهز' }}</td>
                    <td class="text-center">
                        <span class="badge">{{ $statusTranslations[$order->operational_status] ?? $order->operational_status }}</span>
                    </td>
                    <td>@if($financial)<strong>{{ number_format((float) $order->approved_total, 2) }} ج.س</strong>@else—@endif</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center muted">لا توجد طلبات مسجلة في الفترة المختارة.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    @if($orders->count() >= 500)
        <p class="muted">يعرض هذا المستند أول 500 طلب مطابقة لمعايير البحث.</p>
    @endif

    <h2>أعمال الترزي والإرساليات</h2>
    <table>
        <thead>
            <tr>
                <th style="width: 85px;">الطلب</th>
                <th>الصنف / البيان</th>
                <th style="width: 95px; text-align: center;">المرحلة</th>
                <th style="width: 60px; text-align: center;">أُرسل</th>
                <th style="width: 60px; text-align: center;">استُلم</th>
                <th style="width: 65px; text-align: center;">المتبقي</th>
            </tr>
        </thead>
        <tbody>
            @php($hasTailorRows = false)
            @foreach($productionJobs as $job)
                @foreach($job->dispatches as $dispatch)
                    @foreach($dispatch->items as $item)
                        @php($hasTailorRows = true)
                        @php($received = $item->receipts->sum('quantity_received'))
                        <tr>
                            <td>{{ $job->order?->number ?? '—' }}</td>
                            <td>{{ $item->orderItem?->description_snapshot ?? $job->orderItem?->description_snapshot ?? '—' }}</td>
                            <td class="text-center">{{ $stageTranslations[$job->stage] ?? $job->stage }}</td>
                            <td class="text-center">{{ $item->quantity_sent }}</td>
                            <td class="text-center" style="color: #047857;">{{ $received }}</td>
                            <td class="text-center" style="color: {{ ($item->quantity_sent - $received) > 0 ? '#b45309' : '#334155' }};">
                                <strong>{{ max(0, $item->quantity_sent - $received) }}</strong>
                            </td>
                        </tr>
                    @endforeach
                @endforeach
            @endforeach
            @unless($hasTailorRows)
                <tr>
                    <td colspan="6" class="text-center muted">لا توجد إرساليات للترزي في طلبات هذه الفترة.</td>
                </tr>
            @endunless
        </tbody>
    </table>

    <h2>المخزون المتاح حالياً</h2>
    <table>
        <thead>
            <tr>
                <th>المدرسة</th>
                <th>الصنف</th>
                <th>المرحلة</th>
                <th>المقاس / اللون</th>
                <th style="width: 75px; text-align: center;">المتاح</th>
            </tr>
        </thead>
        <tbody>
            @forelse($inventory as $variant)
                <tr>
                    <td><strong>{{ $variant->school?->name ?? 'عام / بلا مدرسة' }}</strong></td>
                    <td>{{ $variant->garmentType?->name ?? '—' }}</td>
                    <td>{{ $variant->grade?->name ?? '—' }}</td>
                    <td>{{ $variant->size ?? '—' }} / {{ $variant->color ?? '—' }}</td>
                    <td class="text-center"><strong>{{ $variant->balance?->quantity_available ?? 0 }}</strong></td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center muted">لا توجد أصناف مخزنية.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <h2>حركات المخزون في الفترة</h2>
    <table>
        <thead>
            <tr>
                <th style="width: 105px;">التاريخ</th>
                <th>الصنف</th>
                <th style="width: 100px; text-align: center;">نوع الحركة</th>
                <th style="width: 60px; text-align: center;">التغير</th>
                <th>السبب / البيان</th>
            </tr>
        </thead>
        <tbody>
            @forelse($movements as $movement)
                <tr>
                    <td>{{ $movement->occurred_at?->timezone(config('app.display_timezone'))?->format('d/m/Y H:i') ?? '—' }}</td>
                    <td>{{ $movement->variant?->school?->name ?? 'عام / بلا مدرسة' }} · {{ $movement->variant?->garmentType?->name ?? '—' }}</td>
                    <td class="text-center">{{ $movementTranslations[$movement->movement_type] ?? $movement->movement_type }}</td>
                    <td class="text-center" style="font-weight: bold; color: {{ $movement->quantity_change > 0 ? '#047857' : '#b91c1c' }};">
                        {{ $movement->quantity_change > 0 ? '+'.$movement->quantity_change : $movement->quantity_change }}
                    </td>
                    <td>{{ $movement->reason ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center muted">لا توجد حركات مخزون مسجلة في الفترة.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <p class="muted" style="margin-top: 15px; border-top: 1px dashed #cbd5e1; padding-top: 6px;">
        أُنشئ هذا التقرير آلياً عبر نظام مشغل أسماء في {{ now(config('app.display_timezone'))->format('d/m/Y H:i') }} بتوقيت الخرطوم. النتائج التشغيلية محدودة بأول 500 طلب، وحركات المخزون بآخر 100 حركة في الفترة.
    </p>
</body>
</html>
