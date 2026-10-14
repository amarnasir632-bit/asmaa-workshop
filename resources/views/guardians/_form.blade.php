<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <label for="name" class="mb-1 block text-sm font-semibold">اسم ولي الأمر <span aria-hidden="true">*</span></label>
        <input id="name" name="name" value="{{ old('name', $guardian->name ?? '') }}" required maxlength="160" class="w-full rounded-lg border border-[var(--brand-border)] px-3 py-2.5">
        @error('name') <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="phone" class="mb-1 block text-sm font-semibold">رقم التواصل <span aria-hidden="true">*</span></label>
        <input id="phone" name="phone" type="tel" dir="ltr" value="{{ old('phone', $guardian->phone ?? '') }}" required maxlength="40" class="w-full rounded-lg border border-[var(--brand-border)] px-3 py-2.5">
        @error('phone') <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="alternate_phone" class="mb-1 block text-sm font-semibold">رقم بديل (اختياري)</label>
        <input id="alternate_phone" name="alternate_phone" type="tel" dir="ltr" value="{{ old('alternate_phone', $guardian->alternate_phone ?? '') }}" maxlength="40" class="w-full rounded-lg border border-[var(--brand-border)] px-3 py-2.5">
        @error('alternate_phone') <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
    </div>
    <div class="sm:col-span-2">
        <label for="notes" class="mb-1 block text-sm font-semibold">ملاحظات</label>
        <textarea id="notes" name="notes" rows="3" maxlength="2000" class="w-full rounded-lg border border-[var(--brand-border)] px-3 py-2.5">{{ old('notes', $guardian->notes ?? '') }}</textarea>
        @error('notes') <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
    </div>
</div>
<div class="mt-5 flex gap-3">
    <button class="rounded-lg bg-[var(--brand-navy)] px-5 py-2.5 font-semibold text-white">حفظ</button>
    <a href="{{ isset($guardian) ? route('guardians.show', $guardian) : route('guardians.index') }}" class="rounded-lg border border-[var(--brand-border)] px-5 py-2.5">إلغاء</a>
</div>
