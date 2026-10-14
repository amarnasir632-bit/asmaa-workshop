@extends('layouts.app')
@section('title', 'ملف ولي الأمر')
@section('content')
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <a class="text-sm font-semibold text-[var(--brand-navy)] underline" href="{{ route('guardians.index') }}">العودة للقائمة</a>
            <h1 class="mt-2 text-2xl font-bold text-[var(--brand-navy)]">{{ $guardian->name }}</h1>
            <p class="mt-1 text-sm text-[var(--brand-muted)]">رقم التواصل: <span dir="ltr">{{ $guardian->phone }}</span></p>
        </div>
        <div class="flex flex-wrap gap-2">
            @can('customers.update')
                <a href="{{ route('guardians.edit', $guardian) }}" class="rounded-lg border border-[var(--brand-border)] bg-white px-4 py-2 font-semibold">تعديل البيانات</a>
                <form method="POST" action="{{ route('guardians.archive', $guardian) }}" onsubmit="return confirm('أرشفة الملف؟ ستبقى السجلات المرتبطة محفوظة.');">
                    @csrf
                    <button class="rounded-lg border border-red-200 bg-white px-4 py-2 font-semibold text-red-800">أرشفة</button>
                </form>
            @endcan
        </div>
    </div>

    @if ($guardian->alternate_phone || $guardian->notes)
        <section class="mb-6 rounded-xl border border-[var(--brand-border)] bg-white p-5">
            @if ($guardian->alternate_phone)<p class="text-sm">رقم بديل: <span dir="ltr">{{ $guardian->alternate_phone }}</span></p>@endif
            @if ($guardian->notes)<p class="mt-2 whitespace-pre-line text-sm">{{ $guardian->notes }}</p>@endif
        </section>
    @endif

    <section class="mb-7">
        <h2 class="mb-3 text-lg font-bold">الأطفال المسجلون ({{ number_format($guardian->children->count()) }})</h2>
        <div class="grid gap-4 xl:grid-cols-2">
            @forelse ($guardian->children as $child)
                <article class="rounded-xl border border-[var(--brand-border)] bg-white p-5">
                    <div class="mb-4 flex items-start justify-between gap-3">
                        <div>
                            <h3 class="font-bold">{{ $child->name }}</h3>
                            <p class="mt-1 text-sm text-[var(--brand-muted)]">{{ $child->school?->name ?? 'لم تحدد مدرسة' }}{{ $child->grade ? ' · '.$child->grade->name : '' }}</p>
                        </div>
                        @can('customers.update')
                            <form method="POST" action="{{ route('children.archive', $child) }}" onsubmit="return confirm('أرشفة ملف الطفل؟ ستبقى الطلبات والسجلات المرتبطة محفوظة.');">
                                @csrf
                                <button class="text-sm text-red-800 underline">أرشفة الملف</button>
                            </form>
                        @endcan
                    </div>
                    @can('measurements.view')
                        <h4 class="mb-2 text-sm font-semibold text-[var(--brand-navy)]">المقاسات الحالية — سم</h4>
                        @php($measurement = $child->currentMeasurements)
                        <dl class="mb-4 grid grid-cols-2 gap-x-3 gap-y-2 text-sm sm:grid-cols-3">
                            @foreach ([['الكتف', 'shoulder_cm'], ['الصدر', 'chest_cm'], ['الكم', 'sleeve_cm'], ['محيط الخصر', 'waist_circumference_cm'], ['الطول', 'length_cm']] as [$label, $field])
                                <div><dt class="text-[var(--brand-muted)]">{{ $label }}</dt><dd class="font-semibold">{{ $measurement?->{$field} ?? '—' }}</dd></div>
                            @endforeach
                        </dl>
                    @endcan
                    @can('measurements.update')
                        <details class="border-t border-[var(--brand-border)] pt-3">
                            <summary class="cursor-pointer text-sm font-semibold text-[var(--brand-navy)]">تحديث المقاسات الحالية</summary>
                            <form method="POST" action="{{ route('children.measurements.update', $child) }}" class="mt-4 grid gap-3 sm:grid-cols-2">
                                @csrf @method('PATCH')
                                @foreach ([['الكتف (سم)', 'shoulder_cm'], ['الصدر (سم)', 'chest_cm'], ['الكم (سم)', 'sleeve_cm'], ['محيط الخصر (سم)', 'waist_circumference_cm'], ['الطول (سم)', 'length_cm']] as [$label, $field])
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold" for="{{ $field }}-{{ $child->id }}">{{ $label }}</label>
                                        <input id="{{ $field }}-{{ $child->id }}" name="{{ $field }}" type="number" step="0.01" min="0.01" max="500" value="{{ old($field, $measurement?->{$field}) }}" class="w-full rounded-lg border border-[var(--brand-border)] px-3 py-2">
                                        @error($field) <p class="mt-1 text-xs text-red-700">{{ $message }}</p> @enderror
                                    </div>
                                @endforeach
                                <button class="rounded-lg bg-[var(--brand-navy)] px-4 py-2 text-sm font-semibold text-white sm:col-span-2">حفظ المقاسات الحالية</button>
                            </form>
                        </details>
                    @endcan
                </article>
            @empty
                <p class="rounded-xl border border-dashed border-[var(--brand-border)] bg-white p-5 text-sm text-[var(--brand-muted)]">لا يوجد أطفال مسجلون في هذا الملف بعد.</p>
            @endforelse
        </div>
    </section>

    @can('customers.create')
        <section class="rounded-xl border border-[var(--brand-border)] bg-white p-5 sm:p-6">
            <h2 class="mb-4 text-lg font-bold">إضافة طفل</h2>
            <form method="POST" action="{{ route('children.store', $guardian) }}" class="grid gap-4 sm:grid-cols-2">
                @csrf
                <div>
                    <label for="child-name" class="mb-1 block text-sm font-semibold">اسم الطفل <span aria-hidden="true">*</span></label>
                    <input id="child-name" name="name" required maxlength="160" value="{{ old('name') }}" class="w-full rounded-lg border border-[var(--brand-border)] px-3 py-2.5">
                    @error('name')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="school" class="mb-1 block text-sm font-semibold">المدرسة (اختياري)</label>
                    <select id="school" name="school_id" class="w-full rounded-lg border border-[var(--brand-border)] px-3 py-2.5">
                        <option value="">غير محددة</option>
                        @foreach ($schools as $school)<option value="{{ $school->id }}" @selected(old('school_id') == $school->id)>{{ $school->name }}</option>@endforeach
                    </select>
                    @error('school_id')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="grade" class="mb-1 block text-sm font-semibold">المرحلة/الصف (اختياري)</label>
                    <select id="grade" name="grade_id" class="w-full rounded-lg border border-[var(--brand-border)] px-3 py-2.5">
                        <option value="">غير محددة</option>
                        @foreach ($schools as $school)@foreach ($school->grades as $grade)<option value="{{ $grade->id }}" @selected(old('grade_id') == $grade->id)>{{ $school->name }} — {{ $grade->name }}</option>@endforeach @endforeach
                    </select>
                    @error('grade_id')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <div class="sm:col-span-2">
                    <label for="child-notes" class="mb-1 block text-sm font-semibold">ملاحظات</label>
                    <textarea id="child-notes" name="notes" rows="2" maxlength="2000" class="w-full rounded-lg border border-[var(--brand-border)] px-3 py-2">{{ old('notes') }}</textarea>
                </div>
                <button class="rounded-lg bg-[var(--brand-navy)] px-4 py-2.5 font-semibold text-white sm:col-span-2">إضافة الطفل</button>
            </form>
        </section>
    @endcan
@endsection
