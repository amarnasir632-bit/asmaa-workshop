@extends('layouts.app')

@section('title', 'أولياء الأمور')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-[var(--brand-navy)]">أولياء الأمور والأطفال</h1>
            <p class="mt-1 text-sm text-[var(--brand-muted)]">البحث بالاسم أو رقم التواصل أو اسم الطفل.</p>
        </div>
        @can('customers.create')
            <a href="{{ route('guardians.create') }}" class="rounded-lg bg-[var(--brand-navy)] px-4 py-2.5 text-sm font-semibold text-white">إضافة ولي أمر</a>
        @endcan
    </div>

    <form method="GET" action="{{ route('guardians.index') }}" class="mb-5 flex gap-2 rounded-xl border border-[var(--brand-border)] bg-white p-3">
        <label class="sr-only" for="q">ابحث</label>
        <input id="q" name="q" value="{{ $search }}" type="search" placeholder="الاسم أو رقم التواصل" class="min-w-0 flex-1 rounded-lg border border-[var(--brand-border)] px-3 py-2">
        <button class="rounded-lg border border-[var(--brand-border)] px-4 py-2 font-semibold">بحث</button>
        @if ($search !== '') <a class="self-center px-2 text-sm text-[var(--brand-navy)]" href="{{ route('guardians.index') }}">مسح</a> @endif
    </form>

    <div class="overflow-hidden rounded-xl border border-[var(--brand-border)] bg-white">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[620px] text-right text-sm">
                <thead class="bg-slate-50 text-[var(--brand-muted)]"><tr><th class="px-4 py-3">ولي الأمر</th><th class="px-4 py-3">رقم التواصل</th><th class="px-4 py-3">الأطفال</th><th class="px-4 py-3">فتح الملف</th></tr></thead>
                <tbody class="divide-y divide-[var(--brand-border)]">
                    @forelse ($guardians as $guardian)
                        <tr>
                            <td class="px-4 py-3 font-semibold">{{ $guardian->name }}</td>
                            <td class="px-4 py-3" dir="ltr">{{ $guardian->phone }}</td>
                            <td class="px-4 py-3">{{ number_format($guardian->children_count) }}</td>
                            <td class="px-4 py-3"><a class="font-semibold text-[var(--brand-navy)] underline" href="{{ route('guardians.show', $guardian) }}">عرض الملف</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-[var(--brand-muted)]">لا توجد سجلات مطابقة.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-[var(--brand-border)] p-3">{{ $guardians->links() }}</div>
    </div>
@endsection
