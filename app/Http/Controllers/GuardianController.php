<?php

namespace App\Http\Controllers;

use App\Models\Guardian;
use App\Models\School;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class GuardianController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('customers.view');
        $search = trim((string) $request->query('q', ''));
        $guardians = Guardian::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%')
                        ->orWhereHas('children', fn ($children) => $children->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->withCount('children')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('guardians.index', compact('guardians', 'search'));
    }

    public function create(): View
    {
        Gate::authorize('customers.create');

        return view('guardians.create');
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('customers.create');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'phone' => ['required', 'string', 'max:40'],
            'alternate_phone' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $guardian = Guardian::query()->create($data);
        $audit->record('guardian_created', $guardian, $guardian->id, array_keys($data));

        return redirect()->route('guardians.show', $guardian)->with('status', 'تم حفظ ملف ولي الأمر.');
    }

    public function show(Guardian $guardian): View
    {
        Gate::authorize('customers.view');
        $guardian->load(['children.school', 'children.grade', 'children.currentMeasurements']);
        $guardian->loadCount('orders');
        $schools = School::query()->where('is_active', true)->with('grades')->orderBy('name')->get();

        return view('guardians.show', compact('guardian', 'schools'));
    }

    public function edit(Guardian $guardian): View
    {
        Gate::authorize('customers.update');

        return view('guardians.edit', compact('guardian'));
    }

    public function update(Request $request, Guardian $guardian, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('customers.update');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'phone' => ['required', 'string', 'max:40'],
            'alternate_phone' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $guardian->fill($data);
        $changedFields = array_keys($guardian->getDirty());
        if ($changedFields !== []) {
            $guardian->save();
            $audit->record('guardian_updated', $guardian, $guardian->id, $changedFields);
        }

        return redirect()->route('guardians.show', $guardian)->with('status', 'تم تحديث ملف ولي الأمر.');
    }

    public function archive(Guardian $guardian, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('customers.update');
        $guardian->delete();
        $audit->record('guardian_archived', $guardian, $guardian->id, ['deleted_at']);

        return redirect()->route('guardians.index')->with('status', 'تمت أرشفة الملف مع الاحتفاظ بالسجلات المرتبطة.');
    }
}
