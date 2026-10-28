<?php

namespace App\Http\Controllers;

use App\Models\Child;
use App\Models\CurrentMeasurement;
use App\Models\Grade;
use App\Models\Guardian;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ChildController extends Controller
{
    public function store(Request $request, Guardian $guardian, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('customers.create');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'grade_id' => ['nullable', 'integer', 'exists:grades,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if (! empty($data['grade_id']) && empty($data['school_id'])) {
            throw ValidationException::withMessages(['school_id' => 'اختر المدرسة المرتبطة بالمرحلة.']);
        }

        if (! empty($data['grade_id']) && ! empty($data['school_id'])) {
            if (! Grade::query()->whereKey($data['grade_id'])->where('school_id', $data['school_id'])->exists()) {
                throw ValidationException::withMessages(['grade_id' => 'المرحلة المحددة لا تتبع المدرسة المختارة.']);
            }
        }

        $child = $guardian->children()->create($data);
        $audit->record('child_created', $child, $child->id, array_keys($data));

        return redirect()->route('guardians.show', $guardian)->with('status', 'تمت إضافة الطفل.');
    }

    public function updateMeasurements(Request $request, Child $child, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('measurements.update');
        $data = $request->validate([
            'shoulder_cm' => ['nullable', 'numeric', 'gt:0', 'max:500'],
            'chest_cm' => ['nullable', 'numeric', 'gt:0', 'max:500'],
            'sleeve_cm' => ['nullable', 'numeric', 'gt:0', 'max:500'],
            'waist_circumference_cm' => ['nullable', 'numeric', 'gt:0', 'max:500'],
            'length_cm' => ['nullable', 'numeric', 'gt:0', 'max:500'],
        ]);

        $measurement = CurrentMeasurement::query()->firstOrNew(['child_id' => $child->id]);
        $measurement->fill($data);
        $measurement->updated_by = $request->user()->id;
        $changedFields = array_keys($measurement->getDirty());
        if ($changedFields !== []) {
            $measurement->save();
            $audit->record('child_measurements_updated', $measurement, $measurement->id, $changedFields);
        }

        return redirect()->route('guardians.show', $child->guardian_id)->with('status', 'تم حفظ المقاسات الحالية. الطلبات السابقة تحتفظ بنسخها التاريخية.');
    }

    public function archive(Child $child, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('customers.update');
        $guardianId = $child->guardian_id;
        $child->delete();
        $audit->record('child_archived', $child, $child->id, ['deleted_at']);

        return redirect()->route('guardians.show', $guardianId)->with('status', 'تمت أرشفة ملف الطفل مع الاحتفاظ بسجلاته.');
    }
}
