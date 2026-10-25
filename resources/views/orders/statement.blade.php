<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>كشف حساب وموقف توريد · طلب {{ $order->number }} · مشغل أسماء</title>
    <link rel="icon" type="image/png" href="{{ asset('icon.png') }}">
    @vite(['resources/css/app.css'])
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; padding: 0 !important; color: black !important; }
            .print-shadow-none { box-shadow: none !important; border-color: #cbd5e1 !important; }
            @page { size: A4 portrait; margin: 12mm 10mm; }
        }
    </style>
</head>
<body class="bg-slate-100 p-4 font-sans text-slate-800 antialiased sm:p-6 lg:p-8">

    <!-- شريط الأزرار العلوي (يختفي عند الطباعة) -->
    <div class="no-print mx-auto mb-6 flex max-w-4xl flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <a href="{{ route('orders.show', $order) }}" class="inline-flex items-center text-sm font-semibold text-[var(--brand-navy)] hover:underline">
            ← العودة لتفاصيل الطلب
        </a>
        <div class="flex flex-wrap items-center gap-2">
            <button onclick="window.print()" class="inline-flex items-center gap-2 rounded-lg bg-[var(--brand-navy)] px-4 py-2 text-sm font-bold text-white shadow hover:opacity-90">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                طباعة / حفظ كـ PDF
            </button>
            <a href="{{ route('orders.statement.pdf', $order) }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-slate-50 px-3.5 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">
                تنزيل PDF مباشر
            </a>
        </div>
    </div>

    @php($totalOrdered = (int) $order->items->sum('quantity'))
    @php($totalDelivered = (int) $order->items->sum('delivered_quantity'))
    @php($totalRemaining = max(0, $totalOrdered - $totalDelivered))
    @php($summary = $order->financial_summary)

    <!-- ورقة المستند الرسمي (A4) -->
    <main class="print-shadow-none mx-auto max-w-4xl rounded-2xl border border-slate-200 bg-white p-6 shadow-md sm:p-10 space-y-6">

        <!-- الترويسة الرسمية للمشغل -->
        <header class="flex flex-wrap items-center justify-between gap-4 border-b-2 border-slate-200 pb-6">
            <div class="flex items-center gap-4">
                <img src="{{ asset('logo.png') }}" alt="مشغل أسماء" class="h-16 w-auto object-contain">
                <div>
                    <h1 class="text-2xl font-black text-[var(--brand-navy)]">مشغل أسماء</h1>
                    <p class="text-xs font-semibold text-[var(--brand-gold)]">لتفصيل وتوزيع الملابس المدرسية والزي الموحد</p>
                    <p class="mt-0.5 text-[11px] text-slate-500">الخرطوم · جمهورية السودان · العملة: الجنيه السوداني (SDG)</p>
                </div>
            </div>
            <div class="text-left">
                <span class="inline-block rounded-md bg-indigo-50 px-3 py-1 text-xs font-bold text-indigo-900 border border-indigo-200">
                    كشف حساب وموقف توريد
                </span>
                <p class="mt-2 text-sm font-bold text-slate-800">طلب رقم: <span class="text-[var(--brand-navy)]">#{{ $order->number }}</span></p>
                <p class="text-xs text-slate-500">تاريخ الإصدار: {{ now(config('app.display_timezone'))->format('Y-m-d') }}</p>
            </div>
        </header>

        <!-- بيانات المدرسة والعقد -->
        <section class="grid gap-4 rounded-xl border border-slate-200 bg-slate-50/70 p-4 sm:grid-cols-2 text-xs">
            <div>
                <p class="text-slate-500">المدرسة / العميل:</p>
                <p class="text-sm font-bold text-slate-900 mt-0.5">{{ $order->customer_name_snapshot ?: $order->guardian?->name }}</p>
                @if($order->schoolContract?->contact_name)
                    <p class="text-slate-600 mt-1">مندوب المدرسة: <b>{{ $order->schoolContract->contact_name }}</b></p>
                @endif
            </div>
            <div>
                <p class="text-slate-500">بيانات العقد والتواريخ:</p>
                <p class="text-slate-700 mt-0.5">
                    {{ $order->schoolContract?->contract_number ? 'رقم العقد: '.$order->schoolContract->contract_number.' · ' : '' }}
                    تاريخ الطلب: <b>{{ $order->created_on->format('Y-m-d') }}</b>
                </p>
                <p class="text-slate-700 mt-1">
                    موعد التسليم المتوقع: <b>{{ $order->due_on?->format('Y-m-d') ?? '—' }}</b>
                </p>
            </div>
            @if($order->schoolContract?->terms)
                <div class="sm:col-span-2 border-t border-slate-200 pt-2 text-[11px] text-slate-600">
                    <span class="font-bold text-slate-700">ملاحظات وشروط الاتفاق:</span>
                    <p class="whitespace-pre-line mt-0.5">{{ $order->schoolContract->terms }}</p>
                </div>
            @endif
        </section>

        <!-- بطاقات ملخص الموقف الشامل (المطلوب، المسلم، المدفوع، والمتبقي) -->
        <section>
            <h2 class="mb-3 text-sm font-bold text-[var(--brand-navy)]">ملخص الموقف المالي والتوريدي</h2>
            <div class="grid grid-cols-2 gap-3 text-center sm:grid-cols-6 text-xs">
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-2.5">
                    <p class="text-[11px] text-slate-500 font-medium">اللبسات المطلوبة</p>
                    <p class="mt-1 text-xl font-black text-slate-900">{{ $totalOrdered }}</p>
                    <span class="text-[10px] text-slate-400">كم لبسة طُلبت</span>
                </div>
                <div class="rounded-xl border border-emerald-200 bg-emerald-50/70 p-2.5">
                    <p class="text-[11px] text-emerald-800 font-medium">استلموا كم (المسلّم)</p>
                    <p class="mt-1 text-xl font-black text-emerald-900">{{ $totalDelivered }}</p>
                    <span class="text-[10px] text-emerald-700">لبسة تم تسليمها</span>
                </div>
                <div class="rounded-xl border border-amber-200 bg-amber-50/70 p-2.5">
                    <p class="text-[11px] text-amber-800 font-medium">ما استلموا كم (المتبقي)</p>
                    <p class="mt-1 text-xl font-black text-amber-900">{{ $totalRemaining }}</p>
                    <span class="text-[10px] text-amber-700">متبقي للتسليم</span>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-2.5">
                    <p class="text-[11px] text-slate-500 font-medium">قيمة العقد</p>
                    <p class="mt-1 text-xl font-black text-slate-900">{{ number_format((float)$order->approved_total, 2) }}</p>
                    <span class="text-[10px] text-slate-400">ج.س</span>
                </div>
                <div class="rounded-xl border border-emerald-200 bg-emerald-50/70 p-2.5">
                    <p class="text-[11px] text-emerald-800 font-medium">دفعوا كم (المدفوع)</p>
                    <p class="mt-1 text-xl font-black text-emerald-900">{{ number_format($summary['net_paid'] / 100, 2) }}</p>
                    <span class="text-[10px] text-emerald-700">المؤكد المسدد</span>
                </div>
                <div class="rounded-xl border border-rose-200 bg-rose-50/70 p-2.5">
                    <p class="text-[11px] text-rose-800 font-medium">متبقي كم (الرصيد)</p>
                    <p class="mt-1 text-xl font-black text-rose-900">{{ number_format($summary['due'] / 100, 2) }}</p>
                    <span class="text-[10px] text-rose-700 font-semibold">مستحق السداد</span>
                </div>
            </div>
        </section>

        <!-- تفصيل اللبسات لكل مرحلة (المطلوب، المستلم، ما استلمت، والمتبقي) -->
        <section class="space-y-2">
            <h2 class="text-sm font-bold text-[var(--brand-navy)]">تفاصيل اللبسات والأصناف وموقف التسليم</h2>
            <div class="overflow-x-auto rounded-xl border border-slate-200">
                <table class="w-full text-right text-xs">
                    <thead class="bg-slate-100 font-bold text-slate-700">
                        <tr>
                            <th class="p-2.5">المرحلة الدراسية / الدفعة</th>
                            <th class="p-2.5">وصف اللبسة / الصنف</th>
                            <th class="p-2.5 text-center">المطلوب</th>
                            <th class="p-2.5 text-center text-emerald-800">المسلّم (استلموا)</th>
                            <th class="p-2.5 text-center text-rose-800">المتبقي (ما استلموا)</th>
                            <th class="p-2.5 text-left">سعر الوحدة</th>
                            <th class="p-2.5 text-left">الإجمالي</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($order->items as $item)
                            @php($itemRem = max(0, $item->quantity - $item->delivered_quantity))
                            <tr class="hover:bg-slate-50/40">
                                <td class="p-2.5 font-bold text-[var(--brand-navy)]">
                                    {{ $item->beneficiary?->grade_snapshot ?: 'عام' }}
                                    @if($item->beneficiary?->name_snapshot && $item->beneficiary->name_snapshot !== ('دفعة ' . $item->beneficiary->grade_snapshot))
                                        <br><span class="text-[10px] font-normal text-slate-500">{{ $item->beneficiary->name_snapshot }}</span>
                                    @endif
                                </td>
                                <td class="p-2.5 font-medium text-slate-800">
                                    {{ $item->description_snapshot }}
                                </td>
                                <td class="p-2.5 text-center font-bold">
                                    {{ $item->quantity }}
                                </td>
                                <td class="p-2.5 text-center font-bold text-emerald-700">
                                    {{ $item->delivered_quantity }}
                                </td>
                                <td class="p-2.5 text-center font-bold {{ $itemRem > 0 ? 'text-rose-700' : 'text-slate-400' }}">
                                    {{ $itemRem }}
                                </td>
                                <td class="p-2.5 text-left text-slate-600">
                                    {{ number_format((float)$item->unit_price, 2) }}
                                </td>
                                <td class="p-2.5 text-left font-bold text-slate-900">
                                    {{ number_format((float)$item->line_total, 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="border-t-2 border-slate-200 bg-slate-50 font-bold">
                        <tr>
                            <td colspan="2" class="p-2.5 text-slate-700">الإجمالي العام للبنود:</td>
                            <td class="p-2.5 text-center text-slate-900">{{ $totalOrdered }} لبسة</td>
                            <td class="p-2.5 text-center text-emerald-800">{{ $totalDelivered }} لبسة</td>
                            <td class="p-2.5 text-center text-rose-800">{{ $totalRemaining }} لبسة</td>
                            <td></td>
                            <td class="p-2.5 text-left text-[var(--brand-navy)]">{{ number_format((float)$order->approved_total, 2) }} ج.س</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>

        <!-- سجل الدفعات المسددة (دفعو كم) -->
        <section class="space-y-2">
            <h2 class="text-sm font-bold text-[var(--brand-navy)]">حركة السداد والدفعات المسجلة</h2>
            @if($order->payments->isNotEmpty())
                <div class="overflow-x-auto rounded-xl border border-slate-200">
                    <table class="w-full text-right text-xs">
                        <thead class="bg-slate-100 font-bold text-slate-700">
                            <tr>
                                <th class="p-2.5">تاريخ الدفعة</th>
                                <th class="p-2.5">طريقة الدفع</th>
                                <th class="p-2.5">رقم العملية / الإشعار</th>
                                <th class="p-2.5">حالة الدفعة</th>
                                <th class="p-2.5 text-left">المبلغ المسدد</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($order->payments as $payment)
                                <tr>
                                    <td class="p-2.5 text-slate-700">{{ $payment->paid_at->format('Y-m-d H:i') }}</td>
                                    <td class="p-2.5 font-medium">{{ $payment->method === 'cash' ? 'نقداً (إيصال استلام)' : 'تحويل بنكي' }}</td>
                                    <td class="p-2.5 text-slate-600 font-mono">{{ $payment->transaction_reference ?: '—' }}</td>
                                    <td class="p-2.5">
                                        <span class="inline-block rounded px-2 py-0.5 text-[10px] font-bold {{ $payment->verification_status === 'pending' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800' }}">
                                            {{ ['not_required'=>'مؤكد نقداً','pending'=>'بانتظار تحقق المدير','verified'=>'معتمد ومؤكد'][$payment->verification_status] }}
                                        </span>
                                    </td>
                                    <td class="p-2.5 text-left font-bold text-slate-900">{{ number_format((float)$payment->amount, 2) }} ج.س</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t bg-slate-50 font-bold">
                            <tr>
                                <td colspan="4" class="p-2.5 text-slate-700">مجموع المبالغ المسددة المؤكدة:</td>
                                <td class="p-2.5 text-left text-emerald-800">{{ number_format($summary['net_paid'] / 100, 2) }} ج.س</td>
                            </tr>
                            <tr>
                                <td colspan="4" class="p-2.5 text-rose-800">الرصيد المتبقي المطلوب سداده:</td>
                                <td class="p-2.5 text-left text-rose-800 text-sm">{{ number_format($summary['due'] / 100, 2) }} ج.س</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @else
                <p class="rounded-xl border border-dashed border-slate-300 p-4 text-center text-xs text-slate-500">
                    لم يتم تسجيل أي دفعات حتى الآن. الرصيد المتبقي: {{ number_format($summary['due'] / 100, 2) }} ج.س.
                </p>
            @endif
        </section>

        <!-- سجل التسليمات الفعلية (استلمت كم ومن استلمها) -->
        <section class="space-y-2">
            <h2 class="text-sm font-bold text-[var(--brand-navy)]">سجل التسليمات والاستلام الفعلي</h2>
            @if($order->deliveries->isNotEmpty())
                <div class="overflow-x-auto rounded-xl border border-slate-200">
                    <table class="w-full text-right text-xs">
                        <thead class="bg-slate-100 font-bold text-slate-700">
                            <tr>
                                <th class="p-2.5">تاريخ ووقت التسليم</th>
                                <th class="p-2.5">اسم المستلم من المدرسة</th>
                                <th class="p-2.5">رقم الإيصال</th>
                                <th class="p-2.5">تفاصيل الأصناف والكميات المسلمة</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($order->deliveries as $del)
                                <tr>
                                    <td class="p-2.5 text-slate-700">{{ $del->delivered_at->format('Y-m-d H:i') }}</td>
                                    <td class="p-2.5 font-bold text-slate-900">{{ $del->received_by_name }}</td>
                                    <td class="p-2.5 font-mono text-slate-600">{{ $del->receipt_number ?: '—' }}</td>
                                    <td class="p-2.5 text-slate-800">
                                        @foreach($del->items as $dItem)
                                            <span class="inline-block bg-slate-100 rounded px-1.5 py-0.5 ml-1 text-[11px]">
                                                {{ $order->items->firstWhere('id', $dItem->order_item_id)?->description_snapshot }}: <b>{{ $dItem->quantity_delivered }}</b>
                                            </span>
                                        @endforeach
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="rounded-xl border border-dashed border-slate-300 p-4 text-center text-xs text-slate-500">
                    لا توجد أي تسليمات مسجلة حتى الآن. البنود قيد التجهيز والتفصيل.
                </p>
            @endif
        </section>

        <!-- التوقيعات والاعتماد الرسمي -->
        <footer class="mt-10 pt-6 border-t border-slate-200 grid grid-cols-2 gap-8 text-center text-xs">
            <div class="space-y-12">
                <p class="font-bold text-slate-700">توقيع وخاتم إدارة مشغل أسماء:</p>
                <div class="border-b border-dashed border-slate-400 mx-auto w-48"></div>
                <p class="text-[11px] text-slate-400">التاريخ: ____________</p>
            </div>
            <div class="space-y-12">
                <p class="font-bold text-slate-700">استلام ومصادقة مندوب المدرسة:</p>
                <div class="border-b border-dashed border-slate-400 mx-auto w-48"></div>
                <p class="text-[11px] text-slate-400">الاسم والتوقيع: ____________</p>
            </div>
        </footer>

    </main>

</body>
</html>
