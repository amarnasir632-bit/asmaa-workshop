<?php

namespace App\Http\Controllers;

use App\Models\CurrentMeasurement;
use App\Models\Guardian;
use App\Models\School;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $schools = School::query()->where('is_active', true)->with('grades')->orderBy('name')->get();

        return view('guardians.create', compact('schools'));
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('customers.create');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'phone' => ['required', 'string', 'max:40'],
            'alternate_phone' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'children' => ['nullable', 'array'],
            'children.*.name' => ['nullable', 'string', 'max:160'],
            'children.*.school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'children.*.grade_id' => ['nullable', 'integer', 'exists:grades,id'],
            'children.*.notes' => ['nullable', 'string', 'max:2000'],
            'children.*.shoulder_cm' => ['nullable', 'numeric', 'gt:0', 'max:500'],
            'children.*.chest_cm' => ['nullable', 'numeric', 'gt:0', 'max:500'],
            'children.*.sleeve_cm' => ['nullable', 'numeric', 'gt:0', 'max:500'],
            'children.*.waist_circumference_cm' => ['nullable', 'numeric', 'gt:0', 'max:500'],
            'children.*.length_cm' => ['nullable', 'numeric', 'gt:0', 'max:500'],
        ]);

        $guardian = DB::transaction(function () use ($data, $audit, $request) {
            $guardianData = [
                'name' => $data['name'],
                'phone' => $data['phone'],
                'alternate_phone' => $data['alternate_phone'] ?? null,
                'notes' => $data['notes'] ?? null,
            ];
            $guardian = Guardian::query()->create($guardianData);
            $audit->record('guardian_created', $guardian, $guardian->id, array_keys($guardianData));

            if (! empty($data['children']) && is_array($data['children'])) {
                foreach ($data['children'] as $childData) {
                    $childName = trim((string) ($childData['name'] ?? ''));
                    if ($childName === '') {
                        continue;
                    }

                    $child = $guardian->children()->create([
                        'name' => $childName,
                        'school_id' => ! empty($childData['school_id']) ? (int) $childData['school_id'] : null,
                        'grade_id' => ! empty($childData['grade_id']) ? (int) $childData['grade_id'] : null,
                        'notes' => $childData['notes'] ?? null,
                    ]);
                    $audit->record('child_created', $child, $child->id, ['name', 'school_id', 'grade_id', 'notes']);

                    $measurementFields = ['shoulder_cm', 'chest_cm', 'sleeve_cm', 'waist_circumference_cm', 'length_cm'];
                    $measurements = [];
                    foreach ($measurementFields as $field) {
                        if (isset($childData[$field]) && $childData[$field] !== '') {
                            $measurements[$field] = $childData[$field];
                        }
                    }

                    if ($measurements !== []) {
                        $measurements['child_id'] = $child->id;
                        $measurements['updated_by'] = $request->user()?->id;
                        $measurement = CurrentMeasurement::query()->create($measurements);
                        $audit->record('child_measurements_updated', $measurement, $measurement->id, array_keys($measurements));
                    }
                }
            }

            return $guardian;
        });

        return redirect()->route('guardians.show', $guardian)->with('status', 'تم حفظ ملف ولي الأمر والأبناء بنجاح.');
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
