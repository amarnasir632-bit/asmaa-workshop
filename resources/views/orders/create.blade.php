@extends('layouts.app')
@section('title', 'إنشاء طلب')
@section('content')
<div class="mx-auto max-w-5xl space-y-5">
    <header>
        <a href="{{ route('orders.index') }}" class="text-xs sm:text-sm font-semibold text-[var(--brand-navy)] underline">العودة للطلبات</a>
        <h1 class="mt-1 text-xl sm:text-2xl font-bold text-[var(--brand-navy)]">إنشاء طلب جديد</h1>
        <p class="mt-1 text-xs sm:text-sm text-[var(--brand-muted)]">عند اختيار الصنف، يتم تعبئة السعر تلقائياً مع إمكانية تعديله يدوياً وحساب الإجمالي فوراً.</p>
    </header>

    @if ($errors->any())
        <div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800 font-medium">
            <ul class="list-inside list-disc">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('orders.store') }}" id="order-create-form" class="space-y-5">
        @csrf
        <section class="grid gap-4 rounded-xl border border-[var(--brand-border)] bg-white p-4 sm:p-5 shadow-xs sm:grid-cols-2">
            <label class="text-xs sm:text-sm font-semibold text-slate-700">نوع الطلب <span class="text-red-600">*</span>
                <select id="order-type" name="type" required class="mt-1 w-full rounded-lg border border-slate-300 p-2.5 text-sm">
                    <option value="custom">تفصيل جديد</option>
                    <option value="alteration">تعديل أو إصلاح</option>
                    <option value="ready">بيع ملابس جاهزة من المخزون</option>
                </select>
                <span class="mt-1 block text-xs text-[var(--brand-muted)]">في البيع الجاهز، يخصم النظام الكمية من المتاح عند حفظ الطلب.</span>
            </label>
            <label class="text-xs sm:text-sm font-semibold text-slate-700">ولي الأمر <span class="text-red-600">*</span>
                <select name="guardian_id" id="guardian-select" required class="mt-1 w-full rounded-lg border border-slate-300 p-2.5 text-sm">
                    <option value="">اختر ملف ولي الأمر</option>
                    @foreach($guardians as $guardian)
                        <option value="{{ $guardian->id }}" @selected(old('guardian_id', request('guardian_id')) == $guardian->id)>
                            {{ $guardian->name }} — {{ $guardian->phone }}
                        </option>
                    @endforeach
                </select>
            </label>
            <label class="text-xs sm:text-sm font-semibold text-slate-700">موعد الإنجاز والتسليم المتوقع
                <input type="date" name="due_on" min="{{ now(config('app.display_timezone'))->toDateString() }}" value="{{ old('due_on') }}" class="mt-1 w-full rounded-lg border border-slate-300 p-2.5 text-sm">
            </label>
            <label class="text-xs sm:text-sm font-semibold text-slate-700 sm:col-span-2">ملاحظات تشغيلية
                <textarea name="notes" maxlength="3000" rows="2" placeholder="أي ملاحظات خاصة بالتفصيل أو الاستلام..." class="mt-1 w-full rounded-lg border border-slate-300 p-2.5 text-sm">{{ old('notes') }}</textarea>
            </label>
        </section>

        <!-- بنود الطلب -->
        <section class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 class="text-base sm:text-lg font-bold text-slate-900">بنود الملابس والقطع</h2>
                    <p class="text-xs text-slate-500">اختر الصنف لتعبئة السعر تلقائياً، ويمكنك إضافة بنود متعددة في نفس الطلب.</p>
                </div>
                <button type="button" id="add-order-item" class="rounded-xl border border-[var(--brand-navy)] bg-white px-4 py-2 text-sm font-bold text-[var(--brand-navy)] hover:bg-slate-50 transition min-h-[44px]">
                    + إضافة بند آخر
                </button>
            </div>

            <div id="order-items" data-next-index="1" class="space-y-4">
                <article class="order-item rounded-xl border border-[var(--brand-border)] bg-white p-4 sm:p-5 shadow-xs" data-item>
                    <div class="mb-3 flex items-center justify-between border-b pb-2">
                        <h3 class="font-bold text-slate-900 text-sm sm:text-base">البند 1</h3>
                        <div class="text-xs font-bold text-[var(--brand-navy)]" data-line-total-preview>
                            الإجمالي: <span class="total-val">0.00</span> ج.س
                        </div>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <label class="text-xs sm:text-sm font-semibold text-slate-700">الطفل (اختياري)
                            <select name="items[0][child_id]" class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm">
                                <option value="">بدون تحديد طفل</option>
                                @foreach($guardians as $guardian)
                                    @foreach($guardian->children as $child)
                                        <option value="{{ $child->id }}" data-guardian="{{ $guardian->id }}">{{ $guardian->name }} — {{ $child->name }}</option>
                                    @endforeach
                                @endforeach
                            </select>
                        </label>
                        <label class="text-xs sm:text-sm font-semibold text-slate-700">الصنف من الكتالوج (للبيع الجاهز إلزامي)
                            <select data-ready-stock-select data-variant-select name="items[0][inventory_variant_id]" class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm">
                                <option value="" data-price="0.00">اختر صنفاً من الكتالوج</option>
                                @foreach($variants as $variant)
                                    @php($available = $variant->balance?->quantity_available ?? 0)
                                    <option value="{{ $variant->id }}" data-price="{{ $variant->default_price ?? '0.00' }}" @disabled($available < 1)>
                                        {{ $variant->garmentType->name }}{{ $variant->school ? ' · '.$variant->school->name : '' }}{{ $variant->grade ? ' · '.$variant->grade->name : '' }}{{ $variant->size ? ' · '.$variant->size : '' }}{{ $variant->color ? ' · '.$variant->color : '' }} · المتاح {{ $available }}
                                    </option>
                                @endforeach
                            </select>
                        </label>
                        <label class="text-xs sm:text-sm font-semibold text-slate-700">نوع الملابس (اختياري)
                            <select name="items[0][garment_type_id]" class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm">
                                <option value="">—</option>
                                @foreach($garmentTypes as $type)
                                    <option value="{{ $type->id }}">{{ $type->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="text-xs sm:text-sm font-semibold text-slate-700">وصف البند <span class="text-red-600">*</span>
                            <input name="items[0][description]" required maxlength="180" placeholder="مثال: قميص أبيض تفصيل خاص..." class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm">
                        </label>
                        <label class="text-xs sm:text-sm font-semibold text-slate-700">الكمية <span class="text-red-600">*</span>
                            <input name="items[0][quantity]" required type="number" min="1" max="500" value="1" class="item-qty mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm">
                        </label>
                        <label class="text-xs sm:text-sm font-semibold text-slate-700">سعر الوحدة (ج.س) <span class="text-red-600">*</span>
                            <input name="items[0][unit_price]" required inputmode="decimal" placeholder="0.00" class="item-price mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm">
                        </label>
                        <label class="text-xs sm:text-sm font-semibold text-slate-700">خصم البند (ج.س)
                            <input name="items[0][discount]" inputmode="decimal" value="0.00" class="item-discount mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm">
                        </label>
                    </div>
                    <details class="mt-4 rounded-lg border border-slate-200 p-3 bg-slate-50/50">
                        <summary class="cursor-pointer text-xs sm:text-sm font-semibold text-[var(--brand-navy)]">مقاسات هذا البند (اختياري)</summary>
                        <p class="my-2 text-xs text-[var(--brand-muted)]">إذا تركتها فارغة مع اختيار طفل، تُنسخ المقاسات الحالية المسجلة له تلقائياً.</p>
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
                            @foreach(['shoulder_cm'=>'الكتف','chest_cm'=>'الصدر','sleeve_cm'=>'الكم','waist_circumference_cm'=>'محيط الخصر','length_cm'=>'الطول'] as $field=>$label)
                                <label class="text-xs text-slate-600">{{ $label }} (سم)
                                    <input name="items[0][measurements][{{ $field }}]" type="number" min="0.01" max="500" step="0.01" class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm bg-white">
                                </label>
                            @endforeach
                        </div>
                    </details>
                </article>
            </div>

            <!-- قالب البند الإضافي -->
            <template id="order-item-template">
                <article class="order-item rounded-xl border border-[var(--brand-border)] bg-white p-4 sm:p-5 shadow-xs" data-item>
                    <div class="mb-3 flex items-center justify-between border-b pb-2">
                        <h3 class="font-bold text-slate-900 text-sm sm:text-base">بند جديد</h3>
                        <div class="flex items-center gap-3">
                            <div class="text-xs font-bold text-[var(--brand-navy)]" data-line-total-preview>
                                الإجمالي: <span class="total-val">0.00</span> ج.س
                            </div>
                            <button type="button" data-remove-item class="text-xs font-semibold text-red-600 hover:text-red-800 underline">
                                حذف البند
                            </button>
                        </div>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <label class="text-xs sm:text-sm font-semibold text-slate-700">الطفل (اختياري)
                            <select name="items[__INDEX__][child_id]" class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm">
                                <option value="">بدون تحديد طفل</option>
                                @foreach($guardians as $guardian)
                                    @foreach($guardian->children as $child)
                                        <option value="{{ $child->id }}">{{ $guardian->name }} — {{ $child->name }}</option>
                                    @endforeach
                                @endforeach
                            </select>
                        </label>
                        <label class="text-xs sm:text-sm font-semibold text-slate-700">الصنف من الكتالوج (للبيع الجاهز إلزامي)
                            <select data-ready-stock-select data-variant-select name="items[__INDEX__][inventory_variant_id]" class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm">
                                <option value="" data-price="0.00">اختر صنفاً من الكتالوج</option>
                                @foreach($variants as $variant)
                                    @php($available = $variant->balance?->quantity_available ?? 0)
                                    <option value="{{ $variant->id }}" data-price="{{ $variant->default_price ?? '0.00' }}" @disabled($available < 1)>
                                        {{ $variant->garmentType->name }}{{ $variant->school ? ' · '.$variant->school->name : '' }}{{ $variant->size ? ' · '.$variant->size : '' }} · المتاح {{ $available }}
                                    </option>
                                @endforeach
                            </select>
                        </label>
                        <label class="text-xs sm:text-sm font-semibold text-slate-700">نوع الملابس
                            <select name="items[__INDEX__][garment_type_id]" class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm">
                                <option value="">—</option>
                                @foreach($garmentTypes as $type)
                                    <option value="{{ $type->id }}">{{ $type->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="text-xs sm:text-sm font-semibold text-slate-700">وصف البند <span class="text-red-600">*</span>
                            <input name="items[__INDEX__][description]" required maxlength="180" placeholder="مثال: قميص أبيض تفصيل خاص..." class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm">
                        </label>
                        <label class="text-xs sm:text-sm font-semibold text-slate-700">الكمية <span class="text-red-600">*</span>
                            <input name="items[__INDEX__][quantity]" required type="number" min="1" max="500" value="1" class="item-qty mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm">
                        </label>
                        <label class="text-xs sm:text-sm font-semibold text-slate-700">سعر الوحدة (ج.س) <span class="text-red-600">*</span>
                            <input name="items[__INDEX__][unit_price]" required inputmode="decimal" placeholder="0.00" class="item-price mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm">
                        </label>
                        <label class="text-xs sm:text-sm font-semibold text-slate-700">خصم البند (ج.س)
                            <input name="items[__INDEX__][discount]" inputmode="decimal" value="0.00" class="item-discount mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm">
                        </label>
                    </div>
                    <details class="mt-4 rounded-lg border border-slate-200 p-3 bg-slate-50/50">
                        <summary class="cursor-pointer text-xs sm:text-sm font-semibold text-[var(--brand-navy)]">مقاسات هذا البند (اختياري)</summary>
                        <p class="my-2 text-xs text-[var(--brand-muted)]">إذا تركتها فارغة مع اختيار طفل، تُنسخ المقاسات الحالية المسجلة له تلقائياً.</p>
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
                            @foreach(['shoulder_cm'=>'الكتف','chest_cm'=>'الصدر','sleeve_cm'=>'الكم','waist_circumference_cm'=>'محيط الخصر','length_cm'=>'الطول'] as $field=>$label)
                                <label class="text-xs text-slate-600">{{ $label }} (سم)
                                    <input name="items[__INDEX__][measurements][{{ $field }}]" type="number" min="0.01" max="500" step="0.01" class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm bg-white">
                                </label>
                            @endforeach
                        </div>
                    </details>
                </article>
            </template>
        </section>

        <!-- بطاقة إجمالي الطلب المباشرة -->
        <section class="rounded-xl border border-[var(--brand-navy)] bg-blue-50/40 p-4 sm:p-5 flex flex-wrap items-center justify-between gap-3 shadow-xs">
            <div>
                <p class="text-xs text-slate-600">إجمالي قيمة الطلب التقديرية (تحتسب فوراً عند تغيير السعر أو الكمية)</p>
                <p class="text-2xl font-extrabold text-[var(--brand-navy)]" id="order-grand-total">0.00 <span class="text-sm font-normal">ج.س</span></p>
            </div>
            <div class="flex flex-wrap gap-3">
                <button class="rounded-xl bg-[var(--brand-navy)] px-6 py-3 font-bold text-white shadow-xs hover:opacity-95 transition min-h-[48px] active:scale-[0.99]">
                    حفظ الطلب
                </button>
                <a href="{{ route('orders.index') }}" class="rounded-xl border border-slate-300 bg-white px-5 py-3 font-semibold text-slate-700 hover:bg-slate-50 transition min-h-[48px] flex items-center justify-center">
                    إلغاء
                </a>
            </div>
        </section>
    </form>
</div>

<script>
    function calculateTotals() {
        let grandTotal = 0;
        document.querySelectorAll('#order-items [data-item]').forEach(item => {
            const qtyInput = item.querySelector('.item-qty');
            const priceInput = item.querySelector('.item-price');
            const discountInput = item.querySelector('.item-discount');
            const previewEl = item.querySelector('[data-line-total-preview] .total-val');

            const qty = Math.max(0, parseFloat(qtyInput?.value || 0));
            const price = Math.max(0, parseFloat(priceInput?.value || 0));
            const discount = Math.max(0, parseFloat(discountInput?.value || 0));

            const lineTotal = Math.max(0, (qty * price) - discount);
            grandTotal += lineTotal;

            if (previewEl) {
                previewEl.textContent = lineTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
        });

        const grandEl = document.getElementById('order-grand-total');
        if (grandEl) {
            grandEl.innerHTML = grandTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' <span class="text-sm font-normal">ج.س</span>';
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const orderContainer = document.getElementById('order-items');
        if (!orderContainer) return;

        orderContainer.addEventListener('change', (e) => {
            const variantSelect = e.target.closest('[data-variant-select]');
            if (variantSelect) {
                const opt = variantSelect.options[variantSelect.selectedIndex];
                const price = opt?.dataset.price;
                const row = variantSelect.closest('[data-item]');
                const priceInput = row?.querySelector('.item-price');
                if (price && parseFloat(price) > 0 && priceInput && (!priceInput.value || parseFloat(priceInput.value) === 0)) {
                    priceInput.value = parseFloat(price).toFixed(2);
                }
            }
            calculateTotals();
        });

        orderContainer.addEventListener('input', calculateTotals);
        calculateTotals();
    });
</script>
@endsection
