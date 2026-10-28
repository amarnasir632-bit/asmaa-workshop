<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/png" href="{{ asset('icon.png') }}">
    <title>تسجيل الدخول · مشغل أسماء</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen items-center justify-center p-4">
    <main class="w-full max-w-md rounded-2xl border border-[var(--brand-border)] bg-white p-6 shadow-sm sm:p-8">
        <div class="mb-7 border-b border-[var(--brand-border)] pb-5">
            <img src="{{ asset('logo.png') }}" alt="مشغل أسماء" class="mb-4 h-auto max-h-28 w-full object-contain object-right">
            <p class="text-sm font-semibold text-[var(--brand-gold)]">Tailoring & School Uniforms</p>
            <p class="mt-2 text-sm text-[var(--brand-muted)]">سجّل الدخول للمتابعة إلى النظام</p>
        </div>
        <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
            @csrf
            <div>
                <label for="login" class="mb-1 block text-sm font-semibold">اسم المستخدم أو البريد الإلكتروني</label>
                <input id="login" name="login" type="text" autocomplete="username" required autofocus value="{{ old('login') }}"
                    class="w-full rounded-lg border border-[var(--brand-border)] px-3 py-2.5 focus:border-[var(--brand-navy)]">
                @error('login') <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="password" class="mb-1 block text-sm font-semibold">كلمة المرور</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required
                    class="w-full rounded-lg border border-[var(--brand-border)] px-3 py-2.5 focus:border-[var(--brand-navy)]">
                @error('password') <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
            </div>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="remember" value="1" class="rounded border-slate-300 text-[var(--brand-navy)]">
                تذكرني على هذا الجهاز
            </label>
            <button type="submit" class="w-full rounded-lg bg-[var(--brand-navy)] px-4 py-3 font-semibold text-white hover:brightness-110">دخول</button>
        </form>
        <p class="mt-6 text-center text-xs text-[var(--brand-muted)]">الوصول مخصص لمستخدمي المشغل المسجلين.</p>
        <a href="{{ route('public.lookup') }}" class="mt-3 block text-center text-sm font-semibold text-[var(--brand-navy)] underline">استعلام ولي الأمر عن الطلب</a>
    </main>
</body>
</html>
