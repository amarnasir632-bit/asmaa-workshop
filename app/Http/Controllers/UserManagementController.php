<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function index(): View
    {
        Gate::authorize('users.manage');
        $users = User::query()->with('roles')->orderBy('name')->paginate(30);

        return view('users.index', compact('users'));
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('users.manage');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'username' => ['required', 'string', 'min:3', 'max:80', 'regex:/^[a-z0-9._-]+$/i', 'unique:users,username'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in(['manager', 'employee'])],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);
        $user = DB::transaction(function () use ($data, $audit): User {
            $user = User::query()->create([
                'name' => trim($data['name']),
                'username' => Str::lower($data['username']),
                'email' => isset($data['email']) ? Str::lower($data['email']) : null,
                'password' => Hash::make($data['password']),
                'is_active' => true,
            ]);
            $user->roles()->attach(Role::query()->where('name', $data['role'])->value('id'));
            $audit->record('staff_user_created', $user, $user->id, ['name', 'username', 'email', 'role']);

            return $user;
        });

        return back()->with('status', 'تم إنشاء حساب '.$user->username.'. أرسل بيانات الدخول للمستخدم عبر قناة آمنة.');
    }

    public function update(Request $request, User $user, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('users.manage');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'username' => ['required', 'string', 'min:3', 'max:80', 'regex:/^[a-z0-9._-]+$/i', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', Rule::in(['manager', 'employee'])],
            'is_active' => ['required', 'boolean'],
        ]);
        DB::transaction(function () use ($data, $user, $audit, $request): void {
            $user = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $oldRole = $user->roles()->value('name');
            if ((int) $user->id === (int) $request->user()->id && ($data['role'] !== 'manager' || ! $data['is_active'])) {
                throw ValidationException::withMessages(['user' => 'لا يمكن للمدير تغيير دوره أو إيقاف حسابه أثناء استخدامه.']);
            }
            if ($oldRole === 'manager' && ($data['role'] !== 'manager' || ! $data['is_active'])) {
                $managers = User::query()->whereHas('roles', fn ($query) => $query->where('name', 'manager'))
                    ->orderBy('id')->lockForUpdate()->get();
                if ($managers->where('is_active', true)->count() <= 1) {
                    throw ValidationException::withMessages(['role' => 'يجب إبقاء حساب مدير نشط واحد على الأقل.']);
                }
            }
            $user->fill([
                'name' => trim($data['name']),
                'username' => Str::lower($data['username']),
                'email' => isset($data['email']) ? Str::lower($data['email']) : null,
                'is_active' => (bool) $data['is_active'],
            ]);
            $fields = array_keys($user->getDirty());
            $user->save();
            $user->roles()->sync([Role::query()->where('name', $data['role'])->value('id')]);
            if ($oldRole !== $data['role']) {
                $fields[] = 'role';
            }
            if ($fields !== []) {
                $audit->record('staff_user_updated', $user, $user->id, $fields);
            }
            if (! $data['is_active']) {
                DB::table('sessions')->where('user_id', $user->id)->delete();
                $audit->record('staff_user_deactivated', $user, $user->id, ['is_active']);
            }
            if ($oldRole === 'manager' && $data['role'] !== 'manager') {
                $audit->record('staff_manager_role_removed', $user, $user->id, ['role']);
            }
        });

        return back()->with('status', 'تم تحديث الحساب والصلاحية.');
    }
}
