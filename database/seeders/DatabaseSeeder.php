<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use App\Models\GarmentType;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

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
        });
    }
}
