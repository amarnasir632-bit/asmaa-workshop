@extends('layouts.app')
@section('title', 'تفاصيل الطلب')
@section('content')
<div class="mx-auto max-w-5xl space-y-5">
    <header class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <a href="{{ route('orders.index') }}" class="text-sm font-semibold text-[var(--brand-navy)] underline">العودة للطلبات</a>
            <h1 class="mt-2 text-2xl font-bold text-[var(--brand-navy)]">
                طلب {{ $order->number }}
                @if($order->schoolContract)
                    <span class="mr-2 inline-block rounded-full bg-indigo-100 px-3 py-1 text-xs font-semibold text-indigo-800">عقد مدرسة جماعي</span>
                @endif
            </h1>
            <p class="mt-1 text-sm text-[var(--brand-muted)]">{{ $order->customer_name_snapshot }} · {{ ['custom'=>'تفصيل','ready'=>'ملابس جاهزة','alteration'=>'تعديل أو إصلاح'][$order->type] }} · {{ $order->created_on->format('Y-m-d') }}</p>
        </div>
        <div class="rounded-lg bg-slate-100 px-4 py-2 text-sm">{{ ['new'=>'جديد','in_progress'=>'قيد التنفيذ','partially_received'=>'استلام جزئي','fully_received'=>'مستلم بالكامل','ready'=>'جاهز','delivered'=>'مسلّم','cancelled'=>'ملغي'][$order->operational_status] }}</div>
    </header>
    @if($order->notes)<section class="rounded-xl border border-[var(--brand-border)] bg-white p-4"><h2 class="font-semibold">ملاحظات</h2><p class="mt-2 whitespace-pre-line text-sm">{{ $order->notes }}</p></section>@endif
    @if($order->schoolContract)
    <section class="space-y-4 rounded-xl border border-[var(--brand-border)] bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b pb-3">
            <div>
                <h2 class="text-xl font-bold text-[var(--brand-navy)]">عقد مدرسة جماعي · {{ $order->schoolContract->school->name }}</h2>
                <p class="mt-1 text-sm text-[var(--brand-muted)]">
                    {{ $order->schoolContract->contract_number ? 'رقم العقد: '.$order->schoolContract->contract_number.' · ' : '' }}
                    {{ $order->schoolContract->contact_name ? 'جهة الاتصال: '.$order->schoolContract->contact_name : '' }}
                </p>
            </div>
            @can('payments.record')
                @if($order->financial_summary['due'] > 0 && $order->operational_status !== 'cancelled')
                    <a href="{{ route('payments.index', ['order_id' => $order->id]) }}" class="rounded-lg bg-emerald-700 px-3.5 py-2 text-xs font-bold text-white shadow hover:bg-emerald-800">
                        + تسجيل دفعة للمدرسة
                    </a>
                @endif
            @endcan
        </div>

        @if($order->schoolContract->terms)
            <div class="rounded-lg bg-slate-50 p-3 text-sm">
                <span class="font-semibold text-slate-700">الشروط والملاحظات:</span>
                <p class="mt-1 whitespace-pre-line text-slate-600">{{ $order->schoolContract->terms }}</p>
            </div>
        @endif

        <!-- إحصائيات عقد المدرسة: كم لبسة، كم مرحلة، دفعوا كم، متبقي كم -->
        <div class="grid grid-cols-2 gap-3 text-center sm:grid-cols-5">
            <div class="rounded-xl border border-amber-200 bg-amber-50/70 p-3">
                <p class="text-xs font-medium text-amber-800">إجمالي اللبسات المطلوبة</p>
                <p class="mt-1 text-2xl font-black text-amber-900">{{ $order->items->sum('quantity') }}</p>
                <span class="text-[11px] text-amber-700">لبسة / قطعة</span>
            </div>
            <div class="rounded-xl border border-sky-200 bg-sky-50/70 p-3">
                <p class="text-xs font-medium text-sky-800">المراحل المشمولة</p>
                <p class="mt-1 text-2xl font-black text-sky-900">{{ $order->schoolContract->beneficiaries->pluck('grade_snapshot')->filter()->unique()->count() ?: 1 }}</p>
                <span class="text-[11px] text-sky-700">مراحل دراسية</span>
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                <p class="text-xs font-medium text-slate-700">قيمة العقد الإجمالية</p>
                <p class="mt-1 text-2xl font-black text-slate-900">{{ number_format((float)$order->approved_total, 2) }}</p>
                <span class="text-[11px] text-slate-500">ج.س</span>
            </div>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50/70 p-3">
                <p class="text-xs font-medium text-emerald-800">المدفوع المؤكد (دفعوا)</p>
                <p class="mt-1 text-2xl font-black text-emerald-900">{{ number_format($order->financial_summary['net_paid'] / 100, 2) }}</p>
                <span class="text-[11px] text-emerald-700">ج.س</span>
            </div>
            <div class="rounded-xl border border-rose-200 bg-rose-50/70 p-3">
                <p class="text-xs font-medium text-rose-800">المتبقي على المدرسة</p>
                <p class="mt-1 text-2xl font-black text-rose-900">{{ number_format($order->financial_summary['due'] / 100, 2) }}</p>
                <span class="text-[11px] text-rose-700">ج.س</span>
            </div>
        </div>

        <!-- تفصيل اللبسات لكل مرحلة دراسية -->
        <div>
            <h3 class="mb-2 text-sm font-bold text-slate-800">تفصيل اللبسات والكميات لكل مرحلة دراسية:</h3>
            <div class="overflow-x-auto rounded-lg border bg-white">
                <table class="w-full text-right text-xs">
                    <thead class="border-b bg-slate-50 font-bold text-slate-700">
                        <tr>
                            <th class="p-2.5">المرحلة الدراسية / الدفعة</th>
                            <th class="p-2.5 text-center">كم لبسة (الكمية)</th>
                            <th class="p-2.5">الأصناف واللبسات</th>
                            <th class="p-2.5 text-left">إجمالي القيمة</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach($order->schoolContract->beneficiaries as $ben)
                            @php($benQty = $ben->items->sum('quantity'))
                            @php($benAmount = $ben->items->sum('line_total'))
                            <tr class="hover:bg-slate-50/50">
                                <td class="p-2.5 font-semibold text-[var(--brand-navy)]">
                                    {{ $ben->grade_snapshot ?: 'عام' }}
                                    @if($ben->name_snapshot && $ben->name_snapshot !== ('دفعة ' . $ben->grade_snapshot))
                                        <br><span class="text-[11px] font-normal text-slate-500">{{ $ben->name_snapshot }}</span>
                                    @endif
                                </td>
                                <td class="p-2.5 text-center font-bold text-amber-800">
                                    {{ $benQty }} لبسة
                                </td>
                                <td class="p-2.5 text-slate-700">
                                    {{ $ben->items->pluck('description_snapshot')->join('، ') }}
                                </td>
                                <td class="p-2.5 text-left font-bold text-slate-900">
                                    {{ number_format((float)$benAmount, 2) }} ج.س
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </section>
    @endif
    <section class="overflow-hidden rounded-xl border border-[var(--brand-border)] bg-white"><h2 class="border-b p-4 font-bold">بنود الطلب</h2><div class="divide-y">@foreach($order->items as $item)<article class="p-4"><div class="flex flex-wrap items-start justify-between gap-3"><div><h3 class="font-semibold">{{ $item->description_snapshot }}</h3><p class="mt-1 text-sm text-[var(--brand-muted)]">{{ $item->beneficiary?->name_snapshot ? 'المستفيد: '.$item->beneficiary->name_snapshot.' · ' : '' }}{{ $item->child?->name ? 'الطفل: '.$item->child->name.' · ' : '' }}الكمية {{ $item->quantity }} · سعر الوحدة {{ number_format((float)$item->unit_price,2) }} ج.س @if($item->variant) · {{ $item->variant->garmentType->name }}{{ $item->variant->size ? ' · '.$item->variant->size : '' }}{{ $item->variant->color ? ' · '.$item->variant->color : '' }}@endif</p></div><div class="text-left"><p class="font-bold">{{ number_format((float)$item->line_total,2) }} ج.س</p>@if((float)$item->discount_amount>0)<p class="text-xs text-[var(--brand-muted)]">خصم: {{ number_format((float)$item->discount_amount,2) }}</p>@endif</div></div>
        @if($item->measurementSnapshot)<div class="mt-3 grid grid-cols-2 gap-2 rounded-lg bg-slate-50 p-3 text-sm sm:grid-cols-5">@foreach(['shoulder_cm'=>'الكتف','chest_cm'=>'الصدر','sleeve_cm'=>'الكم','waist_circumference_cm'=>'محيط الخصر','length_cm'=>'الطول'] as $field=>$label)<div><span class="text-[var(--brand-muted)]">{{ $label }}</span><br><b>{{ $item->measurementSnapshot->{$field} ?? '—' }} سم</b></div>@endforeach</div>@endif
    </article>@endforeach</div><div class="space-y-2 border-t bg-slate-50 p-4 text-sm"><div class="flex justify-between"><span>مجموع البنود قبل الخصم</span><b>{{ number_format((float)$order->items_total,2) }} ج.س</b></div><div class="flex justify-between"><span>الخصومات المسجلة على البنود</span><b>{{ number_format((float)$order->discount_total,2) }} ج.س</b></div><div class="flex justify-between text-base"><span>القيمة المعتمدة للطلب</span><b>{{ number_format((float)$order->approved_total,2) }} ج.س</b></div></div></section>
    <section class="rounded-xl border border-[var(--brand-border)] bg-white p-4"><h2 class="font-bold">ملخص السداد</h2><div class="mt-3 grid gap-2 text-sm sm:grid-cols-4"><p>قيمة الطلب: <b>{{ number_format((float)$order->approved_total,2) }} ج.س</b></p><p>المبالغ المسجلة: <b>{{ number_format($order->financial_summary['recorded']/100,2) }} ج.س</b></p><p>المبلغ الصافي المؤكد: <b>{{ number_format($order->financial_summary['net_paid']/100,2) }} ج.س</b></p><p>الرصيد المستحق: <b>{{ number_format($order->financial_summary['due']/100,2) }} ج.س</b></p></div><p class="mt-2 text-sm">الحالة المالية: <b>{{ ['unpaid'=>'غير مدفوع','partially_paid'=>'مدفوع جزئياً','paid'=>'مسدد بالكامل','transfer_pending'=>'تحويل بانتظار التحقق','refunded_partial'=>'مسترد جزئياً','refunded_full'=>'مسترد كلياً'][$order->financial_summary['status']] }}</b>@if($order->financial_summary['has_pending_transfer'] && $order->financial_summary['status'] !== 'transfer_pending') · يوجد تحويل بانتظار التحقق @endif</p><p class="mt-1 text-xs text-[var(--brand-muted)]">التحويل البنكي المعلق لا يُحتسب ضمن السداد.</p></section>
    @can('orders.cancel')
        @if($order->operational_status !== 'cancelled')
        <section class="rounded-xl border border-amber-200 bg-amber-50 p-4"><h2 class="font-bold">إلغاء الطلب</h2><p class="mt-1 text-sm">يحدد المدير الخصم لأول مرة عند الإلغاء وفق مرحلة الإنتاج. لا توجد نسبة ثابتة.</p><form method="POST" action="{{ route('orders.cancel',$order) }}" class="mt-3 grid gap-3 sm:grid-cols-2">@csrf<label class="text-sm">الخصم الذي يحتفظ به المشغل<input required name="cancellation_discount" inputmode="decimal" class="mt-1 w-full rounded-lg border-slate-300"></label><label class="text-sm sm:col-span-2">سبب الإلغاء<textarea required name="reason" rows="2" class="mt-1 w-full rounded-lg border-slate-300"></textarea></label><button class="rounded-lg bg-amber-800 px-4 py-2 font-bold text-white">اعتماد الإلغاء</button></form></section>
        @else @foreach($order->cancellations as $cancellation)<section class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm"><h2 class="font-bold">قرار الإلغاء</h2><p>المرحلة: {{ $cancellation->production_stage_snapshot }} · الخصم: {{ number_format((float)$cancellation->cancellation_discount,2) }} · المستحق إرجاعه: {{ number_format((float)$cancellation->refund_due,2) }}</p><p class="mt-1">السبب: {{ $cancellation->reason }} · {{ $cancellation->approved_at->format('Y-m-d H:i') }}</p>@if(auth()->user()->can('history.correct'))<form method="POST" action="{{ route('orders.cancellation.correct',$order) }}" class="mt-3 flex flex-wrap items-end gap-2">@csrf @method('PATCH')<label>الخصم المصحح<input required name="cancellation_discount" value="{{ number_format((float)$cancellation->cancellation_discount,2,'.','') }}" class="mt-1 block w-36 rounded border-slate-300"></label><label class="min-w-56 flex-1">سبب التصحيح<input required name="reason" class="mt-1 block w-full rounded border-slate-300"></label><button class="rounded bg-amber-800 px-3 py-2 text-white">حفظ تصحيح موثق</button></form>@endif</section>@endforeach @endif
    @endcan
    @can('refunds.approve')
        <section class="rounded-xl border border-slate-200 bg-white p-4"><h2 class="font-bold">اعتماد استرداد</h2><form method="POST" action="{{ route('refunds.approve',$order) }}" class="mt-3 grid gap-3 sm:grid-cols-2">@csrf<label class="text-sm">المبلغ المعتمد<input required name="approved_amount" inputmode="decimal" class="mt-1 w-full rounded-lg border-slate-300"></label><label class="text-sm">الدفعة الأصلية (اختياري)<select name="original_payment_id" class="mt-1 w-full rounded-lg border-slate-300"><option value="">غير محددة</option>@foreach($order->payments as $payment)<option value="{{ $payment->id }}">{{ $payment->method === 'cash' ? 'نقد' : 'تحويل' }} · {{ number_format((float)$payment->amount,2) }} · {{ $payment->verification_status === 'pending' ? 'معلق' : $payment->paid_at->format('Y-m-d') }}</option>@endforeach</select></label>
            @if($order->deliveries->isNotEmpty())<label class="text-sm">القطعة المرتجعة بعد استلامها<select required name="returned_delivery_item_id" class="mt-1 w-full rounded-lg border-slate-300"><option value="">اختر قطعة</option>@foreach($order->deliveries->flatMap->items as $deliveryItem)<option value="{{ $deliveryItem->id }}">{{ $deliveryItem->orderItem->description_snapshot }} · كمية مسلمة {{ $deliveryItem->quantity_delivered }}</option>@endforeach</select></label><label class="text-sm">الكمية المرتجعة<input required type="number" min="1" name="returned_quantity" class="mt-1 w-full rounded-lg border-slate-300"></label><label class="text-sm">نتيجة الفحص<select required name="returned_item_condition" class="mt-1 w-full rounded-lg border-slate-300"><option value="resalable">صالحة لإعادة البيع</option><option value="damaged">تالفة</option><option value="altered">تم تعديلها</option></select></label><label class="text-sm">ملاحظات الفحص<textarea name="inspection_notes" class="mt-1 w-full rounded-lg border-slate-300"></textarea></label>@endif
            <label class="text-sm sm:col-span-2">سبب الاسترداد<textarea required name="reason" rows="2" class="mt-1 w-full rounded-lg border-slate-300"></textarea></label><button class="rounded-lg bg-[var(--brand-navy)] px-4 py-2 font-bold text-white">اعتماد الاسترداد</button></form>
            @foreach($order->refunds as $refund)<article class="mt-4 border-t pt-3 text-sm"><p>استرداد #{{ $refund->id }} · معتمد {{ number_format((float)$refund->approved_amount,2) }} · مدفوع فعلياً {{ number_format((float)$refund->refunded_amount,2) }} · {{ $refund->reason }}</p>@if($refund->returned_item_received_at)<p>المرتجع: {{ $refund->returned_quantity }} · الحالة {{ ['resalable'=>'صالح للبيع','damaged'=>'تالف','altered'=>'معدل'][$refund->returned_item_condition] }} · فُحص {{ $refund->returned_item_received_at->format('Y-m-d H:i') }}</p>@endif
                @if(auth()->user()->can('history.correct'))<form method="POST" action="{{ route('refunds.correct',$refund) }}" class="mt-2 flex flex-wrap items-end gap-2">@csrf @method('PATCH')<label>المبلغ المعتمد المصحح<input required name="approved_amount" value="{{ number_format((float)$refund->approved_amount,2,'.','') }}" class="mt-1 block w-32 rounded border-slate-300"></label><label>سبب التصحيح<input required name="reason" class="mt-1 block rounded border-slate-300"></label><button class="rounded bg-amber-800 px-3 py-2 text-white">تصحيح موثق</button></form>@endif
                @if((float)$refund->approved_amount > (float)$refund->refunded_amount)<form method="POST" action="{{ route('refunds.pay',$refund) }}" class="mt-2 flex flex-wrap items-end gap-2">@csrf<label>المبلغ المنفذ<input required name="amount" inputmode="decimal" max="{{ number_format((float)$refund->approved_amount-(float)$refund->refunded_amount,2,'.','') }}" class="mt-1 block w-32 rounded border-slate-300"></label><label>الطريقة<select name="method" class="mt-1 block rounded border-slate-300"><option value="cash">نقد</option><option value="bank_transfer">تحويل بنكي</option></select></label><label>التاريخ<input required type="datetime-local" name="refunded_at" value="{{ now()->format('Y-m-d\TH:i') }}" class="mt-1 block rounded border-slate-300"></label><button class="rounded bg-emerald-700 px-3 py-2 text-white">تسجيل الدفع الفعلي</button></form>@endif
                @foreach($refund->transactions as $transaction)<p class="mt-1 text-xs text-slate-600">دفع فعلي: {{ number_format((float)$transaction->amount,2) }} · {{ $transaction->method === 'cash' ? 'نقد' : 'تحويل بنكي' }} · {{ $transaction->refunded_at->format('Y-m-d H:i') }}</p>@endforeach
            </article>@endforeach</section>
    @endcan
    @if(auth()->user()->can('deliveries.create'))
        <section class="rounded-xl border border-[var(--brand-border)] bg-white p-4"><h2 class="font-bold">تسجيل تسليم</h2>
            @if($order->operational_status === 'cancelled')<p class="mt-2 text-sm text-red-800">الطلب ملغي ولا يمكن تسليمه.</p>
            @elseif($order->financial_summary['due'] > 0)<p class="mt-2 text-sm text-amber-800">يمنع التسليم حتى سداد الرصيد بالكامل. الرصيد الحالي {{ number_format($order->financial_summary['due']/100,2) }} ج.س</p>
            @elseif($order->items->sum(fn($item) => $item->quantity - $item->delivered_quantity) > 0)
            <form method="POST" action="{{ route('deliveries.store',$order) }}" class="mt-3 grid gap-3 sm:grid-cols-2">@csrf
                <label class="text-sm">اسم المستلم<input required name="received_by_name" class="mt-1 w-full rounded-lg border-slate-300"></label>
                <label class="text-sm">التاريخ<input required type="datetime-local" name="delivered_at" value="{{ now()->format('Y-m-d\TH:i') }}" class="mt-1 w-full rounded-lg border-slate-300"></label>
                <label class="text-sm">رقم الإيصال (اختياري)<input name="receipt_number" class="mt-1 w-full rounded-lg border-slate-300"></label>
                <div class="sm:col-span-2"><p class="mb-2 text-sm font-semibold">الكميات المطلوبة والمتبقية</p>@foreach($order->items as $item)@php($remaining = $item->quantity - $item->delivered_quantity)<label class="mb-2 grid grid-cols-[1fr_5rem_6rem] items-center gap-2 rounded-lg bg-slate-50 p-2 text-sm"><span>{{ $item->description_snapshot }} · المتبقي {{ $remaining }}</span><input type="hidden" name="items[{{ $loop->index }}][order_item_id]" value="{{ $item->id }}"><input aria-label="كمية {{ $item->description_snapshot }}" type="number" name="items[{{ $loop->index }}][quantity_delivered]" min="0" max="{{ $remaining }}" value="0" class="rounded border-slate-300"><span>من {{ $item->quantity }}</span></label>@endforeach</div>
                <label class="text-sm sm:col-span-2">ملاحظات<textarea name="notes" rows="2" class="mt-1 w-full rounded-lg border-slate-300"></textarea></label>
                <button class="rounded-lg bg-[var(--brand-navy)] px-4 py-2 font-bold text-white">حفظ التسليم</button>
            </form>@else<p class="mt-2 text-sm text-emerald-800">اكتملت كميات الطلب المسلمة.</p>@endif
        </section>
    @endif
    <section class="rounded-xl border border-[var(--brand-border)] bg-white p-4"><h2 class="font-bold">سجل التسليمات</h2>@forelse($order->deliveries as $delivery)<article class="mt-3 border-t pt-3 text-sm"><p>{{ $delivery->delivered_at->format('Y-m-d H:i') }} · المستلم: {{ $delivery->received_by_name }} · الإيصال: {{ $delivery->receipt_number ?? '—' }}</p><ul class="mt-1 list-inside list-disc">@foreach($delivery->items as $deliveryItem)<li>{{ $order->items->firstWhere('id',$deliveryItem->order_item_id)->description_snapshot }}: {{ $deliveryItem->quantity_delivered }}</li>@endforeach</ul></article>@empty<p class="mt-2 text-sm text-slate-500">لا توجد تسليمات مسجلة.</p>@endforelse</section>
    @if(auth()->user()->can('finance.view') && $order->financialCorrections->isNotEmpty())<section class="rounded-xl border border-[var(--brand-border)] bg-white p-4"><h2 class="font-bold">تصحيحات مالية موثقة</h2>@foreach($order->financialCorrections as $correction)<p class="mt-2 text-sm">{{ $correction->field_name }}: {{ number_format((float)$correction->old_amount,2) }} ← {{ number_format((float)$correction->new_amount,2) }} · {{ $correction->reason }} · {{ $correction->occurred_at->format('Y-m-d H:i') }}</p>@endforeach</section>@endif
    <p class="text-xs text-[var(--brand-muted)]">أنشأه {{ $order->creator->name }}.</p>
</div>
@endsection
