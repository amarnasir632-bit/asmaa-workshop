<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateManager extends Command
{
    protected $signature = 'app:create-manager';

    protected $description = 'إنشاء حساب المدير الأول من الطرفية';

    public function handle(): int
    {
        if (User::query()->whereHas('roles', fn ($query) => $query->where('name', 'manager'))->exists()) {
            $this->components->error('يوجد حساب مدير بالفعل. أنشئ حسابات الإدارة اللاحقة من داخل النظام.');

            return self::FAILURE;
        }

        $name = trim((string) $this->ask('الاسم الظاهر'));
        $username = Str::lower(trim((string) $this->ask('اسم المستخدم (أحرف إنجليزية وأرقام ونقطة أو شرطة)')));
        $email = Str::lower(trim((string) $this->ask('البريد الإلكتروني (اختياري)')));

        if ($name === '' || ! preg_match('/^[a-z0-9._-]{3,80}$/', $username)) {
            $this->components->error('الاسم مطلوب، واسم المستخدم يجب أن يكون من 3 إلى 80 حرفاً إنجليزياً أو رقماً.');

            return self::INVALID;
        }

        if ($email !== '' && (! filter_var($email, FILTER_VALIDATE_EMAIL) || User::query()->where('email', $email)->exists())) {
            $this->components->error('البريد غير صالح أو مستخدم سابقاً.');

            return self::INVALID;
        }

        if (User::query()->where('username', $username)->exists()) {
            $this->components->error('اسم المستخدم مستخدم سابقاً.');

            return self::INVALID;
        }

        $password = (string) $this->secret('كلمة مرور طويلة (12 حرفاً على الأقل)');
        $confirmation = (string) $this->secret('أعد كتابة كلمة المرور');

        if (strlen($password) < 12 || ! hash_equals($password, $confirmation)) {
            $this->components->error('كلمة المرور يجب أن تبلغ 12 حرفاً وأن تتطابق في الإدخالين.');

            return self::INVALID;
        }

        $manager = DB::transaction(function () use ($name, $username, $email, $password): User {
            $managerRole = Role::query()->where('name', 'manager')->firstOrFail();
            $user = User::query()->create([
                'name' => $name,
                'username' => $username,
                'email' => $email !== '' ? $email : null,
                'password' => Hash::make($password),
                'is_active' => true,
            ]);
            $user->roles()->attach($managerRole->id);

            DB::table('audit_logs')->insert([
                'user_id' => null,
                'action' => 'bootstrap_manager_created',
                'subject_type' => User::class,
                'subject_id' => $user->id,
                'before_values' => null,
                'after_values' => json_encode(['username' => $username], JSON_THROW_ON_ERROR),
                'reason' => 'إنشاء حساب المدير الأول من الطرفية',
                'request_id' => null,
                'occurred_at' => now(),
            ]);

            return $user;
        });

        $this->components->info("تم إنشاء حساب المدير: {$manager->username}");
        $this->components->warn('احتفظ ببيانات الدخول في مكان آمن. لا تسجل كلمة المرور في ملفات المشروع.');

        return self::SUCCESS;
    }
}
