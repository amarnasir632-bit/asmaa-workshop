<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use App\Models\GarmentType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $manager = Role::query()->updateOrCreate(['name' => 'manager'], ['label' => 'مدير']);
            $employee = Role::query()->updateOrCreate(['name' => 'employee'], ['label' => 'موظف']);
            $permissionModels = [];

            foreach (config('permissions.labels') as $name => $label) {
                $permissionModels[$name] = Permission::query()->updateOrCreate(['name' => $name], ['label' => $label]);
            }

            $manager->permissions()->sync(array_values(array_map(fn ($permission) => $permission->id, $permissionModels)));

            foreach (['قمصان', 'بناطيل', 'تنانير', 'زي كامل أو مريلة', 'أخرى'] as $name) {
                GarmentType::query()->firstOrCreate(['name' => $name], [
                    'requires_size' => true,
                    'requires_color' => false,
                    'is_active' => true,
                ]);
            }
            foreach (['أجور الترزي والخياطة', 'النقل', 'الخامات', 'المصروفات التشغيلية', 'أخرى'] as $name) {
                ExpenseCategory::query()->firstOrCreate(['name' => $name], ['is_active' => true]);
            }
            $employee->permissions()->sync(array_map(
                fn ($name) => $permissionModels[$name]->id,
                config('permissions.employee'),
            ));

            // إنشاء حساب المدير الأول تلقائياً من متغيرات البيئة إذا لم يكن موجوداً
            $initUsername = Str::lower(trim((string) env('INITIAL_MANAGER_USERNAME', '')));
            $initPassword = (string) env('INITIAL_MANAGER_PASSWORD', '');
            $initName = trim((string) env('INITIAL_MANAGER_NAME', 'مدير النظام'));

            if (
                $initUsername !== ''
                && $initPassword !== ''
                && ! User::query()->whereHas('roles', fn ($q) => $q->where('name', 'manager'))->exists()
            ) {
                $user = User::query()->create([
                    'name' => $initName !== '' ? $initName : 'مدير النظام',
                    'username' => $initUsername,
                    'email' => null,
                    'password' => Hash::make($initPassword),
                    'is_active' => true,
                ]);
                $user->roles()->attach($manager->id);

                DB::table('audit_logs')->insert([
                    'user_id' => null,
                    'action' => 'bootstrap_manager_created',
                    'subject_type' => User::class,
                    'subject_id' => $user->id,
                    'before_values' => null,
                    'after_values' => json_encode(['username' => $initUsername], JSON_THROW_ON_ERROR),
                    'reason' => 'إنشاء حساب المدير الأول تلقائياً عند التشغيل',
                    'request_id' => null,
                    'occurred_at' => now(),
                ]);
            }
        });
    }
}
