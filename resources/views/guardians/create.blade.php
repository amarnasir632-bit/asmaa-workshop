@extends('layouts.app')
@section('title', 'إضافة ولي أمر')
@section('content')
    <h1 class="mb-5 text-2xl font-bold text-[var(--brand-navy)]">إضافة ولي أمر</h1>
    <form method="POST" action="{{ route('guardians.store') }}" class="rounded-xl border border-[var(--brand-border)] bg-white p-5 sm:p-7">
        @csrf
        @include('guardians._form')
    </form>
@endsection
