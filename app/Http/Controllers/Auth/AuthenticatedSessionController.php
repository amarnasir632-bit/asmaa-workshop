<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request, RateLimiter $limiter): RedirectResponse
    {
        $validated = $request->validate([
            'login' => ['required', 'string', 'max:190'],
            'password' => ['required', 'string', 'max:200'],
            'remember' => ['sometimes', 'boolean'],
        ]);

        $login = trim($validated['login']);
        $throttleKey = 'staff-login:'.hash('sha256', Str::lower($login).'|'.$request->ip());

        if ($limiter->tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'login' => 'تعذر تسجيل الدخول الآن. حاول بعد قليل.',
            ]);
        }

        $credentialField = str_contains($login, '@') ? 'email' : 'username';
        $authenticated = Auth::attempt([
            $credentialField => Str::lower($login),
            'password' => $validated['password'],
            'is_active' => true,
        ], $request->boolean('remember'));

        if (! $authenticated) {
            $limiter->hit($throttleKey, 60);
            throw ValidationException::withMessages([
                'login' => 'بيانات الدخول غير صحيحة أو الحساب غير مفعل.',
            ]);
        }

        $limiter->clear($throttleKey);
        $request->session()->regenerate();
        DB::table('users')->where('id', Auth::id())->update(['last_login_at' => now()]);

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
