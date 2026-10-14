@extends('layouts.app')

@section('title', 'المدارس والأصناف')

@section('content')
<div class="mx-auto max-w-7xl space-y-6">
    <header>
        <p class="text-sm font-semibold text-[var(--brand-gold)]">بيانات مرجعية</p>
        <h1 class="mt-1 text-2xl font-bold text-[var(--brand-navy)]">المدارس والأصناف</h1>
        <p class="mt-2 text-sm text-[var(--brand-muted)]">أدر المدارس ومراحلها والأصناف وتركيبات المقاس واللون. السجلات السابقة تبقى محفوظة عند إيقاف سجل.</p>
    </header>
    @if ($errors->any())
        <div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"><ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    @can('catalog.manage')
    <div class="grid gap-5 xl:grid-cols-2">
        <section class="rounded-xl border border-[var(--brand-border)] bg-white p-5">
            <h2 class="mb-4 text-lg font-bold">إضافة مدرسة</h2>
            <form method="POST" action="{{ route('catalog.schools.store') }}" class="grid gap-3 sm:grid-cols-2">@csrf
                <label class="text-sm">اسم المدرسة <input name="name" required maxlength="180" value="{{ old('name') }}" class="mt-1 w-full rounded-lg border p-2"></label>
                <label class="text-sm">رقم التواصل (اختياري) <input name="phone" maxlength="40" value="{{ old('phone') }}" class="mt-1 w-full rounded-lg border p-2"></label>
                <label class="text-sm sm:col-span-2">العنوان (اختياري) <input name="address" maxlength="1000" value="{{ old('address') }}" class="mt-1 w-full rounded-lg border p-2"></label>
                <button class="rounded-lg bg-[var(--brand-navy)] px-4 py-2 font-semibold text-white sm:col-span-2">حفظ المدرسة</button>
            </form>
        </section>
        <section class="rounded-xl border border-[var(--brand-border)] bg-white p-5">
            <h2 class="mb-4 text-lg font-bold">إضافة مرحلة دراسية</h2>
            <form method="POST" action="{{ route('catalog.grades.store') }}" class="grid gap-3 sm:grid-cols-2">@csrf
                <label class="text-sm">المدرسة <select name="school_id" required class="mt-1 w-full rounded-lg border p-2"><option value="">اختر</option>@foreach ($allSchools as $school)<option value="{{ $school->id }}">{{ $school->name }}</option>@endforeach</select></label>
                <label class="text-sm">اسم المرحلة <input name="name" required maxlength="100" class="mt-1 w-full rounded-lg border p-2"></label>
                <button class="rounded-lg bg-[var(--brand-navy)] px-4 py-2 font-semibold text-white sm:col-span-2">إضافة المرحلة</button>
            </form>
        </section>
        <section class="rounded-xl border border-[var(--brand-border)] bg-white p-5">
            <h2 class="mb-4 text-lg font-bold">إضافة صنف ملابس</h2>
            <form method="POST" action="{{ route('catalog.garment-types.store') }}" class="space-y-3">@csrf
                <label class="block text-sm">اسم الصنف <input name="name" required maxlength="120" class="mt-1 w-full rounded-lg border p-2"></label>
                <div class="flex flex-wrap gap-4 text-sm"><label><input type="checkbox" name="requires_size" value="1" checked> يتطلب مقاساً</label><label><input type="checkbox" name="requires_color" value="1"> يتطلب لوناً</label></div>
                <button class="rounded-lg bg-[var(--brand-navy)] px-4 py-2 font-semibold text-white">حفظ الصنف</button>
            </form>
        </section>
        <section class="rounded-xl border border-[var(--brand-border)] bg-white p-5">
            <h2 class="mb-4 text-lg font-bold">إضافة تركيبة صنف</h2>
            <form method="POST" action="{{ route('catalog.variants.store') }}" class="grid gap-3 sm:grid-cols-2">@csrf
                <label class="text-sm">نوع الملابس <select name="garment_type_id" required class="mt-1 w-full rounded-lg border p-2"><option value="">اختر</option>@foreach ($garmentTypes as $type)<option value="{{ $type->id }}">{{ $type->name }}</option>@endforeach</select></label>
                <label class="text-sm">المدرسة (اختياري) <select name="school_id" class="mt-1 w-full rounded-lg border p-2"><option value="">عام</option>@foreach ($allSchools as $school)<option value="{{ $school->id }}">{{ $school->name }}</option>@endforeach</select></label>
                <label class="text-sm">المرحلة (اختياري) <select name="grade_id" class="mt-1 w-full rounded-lg border p-2"><option value="">كل المراحل</option>@foreach ($allSchools as $school)@foreach ($school->grades as $grade)<option value="{{ $grade->id }}">{{ $school->name }} — {{ $grade->name }}</option>@endforeach @endforeach</select></label>
                <label class="text-sm">المقاس <input name="size" maxlength="60" class="mt-1 w-full rounded-lg border p-2"></label>
                <label class="text-sm">اللون <input name="color" maxlength="80" class="mt-1 w-full rounded-lg border p-2"></label>
                <label class="text-sm">رمز الصنف (اختياري) <input name="sku" maxlength="80" class="mt-1 w-full rounded-lg border p-2"></label>
                <label class="text-sm sm:col-span-2">وصف (اختياري) <input name="description" maxlength="180" class="mt-1 w-full rounded-lg border p-2"></label>
                <button class="rounded-lg bg-[var(--brand-navy)] px-4 py-2 font-semibold text-white sm:col-span-2">حفظ التركيبة</button>
            </form>
        </section>
    </div>
    @endcan
    <div class="grid gap-5 xl:grid-cols-2">
        <section class="overflow-hidden rounded-xl border border-[var(--brand-border)] bg-white">
            <h2 class="border-b p-4 font-bold">المدارس والمراحل</h2>
            <div class="divide-y">@forelse ($schools as $school)<article class="flex items-start justify-between gap-3 p-4"><div><h3 class="font-semibold">{{ $school->name }} @unless($school->is_active)<span class="text-xs text-[var(--brand-muted)]">(موقوفة)</span>@endunless</h3><p class="text-sm text-[var(--brand-muted)]">{{ $school->phone ?: 'لا يوجد رقم تواصل' }}</p><ul class="mt-2 flex flex-wrap gap-2">@foreach ($school->grades as $grade)<li class="rounded-full bg-slate-100 px-3 py-1 text-xs">{{ $grade->name }} @unless($grade->is_active)(موقوفة)@endunless</li>@endforeach</ul></div>@if($school->is_active && auth()->user()->can('catalog.manage'))<form method="POST" action="{{ route('catalog.schools.deactivate', $school) }}">@csrf<button class="text-sm text-red-700 underline">إيقاف</button></form>@endif</article>@empty<p class="p-4 text-sm text-[var(--brand-muted)]">لا توجد مدارس مسجلة.</p>@endforelse</div>
            <div class="border-t p-3">{{ $schools->links() }}</div>
        </section>
        <section class="overflow-hidden rounded-xl border border-[var(--brand-border)] bg-white">
            <h2 class="border-b p-4 font-bold">تركيبات الملابس</h2>
            <div class="divide-y">@forelse ($variants as $variant)<article class="flex items-start justify-between gap-3 p-4"><div><h3 class="font-semibold">{{ $variant->garmentType->name }} @unless($variant->is_active)<span class="text-xs text-[var(--brand-muted)]">(موقوف)</span>@endunless</h3><p class="text-sm text-[var(--brand-muted)]">{{ $variant->school?->name ?? 'صنف عام' }}{{ $variant->grade ? ' · '.$variant->grade->name : '' }}{{ $variant->size ? ' · '.$variant->size : '' }}{{ $variant->color ? ' · '.$variant->color : '' }}</p>@if($variant->sku)<p class="text-xs text-[var(--brand-muted)]">الرمز: {{ $variant->sku }}</p>@endif</div>@if($variant->is_active && auth()->user()->can('catalog.manage'))<form method="POST" action="{{ route('catalog.variants.deactivate', $variant) }}">@csrf<button class="text-sm text-red-700 underline">إيقاف</button></form>@endif</article>@empty<p class="p-4 text-sm text-[var(--brand-muted)]">لا توجد تركيبات مسجلة.</p>@endforelse</div>
            <div class="border-t p-3">{{ $variants->links() }}</div>
        </section>
    </div>
    <section class="rounded-xl border border-[var(--brand-border)] bg-white p-5"><h2 class="mb-3 font-bold">أنواع الملابس</h2><ul class="flex flex-wrap gap-2">@foreach($garmentTypes as $type)<li class="rounded-full bg-slate-100 px-3 py-1 text-sm">{{ $type->name }} @unless($type->is_active)(موقوف)@endunless</li>@endforeach</ul></section>
</div>
@endsection
