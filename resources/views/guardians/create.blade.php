@extends('layouts.app')
@section('title', 'إضافة ولي أمر جديد')
@section('content')
    <div class="mx-auto max-w-4xl">
        <div class="mb-5 flex items-center justify-between">
            <h1 class="text-2xl font-bold text-[var(--brand-navy)]">إضافة ولي أمر جديد</h1>
            <a href="{{ route('guardians.index') }}" class="rounded-lg border border-[var(--brand-border)] bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">العودة للقائمة</a>
        </div>

        <form method="POST" action="{{ route('guardians.store') }}" class="space-y-6">
            @csrf

            <!-- قسم بيانات ولي الأمر الأساسية -->
            <div class="rounded-xl border border-[var(--brand-border)] bg-white p-5 shadow-sm sm:p-6">
                <h2 class="mb-4 flex items-center gap-2 text-base font-bold text-[var(--brand-navy)]">
                    <svg class="h-5 w-5 text-[var(--brand-gold)]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    بيانات ولي الأمر
                </h2>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="name" class="mb-1 block text-sm font-semibold text-slate-800">اسم ولي الأمر <span class="text-red-600" aria-hidden="true">*</span></label>
                        <input id="name" name="name" value="{{ old('name') }}" required maxlength="160" placeholder="مثال: أحمد محمد علي" class="w-full rounded-lg border border-[var(--brand-border)] px-3 py-2.5 text-sm focus:border-[var(--brand-navy)] focus:ring-1 focus:ring-[var(--brand-navy)]">
                        @error('name') <p class="mt-1 text-xs text-red-700" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="phone" class="mb-1 block text-sm font-semibold text-slate-800">رقم الهاتف الأساسي <span class="text-red-600" aria-hidden="true">*</span></label>
                        <input id="phone" name="phone" type="tel" dir="ltr" value="{{ old('phone') }}" required maxlength="40" placeholder="09xxxxxxxx" class="w-full rounded-lg border border-[var(--brand-border)] px-3 py-2.5 text-sm focus:border-[var(--brand-navy)] focus:ring-1 focus:ring-[var(--brand-navy)]">
                        @error('phone') <p class="mt-1 text-xs text-red-700" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="alternate_phone" class="mb-1 block text-sm font-semibold text-slate-800">رقم هاتف بديل (اختياري)</label>
                        <input id="alternate_phone" name="alternate_phone" type="tel" dir="ltr" value="{{ old('alternate_phone') }}" maxlength="40" placeholder="01xxxxxxxx" class="w-full rounded-lg border border-[var(--brand-border)] px-3 py-2.5 text-sm focus:border-[var(--brand-navy)] focus:ring-1 focus:ring-[var(--brand-navy)]">
                        @error('alternate_phone') <p class="mt-1 text-xs text-red-700" role="alert">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="notes" class="mb-1 block text-sm font-semibold text-slate-800">ملاحظات عامة</label>
                        <input id="notes" name="notes" value="{{ old('notes') }}" maxlength="2000" placeholder="أي ملاحظات تخص السكن أو التوصيل" class="w-full rounded-lg border border-[var(--brand-border)] px-3 py-2.5 text-sm focus:border-[var(--brand-navy)] focus:ring-1 focus:ring-[var(--brand-navy)]">
                        @error('notes') <p class="mt-1 text-xs text-red-700" role="alert">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <!-- قسم إضافة الأبناء مباشرة في خطوة واحدة -->
            <div class="rounded-xl border border-[var(--brand-border)] bg-white p-5 shadow-sm sm:p-6">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-3">
                    <div>
                        <h2 class="flex items-center gap-2 text-base font-bold text-[var(--brand-navy)]">
                            <svg class="h-5 w-5 text-[var(--brand-gold)]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                            بيانات الأبناء (اختياري)
                        </h2>
                        <p class="text-xs text-[var(--brand-muted)]">يمكنك إضافة ابن أو أكثر ومقاساتهم الآن بضغطة واحدة، أو إضافتهم لاحقاً.</p>
                    </div>
                    <button type="button" id="btn-add-child" class="inline-flex min-h-[44px] items-center gap-1.5 rounded-lg border border-[var(--brand-navy)] bg-slate-50 px-3.5 py-2 text-xs font-bold text-[var(--brand-navy)] hover:bg-slate-100">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        + إضافة ابن آخر
                    </button>
                </div>

                <div id="children-container" class="space-y-4">
                    <!-- سيتم توليد بطاقات الأبناء هنا عبر الجافاسكربت -->
                </div>

                <p id="no-children-hint" class="rounded-lg border border-dashed border-slate-200 p-4 text-center text-xs text-[var(--brand-muted)]">
                    لم تتم إضافة أبناء بعد. اضغط على زر "+ إضافة ابن آخر" أعلاه إذا أردت تسجيل الأبناء الآن.
                </p>
            </div>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center">
                <button type="submit" class="inline-flex min-h-[48px] w-full items-center justify-center rounded-lg bg-[var(--brand-navy)] px-6 py-3 font-bold text-white shadow hover:opacity-95 sm:w-auto">
                    حفظ ولي الأمر والأبناء
                </button>
                <a href="{{ route('guardians.index') }}" class="inline-flex min-h-[44px] w-full items-center justify-center rounded-lg border border-[var(--brand-border)] bg-white px-5 py-2.5 font-semibold text-slate-700 hover:bg-slate-50 sm:w-auto">
                    إلغاء
                </a>
            </div>
        </form>
    </div>

    <!-- قالب كارت الطفل المستنسخ -->
    <template id="child-card-template">
        <div class="child-card relative rounded-xl border border-slate-200 bg-slate-50/70 p-4 transition-all">
            <div class="mb-3 flex items-center justify-between border-b border-slate-200 pb-2">
                <span class="child-badge inline-flex items-center gap-1 rounded bg-[var(--brand-navy)]/10 px-2.5 py-1 text-xs font-bold text-[var(--brand-navy)]">
                    الابن رقم <span class="child-number">1</span>
                </span>
                <button type="button" class="btn-remove-child inline-flex min-h-[36px] items-center gap-1 text-xs font-semibold text-red-600 hover:text-red-800">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    حذف هذا الابن
                </button>
            </div>

            <div class="grid gap-3 sm:grid-cols-3">
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-700">اسم الطفل <span class="text-red-600">*</span></label>
                    <input type="text" name="children[INDEX][name]" required placeholder="اسم الطفل" class="child-name-input w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-[var(--brand-navy)]">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-700">المدرسة</label>
                    <select name="children[INDEX][school_id]" class="child-school-select w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-[var(--brand-navy)]">
                        <option value="">اختر المدرسة (اختياري)</option>
                        @foreach ($schools as $school)
                            <option value="{{ $school->id }}">{{ $school->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold text-slate-700">المرحلة / الصف</label>
                    <select name="children[INDEX][grade_id]" class="child-grade-select w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-[var(--brand-navy)]">
                        <option value="">اختر المرحلة (اختياري)</option>
                        @foreach ($schools as $school)
                            @foreach ($school->grades as $grade)
                                <option value="{{ $grade->id }}" data-school="{{ $school->id }}">{{ $school->name }} — {{ $grade->name }}</option>
                            @endforeach
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- المقاسات الحالية (اختياري) -->
            <details class="mt-3 border-t border-slate-200/80 pt-2.5">
                <summary class="cursor-pointer text-xs font-bold text-[var(--brand-navy)] hover:underline">
                    + تسجيل المقاسات المبدئية (بالسنتيمتر - اختياري)
                </summary>
                <div class="mt-2.5 grid grid-cols-2 gap-2 sm:grid-cols-5">
                    <div>
                        <label class="mb-1 block text-[11px] text-slate-600">الكتف (سم)</label>
                        <input type="number" step="0.5" min="1" max="500" name="children[INDEX][shoulder_cm]" placeholder="سم" class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs text-center">
                    </div>
                    <div>
                        <label class="mb-1 block text-[11px] text-slate-600">الصدر (سم)</label>
                        <input type="number" step="0.5" min="1" max="500" name="children[INDEX][chest_cm]" placeholder="سم" class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs text-center">
                    </div>
                    <div>
                        <label class="mb-1 block text-[11px] text-slate-600">الكم (سم)</label>
                        <input type="number" step="0.5" min="1" max="500" name="children[INDEX][sleeve_cm]" placeholder="سم" class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs text-center">
                    </div>
                    <div>
                        <label class="mb-1 block text-[11px] text-slate-600">محيط الخصر (سم)</label>
                        <input type="number" step="0.5" min="1" max="500" name="children[INDEX][waist_circumference_cm]" placeholder="سم" class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs text-center">
                    </div>
                    <div>
                        <label class="mb-1 block text-[11px] text-slate-600">الطول (سم)</label>
                        <input type="number" step="0.5" min="1" max="500" name="children[INDEX][length_cm]" placeholder="سم" class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs text-center">
                    </div>
                </div>
            </details>
        </div>
    </template>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const container = document.getElementById('children-container');
            const template = document.getElementById('child-card-template');
            const btnAdd = document.getElementById('btn-add-child');
            const noChildrenHint = document.getElementById('no-children-hint');
            let childIndex = 0;

            function updateCardNumbers() {
                const cards = container.querySelectorAll('.child-card');
                cards.forEach((card, idx) => {
                    const badge = card.querySelector('.child-number');
                    if (badge) badge.textContent = idx + 1;
                });
                if (cards.length === 0) {
                    if (noChildrenHint) noChildrenHint.classList.remove('hidden');
                } else {
                    if (noChildrenHint) noChildrenHint.classList.add('hidden');
                }
            }

            function addChildCard() {
                const clone = template.content.cloneNode(true);
                const html = clone.firstElementChild.outerHTML.replaceAll('INDEX', childIndex);
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = html;
                const card = tempDiv.firstElementChild;

                // ربط زر الحذف
                const btnRemove = card.querySelector('.btn-remove-child');
                btnRemove.addEventListener('click', function () {
                    card.remove();
                    updateCardNumbers();
                });

                // تصفية المراحل بناء على المدرسة
                const schoolSelect = card.querySelector('.child-school-select');
                const gradeSelect = card.querySelector('.child-grade-select');
                if (schoolSelect && gradeSelect) {
                    schoolSelect.addEventListener('change', function () {
                        const selectedSchoolId = this.value;
                        const options = gradeSelect.querySelectorAll('option');
                        options.forEach(opt => {
                            if (!opt.value) return; // الخيار الافتراضي
                            const optSchool = opt.getAttribute('data-school');
                            if (!selectedSchoolId || optSchool === selectedSchoolId) {
                                opt.style.display = '';
                            } else {
                                opt.style.display = 'none';
                                if (opt.selected) gradeSelect.value = '';
                            }
                        });
                    });
                }

                container.appendChild(card);
                childIndex++;
                updateCardNumbers();

                const nameInput = card.querySelector('.child-name-input');
                if (nameInput) nameInput.focus();
            }

            btnAdd.addEventListener('click', addChildCard);
        });
    </script>
@endsection
