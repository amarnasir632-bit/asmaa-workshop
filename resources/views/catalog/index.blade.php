@extends('layouts.app')

@section('title', 'المدارس والأصناف')

@section('content')
<div class="mx-auto max-w-7xl space-y-6">
    <header class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <p class="text-xs sm:text-sm font-semibold text-[var(--brand-gold)]">بيانات مرجعية</p>
            <h1 class="mt-0.5 text-xl sm:text-2xl font-bold text-[var(--brand-navy)]">المدارس والأصناف</h1>
            <p class="mt-1 text-xs sm:text-sm text-[var(--brand-muted)]">إدارة المدارس ومراحلها، أصناف الزي، وتركيبات المقاسات والألوان.</p>
        </div>
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

    <!-- شريط التبويبات الثلاثة (Tabs) -->
    <div class="border-b border-slate-200">
        <nav class="flex gap-2 sm:gap-4 -mb-px overflow-x-auto" aria-label="أقسام الكتالوج">
            <button type="button" data-tab="schools" class="tab-btn inline-flex items-center gap-2 border-b-2 px-3 sm:px-4 py-3 text-sm font-bold border-[var(--brand-navy)] text-[var(--brand-navy)] transition">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                المدارس والمراحل الدراسية
            </button>
            <button type="button" data-tab="garments" class="tab-btn inline-flex items-center gap-2 border-b-2 px-3 sm:px-4 py-3 text-sm font-medium border-transparent text-slate-500 hover:text-slate-800 transition">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path></svg>
                الأصناف والأسعار
            </button>
            <button type="button" data-tab="variants" class="tab-btn inline-flex items-center gap-2 border-b-2 px-3 sm:px-4 py-3 text-sm font-medium border-transparent text-slate-500 hover:text-slate-800 transition">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>
                تركيبات القماش والمقاسات
            </button>
        </nav>
    </div>

    <!-- التبويب الأول: المدارس والمراحل الدراسية -->
    <div id="tab-schools" class="tab-pane space-y-6">
        @can('catalog.manage')
            <div class="flex flex-wrap gap-2">
                <button type="button" onclick="toggleSection('form-school-add')" class="rounded-xl bg-[var(--brand-navy)] px-4 py-2.5 text-sm font-bold text-white shadow-xs hover:opacity-95 transition min-h-[44px]">
                    + إضافة مدرسة جديدة
                </button>
                <button type="button" onclick="toggleSection('form-grade-add')" class="rounded-xl border border-[var(--brand-navy)] bg-white px-4 py-2.5 text-sm font-bold text-[var(--brand-navy)] hover:bg-slate-50 transition min-h-[44px]">
                    + إضافة مرحلة دراسية
                </button>
            </div>

            <!-- نموذج إضافة مدرسة (قابل للطي) -->
            <div id="form-school-add" class="hidden rounded-xl border border-slate-300 bg-white p-5 shadow-sm transition">
                <div class="flex items-center justify-between border-b pb-3 mb-4">
                    <h2 class="text-base font-bold text-slate-900">إضافة مدرسة جديدة</h2>
                    <button type="button" onclick="toggleSection('form-school-add')" class="text-xs text-slate-500 hover:text-slate-800">إغلاق ×</button>
                </div>
                <form method="POST" action="{{ route('catalog.schools.store') }}" class="grid gap-3 sm:grid-cols-2">
                    @csrf
                    <label class="text-xs sm:text-sm font-semibold">اسم المدرسة <span class="text-red-600">*</span>
                        <input name="name" required maxlength="180" value="{{ old('name') }}" placeholder="اسم المدرسة..." class="mt-1 w-full rounded-lg border p-2 text-sm">
                    </label>
                    <label class="text-xs sm:text-sm font-semibold">رقم التواصل (اختياري)
                        <input name="phone" maxlength="40" value="{{ old('phone') }}" placeholder="رقم الهاتف..." class="mt-1 w-full rounded-lg border p-2 text-sm">
                    </label>
                    <label class="text-xs sm:text-sm font-semibold sm:col-span-2">العنوان (اختياري)
                        <input name="address" maxlength="1000" value="{{ old('address') }}" placeholder="موقع المدرسة أو عنوانها..." class="mt-1 w-full rounded-lg border p-2 text-sm">
                    </label>
                    <div class="sm:col-span-2 pt-2">
                        <button class="rounded-lg bg-[var(--brand-navy)] px-5 py-2.5 font-bold text-white min-h-[44px]">حفظ المدرسة</button>
                    </div>
                </form>
            </div>

            <!-- نموذج إضافة مرحلة دراسية (قابل للطي) -->
            <div id="form-grade-add" class="hidden rounded-xl border border-slate-300 bg-white p-5 shadow-sm transition">
                <div class="flex items-center justify-between border-b pb-3 mb-4">
                    <h2 class="text-base font-bold text-slate-900">إضافة مرحلة دراسية لمدرسة</h2>
                    <button type="button" onclick="toggleSection('form-grade-add')" class="text-xs text-slate-500 hover:text-slate-800">إغلاق ×</button>
                </div>
                <form method="POST" action="{{ route('catalog.grades.store') }}" class="grid gap-3 sm:grid-cols-2">
                    @csrf
                    <label class="text-xs sm:text-sm font-semibold">المدرسة <span class="text-red-600">*</span>
                        <select name="school_id" required class="mt-1 w-full rounded-lg border p-2 text-sm">
                            <option value="">اختر المدرسة</option>
                            @foreach ($allSchools as $school)
                                <option value="{{ $school->id }}">{{ $school->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="text-xs sm:text-sm font-semibold">اسم المرحلة الدراسية <span class="text-red-600">*</span>
                        <input name="name" required maxlength="100" placeholder="مثال: الصف الأول أساس، روضة أولى..." class="mt-1 w-full rounded-lg border p-2 text-sm">
                    </label>
                    <div class="sm:col-span-2 pt-2">
                        <button class="rounded-lg bg-[var(--brand-navy)] px-5 py-2.5 font-bold text-white min-h-[44px]">إضافة المرحلة</button>
                    </div>
                </form>
            </div>
        @endcan

        <!-- قائمة المدارس والمراحل -->
        <section class="overflow-hidden rounded-xl border border-[var(--brand-border)] bg-white shadow-xs">
            <h2 class="border-b p-4 font-bold text-base text-slate-900">سجل المدارس والمراحل الدراسية</h2>
            <div class="divide-y divide-slate-100">
                @forelse ($schools as $school)
                    <article class="flex flex-col sm:flex-row sm:items-start justify-between gap-3 p-4 hover:bg-slate-50/60 transition">
                        <div>
                            <h3 class="font-bold text-base text-slate-900">
                                {{ $school->name }}
                                @unless($school->is_active)
                                    <span class="rounded bg-rose-100 px-2 py-0.5 text-xs text-rose-700 mr-1">(موقوفة)</span>
                                @endunless
                            </h3>
                            <p class="text-xs text-slate-500 mt-0.5">{{ $school->phone ?: 'لا يوجد رقم هاتف مسجل' }} {{ $school->address ? '· '.$school->address : '' }}</p>
                            <div class="mt-3">
                                <span class="text-xs font-semibold text-slate-700 block mb-1.5">المراحل الدراسية التابعة:</span>
                                <ul class="flex flex-wrap gap-1.5">
                                    @forelse ($school->grades as $grade)
                                        <li class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-800">
                                            {{ $grade->name }}
                                            @unless($grade->is_active)
                                                <span class="text-rose-600">(موقوفة)</span>
                                            @endunless
                                        </li>
                                    @empty
                                        <li class="text-xs text-slate-400">لا توجد مراحل مسجلة لهذه المدرسة بعد.</li>
                                    @endforelse
                                </ul>
                            </div>
                        </div>
                        @if($school->is_active && auth()->user()->can('catalog.manage'))
                            <form method="POST" action="{{ route('catalog.schools.deactivate', $school) }}" onsubmit="return confirm('هل أنت متأكد من إيقاف هذه المدرسة؟');">
                                @csrf
                                <button class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50 transition min-h-[38px]">
                                    إيقاف المدرسة
                                </button>
                            </form>
                        @endif
                    </article>
                @empty
                    <p class="p-8 text-center text-sm text-[var(--brand-muted)]">لا توجد مدارس مسجلة حتى الآن.</p>
                @endforelse
            </div>
            <div class="border-t p-3">{{ $schools->links() }}</div>
        </section>
    </div>

    <!-- التبويب الثاني: الأصناف والأسعار -->
    <div id="tab-garments" class="tab-pane hidden space-y-6">
        @can('catalog.manage')
            <div>
                <button type="button" onclick="toggleSection('form-garment-add')" class="rounded-xl bg-[var(--brand-navy)] px-4 py-2.5 text-sm font-bold text-white shadow-xs hover:opacity-95 transition min-h-[44px]">
                    + إضافة صنف ملابس جديد
                </button>
            </div>

            <!-- نموذج إضافة صنف ملابس (قابل للطي) -->
            <div id="form-garment-add" class="hidden rounded-xl border border-slate-300 bg-white p-5 shadow-sm transition">
                <div class="flex items-center justify-between border-b pb-3 mb-4">
                    <h2 class="text-base font-bold text-slate-900">إضافة صنف ملابس جديد</h2>
                    <button type="button" onclick="toggleSection('form-garment-add')" class="text-xs text-slate-500 hover:text-slate-800">إغلاق ×</button>
                </div>
                <form method="POST" action="{{ route('catalog.garment-types.store') }}" class="grid gap-3 sm:grid-cols-2">
                    @csrf
                    <label class="text-xs sm:text-sm font-semibold sm:col-span-2">اسم صنف الملابس <span class="text-red-600">*</span>
                        <input name="name" required maxlength="120" placeholder="مثال: قميص مدرسي، مريول، بنطلون..." class="mt-1 w-full rounded-lg border p-2 text-sm">
                    </label>
                    <div class="flex flex-wrap gap-4 text-sm sm:col-span-2 pt-1">
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="requires_size" value="1" checked class="rounded">
                            <span>يتطلب تحديد مقاس</span>
                        </label>
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="requires_color" value="1" class="rounded">
                            <span>يتطلب تحديد لون</span>
                        </label>
                    </div>
                    <div class="sm:col-span-2 pt-2">
                        <button class="rounded-lg bg-[var(--brand-navy)] px-5 py-2.5 font-bold text-white min-h-[44px]">حفظ الصنف</button>
                    </div>
                </form>
            </div>
        @endcan

        <!-- شبكة أنواع الملابس -->
        <section class="rounded-xl border border-[var(--brand-border)] bg-white p-5 shadow-xs">
            <h2 class="mb-4 font-bold text-base text-slate-900">أنواع الملابس المعتمدة</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                @foreach($garmentTypes as $type)
                    <article class="flex items-center justify-between rounded-xl border border-slate-200 p-3.5 hover:bg-slate-50 transition">
                        <div>
                            <p class="font-bold text-sm text-slate-900">{{ $type->name }}</p>
                            <p class="text-xs text-slate-500 mt-1">
                                {{ $type->requires_size ? 'يحتاج مقاساً' : 'بدون مقاس' }} · {{ $type->requires_color ? 'يحتاج لوناً' : 'بدون لون' }}
                            </p>
                        </div>
                        @if($type->is_active && auth()->user()->can('catalog.manage'))
                            <form method="POST" action="{{ route('catalog.garment-types.deactivate', $type) }}" onsubmit="return confirm('إيقاف هذا الصنف؟');">
                                @csrf
                                <button class="rounded px-2 py-1 text-xs text-rose-700 hover:bg-rose-50 font-semibold">إيقاف</button>
                            </form>
                        @elseif(!$type->is_active)
                            <span class="rounded bg-rose-100 px-2 py-0.5 text-xs text-rose-700 font-semibold">موقوف</span>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>
    </div>

    <!-- التبويب الثالث: تركيبات القماش والمقاسات -->
    <div id="tab-variants" class="tab-pane hidden space-y-6">
        @can('catalog.manage')
            <div>
                <button type="button" onclick="toggleSection('form-variant-add')" class="rounded-xl bg-[var(--brand-navy)] px-4 py-2.5 text-sm font-bold text-white shadow-xs hover:opacity-95 transition min-h-[44px]">
                    + إضافة تركيبة مقاس ولون جديدة
                </button>
            </div>

            <!-- نموذج إضافة تركيبة صنف (قابل للطي) -->
            <div id="form-variant-add" class="hidden rounded-xl border border-slate-300 bg-white p-5 shadow-sm transition">
                <div class="flex items-center justify-between border-b pb-3 mb-4">
                    <h2 class="text-base font-bold text-slate-900">إضافة تركيبة صنف (مقاس / لون / مدرسة)</h2>
                    <button type="button" onclick="toggleSection('form-variant-add')" class="text-xs text-slate-500 hover:text-slate-800">إغلاق ×</button>
                </div>
                <form method="POST" action="{{ route('catalog.variants.store') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @csrf
                    <label class="text-xs sm:text-sm font-semibold">نوع الملابس <span class="text-red-600">*</span>
                        <select name="garment_type_id" required class="mt-1 w-full rounded-lg border p-2 text-sm">
                            <option value="">اختر صنف الملابس</option>
                            @foreach ($garmentTypes as $type)
                                <option value="{{ $type->id }}">{{ $type->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="text-xs sm:text-sm font-semibold">المدرسة (اختياري)
                        <select name="school_id" class="mt-1 w-full rounded-lg border p-2 text-sm">
                            <option value="">صنف عام (لكافة العملاء والمدارس)</option>
                            @foreach ($allSchools as $school)
                                <option value="{{ $school->id }}">{{ $school->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="text-xs sm:text-sm font-semibold">المرحلة الدراسية (اختياري)
                        <select name="grade_id" class="mt-1 w-full rounded-lg border p-2 text-sm">
                            <option value="">كافة المراحل</option>
                            @foreach ($allSchools as $school)
                                @foreach ($school->grades as $grade)
                                    <option value="{{ $grade->id }}">{{ $school->name }} — {{ $grade->name }}</option>
                                @endforeach
                            @endforeach
                        </select>
                    </label>
                    <label class="text-xs sm:text-sm font-semibold">المقاس
                        <input name="size" maxlength="60" placeholder="مثال: S, M, L, 32, 34..." class="mt-1 w-full rounded-lg border p-2 text-sm">
                    </label>
                    <label class="text-xs sm:text-sm font-semibold">اللون
                        <input name="color" maxlength="80" placeholder="مثال: أزرق كحلي، أبيض، رمادي..." class="mt-1 w-full rounded-lg border p-2 text-sm">
                    </label>
                    <label class="text-xs sm:text-sm font-semibold">رمز الصنف SKU (اختياري)
                        <input name="sku" maxlength="80" placeholder="مثال: SHIRT-NVY-M" class="mt-1 w-full rounded-lg border p-2 text-sm">
                    </label>
                    <label class="text-xs sm:text-sm font-semibold sm:col-span-2 lg:col-span-3">وصف التركيبة (اختياري)
                        <input name="description" maxlength="180" placeholder="ملاحظات حول القماش أو التفصيل..." class="mt-1 w-full rounded-lg border p-2 text-sm">
                    </label>
                    <div class="sm:col-span-2 lg:col-span-3 pt-2">
                        <button class="rounded-lg bg-[var(--brand-navy)] px-5 py-2.5 font-bold text-white min-h-[44px]">حفظ التركيبة</button>
                    </div>
                </form>
            </div>
        @endcan

        <!-- قائمة تركيبات الملابس -->
        <section class="overflow-hidden rounded-xl border border-[var(--brand-border)] bg-white shadow-xs">
            <h2 class="border-b p-4 font-bold text-base text-slate-900">تركيبات الأصناف المسجلة</h2>
            <div class="divide-y divide-slate-100">
                @forelse ($variants as $variant)
                    <article class="flex flex-col sm:flex-row sm:items-start justify-between gap-3 p-4 hover:bg-slate-50/60 transition">
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="font-bold text-base text-slate-900">{{ $variant->garmentType->name }}</h3>
                                @unless($variant->is_active)
                                    <span class="rounded bg-rose-100 px-2 py-0.5 text-xs text-rose-700">(موقوف)</span>
                                @endunless
                            </div>
                            <p class="text-xs text-slate-600 mt-1">
                                {{ $variant->school?->name ?? 'صنف عام' }}
                                {{ $variant->grade ? ' · '.$variant->grade->name : '' }}
                                {{ $variant->size ? ' · المقاس: '.$variant->size : '' }}
                                {{ $variant->color ? ' · اللون: '.$variant->color : '' }}
                            </p>
                            @if($variant->sku)
                                <p class="text-[11px] text-slate-400 mt-0.5">رمز SKU: {{ $variant->sku }}</p>
                            @endif
                        </div>
                        @if($variant->is_active && auth()->user()->can('catalog.manage'))
                            <form method="POST" action="{{ route('catalog.variants.deactivate', $variant) }}" onsubmit="return confirm('إيقاف هذه التركيبة؟');">
                                @csrf
                                <button class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50 transition min-h-[38px]">
                                    إيقاف
                                </button>
                            </form>
                        @endif
                    </article>
                @empty
                    <p class="p-8 text-center text-sm text-[var(--brand-muted)]">لا توجد تركيبات مسجلة.</p>
                @endforelse
            </div>
            <div class="border-t p-3">{{ $variants->links() }}</div>
        </section>
    </div>
</div>

<script>
    function toggleSection(id) {
        const el = document.getElementById(id);
        if (el) {
            el.classList.toggle('hidden');
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const tabBtns = document.querySelectorAll('.tab-btn');
        const tabPanes = document.querySelectorAll('.tab-pane');

        function switchTab(tabId) {
            tabBtns.forEach(btn => {
                const isActive = btn.dataset.tab === tabId;
                if (isActive) {
                    btn.classList.add('border-[var(--brand-navy)]', 'text-[var(--brand-navy)]', 'font-bold');
                    btn.classList.remove('border-transparent', 'text-slate-500');
                } else {
                    btn.classList.remove('border-[var(--brand-navy)]', 'text-[var(--brand-navy)]', 'font-bold');
                    btn.classList.add('border-transparent', 'text-slate-500');
                }
            });

            tabPanes.forEach(pane => {
                if (pane.id === 'tab-' + tabId) {
                    pane.classList.remove('hidden');
                } else {
                    pane.classList.add('hidden');
                }
            });
        }

        tabBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                switchTab(btn.dataset.tab);
            });
        });
    });
</script>
@endsection

