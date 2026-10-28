@extends('layouts.app')
@section('title', 'تعديل ملف ولي الأمر')
@section('content')
    <h1 class="mb-5 text-2xl font-bold text-[var(--brand-navy)]">تعديل ملف ولي الأمر</h1>
    <form method="POST" action="{{ route('guardians.update', $guardian) }}" class="rounded-xl border border-[var(--brand-border)] bg-white p-5 sm:p-7">
        @csrf @method('PATCH')
        @include('guardians._form')
    </form>
@endsection
