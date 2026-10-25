@extends('layouts.app')
@section('title', 'طلب مدرسة جماعي')
@section('content')
<div class="mx-auto max-w-6xl space-y-5">
    <header>
        <a href="{{ route('orders.index') }}" class="text-sm font-semibold text-[var(--brand-navy)] underline">العودة للطلبات</a>
        <h1 class="mt-2 text-2xl font-bold text-[var(--brand-navy)]">تسجيل طلب مدرسة جماعي</h1>
        <p class="mt-2 text-sm text-[var(--brand-muted)]">سجّل طلب المدرسة بتحديد عدد اللبسات لكل مرحلة دراسية، مع إمكانية إثبات الدفعة الأولى واحتساب المتبقي تلقائياً.</p>
    </header>

    @if ($errors->any())
        <div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            <ul class="list-inside list-disc">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- شريط ملخص تفاعلي للطلب الجماعي (كم لبسة، كم مرحلة، دفعوا كم، ومتبقي كم) -->
    <div id="school-order-summary" class="rounded-xl border border-[var(--brand-border)] bg-gradient-to-r from-slate-900 to-[var(--brand-navy)] p-4 text-white shadow-sm">
        <div class="grid grid-cols-2 gap-3 text-center sm:grid-cols-5">
            <div class="rounded-lg bg-white/5 p-2">
                <p class="text-xs text-slate-300">إجمالي اللبسات</p>
                <p id="summary-total-garments" class="mt-1 text-2xl font-black text-amber-400">0</p>
                <span class="text-[11px] text-slate-400">لبسة / قطعة</span>
            </div>
            <div class="rounded-lg bg-white/5 p-2">
                <p class="text-xs text-slate-300">المراحل المشمولة</p>
                <p id="summary-total-grades" class="mt-1 text-2xl font-black text-sky-400">0</p>
                <span class="text-[11px] text-slate-400">مراحل دراسية</span>
            </div>
            <div class="rounded-lg bg-white/5 p-2">
                <p class="text-xs text-slate-300">إجمالي قيمة العقد</p>
                <p id="summary-gross-total" class="mt-1 text-2xl font-black text-white">0.00</p>
                <span class="text-[11px] text-slate-400">ج.س</span>
            </div>
            <div class="rounded-lg bg-white/5 p-2">
                <p class="text-xs text-slate-300">المدفوع مقدماً</p>
                <p id="summary-paid-amount" class="mt-1 text-2xl font-black text-emerald-400">0.00</p>
                <span class="text-[11px] text-slate-400">ج.س</span>
            </div>
            <div class="rounded-lg bg-white/5 p-2">
                <p class="text-xs text-slate-300">المتبقي على المدرسة</p>
                <p id="summary-due-amount" class="mt-1 text-2xl font-black text-rose-400">0.00</p>
                <span class="text-[11px] text-slate-400">ج.س</span>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('school-contracts.store') }}" enctype="multipart/form-data" class="space-y-5">
        @csrf
        <section class="grid gap-3 rounded-xl border border-[var(--brand-border)] bg-white p-5 sm:grid-cols-2">
            <label class="text-sm font-semibold">المدرسة
                <select id="school-select" name="school_id" required class="mt-1 w-full rounded-lg border p-2.5">
                    <option value="">اختر المدرسة</option>
                    @foreach($schools as $school)
                        <option value="{{ $school->id }}" @selected(old('school_id') == $school->id)>{{ $school->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm font-semibold">رقم العقد (اختياري)
                <input name="contract_number" maxlength="80" value="{{ old('contract_number') }}" placeholder="مثال: SCH-2026-001" class="mt-1 w-full rounded-lg border p-2.5">
            </label>
            <label class="text-sm font-semibold">موعد الإنجاز والتسليم المتوقع
                <input type="date" name="due_on" min="{{ now(config('app.display_timezone'))->toDateString() }}" value="{{ old('due_on') }}" class="mt-1 w-full rounded-lg border p-2.5">
            </label>
            <label class="text-sm font-semibold">جهة الاتصال / المندوب بالمدرسة (اختياري)
                <input name="contact_name" maxlength="160" value="{{ old('contact_name') }}" placeholder="الاسم ورقم الهاتف" class="mt-1 w-full rounded-lg border p-2.5">
            </label>
            <label class="text-sm font-semibold sm:col-span-2">الشروط والملاحظات
                <textarea name="terms" maxlength="3000" rows="2" placeholder="أي شروط أو اتفاقيات خاصة بالعقد..." class="mt-1 w-full rounded-lg border p-2.5">{{ old('terms') }}</textarea>
            </label>
        </section>

        <!-- قسم المراحل والدفعات واللبسات -->
        <section class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold">المراحل واللبسات المطلوبة</h2>
                    <p class="text-xs text-[var(--brand-muted)]">حدّد المرحلة الدراسية أو اسم الدفعة، ثم أضف اللبسات والكميات المطلوبة لكل منها.</p>
                </div>
                <button type="button" id="add-contract-beneficiary" class="rounded-lg border border-[var(--brand-navy)] px-3 py-2 text-sm font-semibold text-[var(--brand-navy)] hover:bg-slate-50">
                    + إضافة مرحلة / دفعة جديدة
                </button>
            </div>

            <div id="contract-beneficiaries" data-next-beneficiary="1">
                <article class="space-y-4 rounded-xl border border-[var(--brand-border)] bg-white p-5" data-beneficiary data-beneficiary-index="0" data-next-item="1">
                    <div class="flex items-center justify-between border-b pb-2">
                        <h3 class="font-bold text-[var(--brand-navy)]">المرحلة / الدفعة 1</h3>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <label class="text-sm font-medium">المرحلة الدراسية
                            <select name="beneficiaries[0][grade_id]" class="grade-select mt-1 w-full rounded-lg border p-2">
                                <option value="">— اختر المرحلة —</option>
                                @foreach($schools as $school)
                                    @foreach($school->grades as $grade)
                                        <option value="{{ $grade->id }}" data-school-id="{{ $school->id }}">{{ $school->name }} · {{ $grade->name }}</option>
                                    @endforeach
                                @endforeach
                            </select>
                        </label>
                        <label class="text-sm font-medium">اسم الدفعة أو الطالب (اختياري)
                            <input name="beneficiaries[0][name]" maxlength="160" placeholder="مثلاً: طلبة الصف أو اسم الطالب" class="mt-1 w-full rounded-lg border p-2">
                        </label>
                        <label class="text-sm font-medium">ملف طفل مسجل (اختياري)
                            <select name="beneficiaries[0][child_id]" class="mt-1 w-full rounded-lg border p-2">
                                <option value="">بدون ملف</option>
                                @foreach($guardians as $guardian)
                                    @foreach($guardian->children as $child)
                                        @if($child->school_id)
                                            <option value="{{ $child->id }}">{{ $child->school?->name }} · {{ $child->name }}</option>
                                        @endif
                                    @endforeach
                                @endforeach
                            </select>
                        </label>
                        <label class="text-sm font-medium">مرجع مدرسي (اختياري)
                            <input name="beneficiaries[0][source_reference]" maxlength="100" placeholder="رقم القائمة أو الكشف" class="mt-1 w-full rounded-lg border p-2">
                        </label>
                    </div>

                    <div class="space-y-3" data-contract-items>
                        <div class="grid gap-2 rounded-lg bg-slate-50 p-3 sm:grid-cols-2 lg:grid-cols-6 items-end" data-contract-item-row>
                            <label class="text-xs font-medium lg:col-span-2">وصف اللبسة / الصنف
                                <input name="beneficiaries[0][items][0][description]" required maxlength="180" placeholder="مثلاً: قميص وبنطلون كحلي" class="mt-1 w-full rounded border p-2">
                            </label>
                            <label class="text-xs font-medium">نوع الملابس
                                <select name="beneficiaries[0][items][0][garment_type_id]" class="mt-1 w-full rounded border p-2">
                                    <option value="">—</option>
                                    @foreach($garmentTypes as $type)
                                        <option value="{{ $type->id }}">{{ $type->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="text-xs font-medium">من الكتالوج (اختياري)
                                <select name="beneficiaries[0][items][0][inventory_variant_id]" class="mt-1 w-full rounded border p-2">
                                    <option value="">تحديد يدوي</option>
                                    @foreach($variants as $variant)
                                        <option value="{{ $variant->id }}">{{ $variant->garmentType->name }}{{ $variant->school ? ' · '.$variant->school->name : '' }}{{ $variant->grade ? ' · '.$variant->grade->name : '' }}{{ $variant->size ? ' · '.$variant->size : '' }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="text-xs font-medium">الكمية (كم لبسة)
                                <input name="beneficiaries[0][items][0][quantity]" required type="number" min="1" max="500" value="1" class="item-quantity mt-1 w-full rounded border p-2 font-bold text-center">
                            </label>
                            <label class="text-xs font-medium">سعر الوحدة ج.س
                                <input name="beneficiaries[0][items][0][unit_price]" required inputmode="decimal" placeholder="0.00" class="item-unit-price mt-1 w-full rounded border p-2">
                            </label>
                            <input type="hidden" name="beneficiaries[0][items][0][discount]" value="0" class="item-discount">
                        </div>
                    </div>
                    <button type="button" data-add-contract-item class="rounded-lg border px-3 py-1.5 text-xs font-semibold hover:bg-slate-50">
                        + إضافة صنف إضافي لهذه المرحلة
                    </button>
                </article>
            </div>

            <!-- قالب إضافة مرحلة جديدة -->
            <template id="contract-beneficiary-template">
                <article class="space-y-4 rounded-xl border border-[var(--brand-border)] bg-white p-5" data-beneficiary data-beneficiary-index="__BEN__" data-next-item="1">
                    <div class="flex items-center justify-between border-b pb-2">
                        <h3 class="font-bold text-[var(--brand-navy)]">مرحلة / دفعة جديدة</h3>
                        <button type="button" data-remove-beneficiary class="text-xs text-red-700 hover:underline">حذف هذه المرحلة</button>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <label class="text-sm font-medium">المرحلة الدراسية
                            <select name="beneficiaries[__BEN__][grade_id]" class="grade-select mt-1 w-full rounded-lg border p-2">
                                <option value="">— اختر المرحلة —</option>
                                @foreach($schools as $school)
                                    @foreach($school->grades as $grade)
                                        <option value="{{ $grade->id }}" data-school-id="{{ $school->id }}">{{ $school->name }} · {{ $grade->name }}</option>
                                    @endforeach
                                @endforeach
                            </select>
                        </label>
                        <label class="text-sm font-medium">اسم الدفعة أو الطالب (اختياري)
                            <input name="beneficiaries[__BEN__][name]" maxlength="160" placeholder="مثلاً: طلبة الصف أو اسم الطالب" class="mt-1 w-full rounded-lg border p-2">
                        </label>
                        <label class="text-sm font-medium">ملف طفل مرتبط (اختياري)
                            <select name="beneficiaries[__BEN__][child_id]" class="mt-1 w-full rounded-lg border p-2">
                                <option value="">بدون ملف</option>
                                @foreach($guardians as $guardian)
                                    @foreach($guardian->children as $child)
                                        @if($child->school_id)
                                            <option value="{{ $child->id }}">{{ $child->school?->name }} · {{ $child->name }}</option>
                                        @endif
                                    @endforeach
                                @endforeach
                            </select>
                        </label>
                        <label class="text-sm font-medium">مرجع مدرسي (اختياري)
                            <input name="beneficiaries[__BEN__][source_reference]" maxlength="100" class="mt-1 w-full rounded-lg border p-2">
                        </label>
                    </div>
                    <div class="space-y-3" data-contract-items>
                        <div class="grid gap-2 rounded-lg bg-slate-50 p-3 sm:grid-cols-2 lg:grid-cols-6 items-end" data-contract-item-row>
                            <label class="text-xs font-medium lg:col-span-2">وصف اللبسة / الصنف
                                <input name="beneficiaries[__BEN__][items][0][description]" required maxlength="180" placeholder="مثلاً: زي مدرسي كامل" class="mt-1 w-full rounded border p-2">
                            </label>
                            <label class="text-xs font-medium">نوع الملابس
                                <select name="beneficiaries[__BEN__][items][0][garment_type_id]" class="mt-1 w-full rounded border p-2">
                                    <option value="">—</option>
                                    @foreach($garmentTypes as $type)
                                        <option value="{{ $type->id }}">{{ $type->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="text-xs font-medium">من الكتالوج
                                <select name="beneficiaries[__BEN__][items][0][inventory_variant_id]" class="mt-1 w-full rounded border p-2">
                                    <option value="">تحديد يدوي</option>
                                    @foreach($variants as $variant)
                                        <option value="{{ $variant->id }}">{{ $variant->garmentType->name }}{{ $variant->school ? ' · '.$variant->school->name : '' }}{{ $variant->size ? ' · '.$variant->size : '' }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="text-xs font-medium">الكمية (كم لبسة)
                                <input name="beneficiaries[__BEN__][items][0][quantity]" required type="number" min="1" max="500" value="1" class="item-quantity mt-1 w-full rounded border p-2 font-bold text-center">
                            </label>
                            <label class="text-xs font-medium">سعر الوحدة ج.س
                                <input name="beneficiaries[__BEN__][items][0][unit_price]" required inputmode="decimal" placeholder="0.00" class="item-unit-price mt-1 w-full rounded border p-2">
                            </label>
                            <input type="hidden" name="beneficiaries[__BEN__][items][0][discount]" value="0" class="item-discount">
                        </div>
                    </div>
                    <button type="button" data-add-contract-item class="rounded-lg border px-3 py-1.5 text-xs font-semibold hover:bg-slate-50">
                        + إضافة صنف إضافي لهذه المرحلة
                    </button>
                </article>
            </template>

            <!-- قالب إضافة صنف إضافي لنفس المرحلة -->
            <template id="contract-item-template">
                <div class="grid gap-2 rounded-lg border-t bg-slate-50 p-3 sm:grid-cols-2 lg:grid-cols-6 items-end" data-contract-item-row>
                    <label class="text-xs font-medium lg:col-span-2">وصف اللبسة / الصنف
                        <input name="beneficiaries[__BEN__][items][__ITEM__][description]" required maxlength="180" class="mt-1 w-full rounded border p-2">
                    </label>
                    <label class="text-xs font-medium">نوع الملابس
                        <select name="beneficiaries[__BEN__][items][__ITEM__][garment_type_id]" class="mt-1 w-full rounded border p-2">
                            <option value="">—</option>
                            @foreach($garmentTypes as $type)
                                <option value="{{ $type->id }}">{{ $type->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="text-xs font-medium">من الكتالوج
                        <select name="beneficiaries[__BEN__][items][__ITEM__][inventory_variant_id]" class="mt-1 w-full rounded border p-2">
                            <option value="">تحديد يدوي</option>
                            @foreach($variants as $variant)
                                <option value="{{ $variant->id }}">{{ $variant->garmentType->name }}{{ $variant->school ? ' · '.$variant->school->name : '' }}{{ $variant->size ? ' · '.$variant->size : '' }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="text-xs font-medium">الكمية
                        <input name="beneficiaries[__BEN__][items][__ITEM__][quantity]" required type="number" min="1" max="500" value="1" class="item-quantity mt-1 w-full rounded border p-2 font-bold text-center">
                    </label>
                    <label class="text-xs font-medium">سعر الوحدة ج.س
                        <input name="beneficiaries[__BEN__][items][__ITEM__][unit_price]" required inputmode="decimal" class="item-unit-price mt-1 w-full rounded border p-2">
                    </label>
                    <input type="hidden" name="beneficiaries[__BEN__][items][__ITEM__][discount]" value="0" class="item-discount">
                </div>
            </template>
        </section>

        <!-- قسم السداد المالي (دفعوا كم ومتبقي كم) -->
        <section class="space-y-4 rounded-xl border border-[var(--brand-border)] bg-white p-5">
            <div class="border-b pb-3">
                <h2 class="text-lg font-bold text-[var(--brand-navy)]">الدفعة المقدمة / السداد المبدئي (اختياري)</h2>
                <p class="text-xs text-[var(--brand-muted)]">إذا دفعت المدرسة عربوناً أو دفعة مقدماً، سجّلها هنا ليتم خصمها مباشرة ويظهر المتبقي في سجل العقد.</p>
            </div>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <label class="text-sm font-semibold">المبلغ المدفوع ج.س (دفعوا كم)
                    <input id="initial-payment-amount" name="initial_payment[amount]" type="number" step="0.01" min="0" placeholder="0.00" value="{{ old('initial_payment.amount') }}" class="mt-1 w-full rounded-lg border p-2.5 font-bold text-emerald-700">
                </label>
                <label class="text-sm font-semibold">طريقة الدفع
                    <select name="initial_payment[method]" class="mt-1 w-full rounded-lg border p-2.5">
                        <option value="cash" @selected(old('initial_payment.method') === 'cash')>نقداً (إيصال استلام فوري)</option>
                        <option value="bank_transfer" @selected(old('initial_payment.method') === 'bank_transfer')>تحويل بنكي (بانتظار التحقق)</option>
                    </select>
                </label>
                <label class="text-sm font-semibold">تاريخ الدفع
                    <input type="datetime-local" name="initial_payment[paid_at]" value="{{ old('initial_payment.paid_at', now()->format('Y-m-d\TH:i')) }}" class="mt-1 w-full rounded-lg border p-2.5">
                </label>
                <label class="text-sm font-semibold">رقم العملية / الإشعار
                    <input name="initial_payment[transaction_reference]" maxlength="120" placeholder="رقم التحويل أو الإشعار" value="{{ old('initial_payment.transaction_reference') }}" class="mt-1 w-full rounded-lg border p-2.5">
                </label>
                <label class="text-sm font-semibold sm:col-span-2">إرفاق إشعار الدفع (اختياري)
                    <input type="file" name="initial_payment_evidence" accept=".pdf,.jpg,.jpeg,.png" class="mt-1 w-full rounded-lg border p-2">
                </label>
                <label class="text-sm font-semibold sm:col-span-2">ملاحظات على الدفعة
                    <input name="initial_payment[notes]" maxlength="2000" placeholder="مثلاً: دفعة أولى بموجب شيك أو تحويل بنكك..." value="{{ old('initial_payment.notes') }}" class="mt-1 w-full rounded-lg border p-2.5">
                </label>
            </div>
        </section>

        <div class="flex flex-wrap gap-3">
            <button type="submit" class="rounded-lg bg-[var(--brand-navy)] px-6 py-3 font-semibold text-white shadow hover:opacity-95">
                حفظ العقد والطلب الجماعي
            </button>
            <a href="{{ route('orders.index') }}" class="rounded-lg border px-5 py-3 text-slate-700 hover:bg-slate-50">
                إلغاء
            </a>
        </div>
    </form>
</div>
@endsection
