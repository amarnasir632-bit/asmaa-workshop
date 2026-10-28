<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>كشف حساب طلب</title>
    <style>
        body { font-family: dejavusans, sans-serif; direction: rtl; font-size: 9.5pt; color: #1e293b; line-height: 1.4; }
        h1, h2, h3 { color: #1f4e79; }
        h1 { font-size: 15pt; margin-bottom: 3px; font-weight: bold; }
        h2 { margin-top: 12px; margin-bottom: 5px; font-size: 11pt; font-weight: bold; border-bottom: 1px solid #cbd5e1; padding-bottom: 3px; }
        p { margin: 2px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 5px; margin-bottom: 12px; }
        th, td { border: 1px solid #cbd5e1; padding: 5px 7px; text-align: right; font-size: 9pt; }
        th { background-color: #1f4e79; color: #ffffff; font-weight: bold; }
        tr:nth-child(even) td { background-color: #f8fafc; }
        .summary th { background-color: #f1f5f9; color: #1e293b; font-weight: bold; width: 25%; }
        .summary td { width: 25%; }
        .text-center { text-align: center; }
        .text-left { text-align: left; }
    </style>
</head>
<body>
    <h1>مشغل أسماء · كشف حساب وموقف توريد وتسليم</h1>
    <p><b>طلب رقم:</b> #{{ $order->number }} | <b>التاريخ:</b> {{ $order->created_on->format('Y-m-d') }}</p>
    <p><b>المدرسة / العميل:</b> {{ $order->customer_name_snapshot }} @if($order->schoolContract?->contact_name) | <b>المندوب:</b> {{ $order->schoolContract->contact_name }} @endif</p>
    @if($order->schoolContract?->contract_number)<p><b>رقم العقد:</b> {{ $order->schoolContract->contract_number }}</p>@endif

    <h2>ملخص الموقف الشامل (المطلوب، المسلم، والمدفوع)</h2>
    @php($totalOrdered = (int) $order->items->sum('quantity'))
    @php($totalDelivered = (int) $order->items->sum('delivered_quantity'))
    @php($remainingDelivery = max(0, $totalOrdered - $totalDelivered))
    @php($summary = $order->financial_summary ?? ['net_paid' => 0, 'due' => (int) round(((float) $order->approved_total) * 100)])
    <table class="summary">
        <tbody>
            <tr>
                <th>إجمالي اللبسات المطلوبة</th><td>{{ $totalOrdered }} لبسة</td>
                <th>قيمة العقد الإجمالية</th><td>{{ number_format((float)$order->approved_total, 2) }} ج.س</td>
            </tr>
            <tr>
                <th>الكمية المسلّمة (استلموا)</th><td>{{ $totalDelivered }} لبسة</td>
                <th>المبلغ المدفوع (دفعوا)</th><td>{{ number_format($summary['net_paid'] / 100, 2) }} ج.س</td>
            </tr>
            <tr>
                <th>المتبقي للتسليم (ما استلموا)</th><td>{{ $remainingDelivery }} لبسة</td>
                <th>الرصيد المتبقي مستحق السداد</th><td><b>{{ number_format($summary['due'] / 100, 2) }} ج.س</b></td>
            </tr>
        </tbody>
    </table>

    <h2>تفاصيل اللبسات والأصناف وموقف التسليم</h2>
    <table>
        <thead>
            <tr>
                <th>المرحلة / الدفعة</th>
                <th>الصنف / اللبسة</th>
                <th class="text-center">المطلوب</th>
                <th class="text-center">المسلّم (استلموا)</th>
                <th class="text-center">المتبقي (ما استلموا)</th>
                <th class="text-left">سعر الوحدة</th>
                <th class="text-left">الإجمالي</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $item)
                @php($rem = max(0, $item->quantity - $item->delivered_quantity))
                <tr>
                    <td>{{ $item->beneficiary?->grade_snapshot ?: 'عام' }}</td>
                    <td>{{ $item->description_snapshot }}</td>
                    <td class="text-center">{{ $item->quantity }}</td>
                    <td class="text-center">{{ $item->delivered_quantity }}</td>
                    <td class="text-center">{{ $rem }}</td>
                    <td class="text-left">{{ number_format((float)$item->unit_price, 2) }}</td>
                    <td class="text-left">{{ number_format((float)$item->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if($order->payments->isNotEmpty())
        <h2>حركة السداد والدفعات المسجلة</h2>
        <table>
            <thead>
                <tr>
                    <th>تاريخ الدفعة</th>
                    <th>طريقة الدفع</th>
                    <th>رقم العملية / الإشعار</th>
                    <th>الحالة</th>
                    <th class="text-left">المبلغ</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->payments as $payment)
                    <tr>
                        <td>{{ $payment->paid_at->format('Y-m-d') }}</td>
                        <td>{{ $payment->method === 'cash' ? 'نقداً' : 'تحويل بنكي' }}</td>
                        <td>{{ $payment->transaction_reference ?: '—' }}</td>
                        <td>{{ ['not_required'=>'مؤكد نقداً','pending'=>'بانتظار التحقق','verified'=>'تم التحقق'][$payment->verification_status] }}</td>
                        <td class="text-left">{{ number_format((float)$payment->amount, 2) }} ج.س</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if($order->deliveries->isNotEmpty())
        <h2>سجل التسليمات الفعلية</h2>
        <table>
            <thead>
                <tr>
                    <th>تاريخ التسليم</th>
                    <th>اسم المستلم</th>
                    <th>رقم الإيصال</th>
                    <th>الكميات المسلّمة</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->deliveries as $del)
                    <tr>
                        <td>{{ $del->delivered_at->format('Y-m-d H:i') }}</td>
                        <td>{{ $del->received_by_name }}</td>
                        <td>{{ $del->receipt_number ?: '—' }}</td>
                        <td>
                            @foreach($del->items as $dItem)
                                {{ $order->items->firstWhere('id', $dItem->order_item_id)?->description_snapshot }}: ({{ $dItem->quantity_delivered }})@if(! $loop->last)، @endif
                            @endforeach
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
