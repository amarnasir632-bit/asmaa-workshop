<?php

namespace App\Http\Controllers;

use App\Models\GarmentType;
use App\Models\Grade;
use App\Models\InventoryVariant;
use App\Models\School;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function index(): View
    {
        Gate::authorize('catalog.view');

        return view('catalog.index', [
            'schools' => School::query()->with('grades')->orderBy('name')->paginate(20, ['*'], 'schools_page'),
            'allSchools' => School::query()->with(['grades' => fn ($query) => $query->orderBy('name')])->where('is_active', true)->orderBy('name')->get(),
            'garmentTypes' => GarmentType::query()->orderBy('name')->get(),
            'variants' => InventoryVariant::query()->with(['school', 'grade', 'garmentType'])->orderBy('id', 'desc')->paginate(20, ['*'], 'variants_page'),
        ]);
    }

    public function storeSchool(Request $request, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('catalog.manage');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180', 'unique:schools,name'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:1000'],
        ]);
        $school = School::query()->create($data);
        $audit->record('school_created', $school, $school->id, array_keys($data));

        return back()->with('status', 'تم تسجيل المدرسة.');
    }

    public function storeGrade(Request $request, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('catalog.manage');
        $data = $request->validate([
            'school_id' => ['required', 'integer', 'exists:schools,id'],
            'name' => ['required', 'string', 'max:100'],
        ]);
        if (Grade::query()->where('school_id', $data['school_id'])->where('name', $data['name'])->exists()) {
            throw ValidationException::withMessages(['grade_name' => 'هذه المرحلة مسجلة للمدرسة.']);
        }

        $grade = Grade::query()->create(['school_id' => $data['school_id'], 'name' => $data['name']]);
        $audit->record('grade_created', $grade, $grade->id, ['school_id', 'name']);

        return back()->with('status', 'تمت إضافة المرحلة الدراسية.');
    }

    public function storeGarmentType(Request $request, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('catalog.manage');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', 'unique:garment_types,name'],
            'requires_size' => ['sometimes', 'boolean'],
            'requires_color' => ['sometimes', 'boolean'],
        ]);
        $type = GarmentType::query()->create($data + ['requires_size' => false, 'requires_color' => false]);
        $audit->record('garment_type_created', $type, $type->id, array_keys($data));

        return back()->with('status', 'تمت إضافة صنف الملابس.');
    }

    public function storeVariant(Request $request, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('catalog.manage');
        $data = $request->validate([
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'garment_type_id' => ['required', 'integer', 'exists:garment_types,id'],
            'grade_id' => ['nullable', 'integer', 'exists:grades,id'],
            'size' => ['nullable', 'string', 'max:60'],
            'color' => ['nullable', 'string', 'max:80'],
            'sku' => ['nullable', 'string', 'max:80', 'unique:inventory_variants,sku'],
            'description' => ['nullable', 'string', 'max:180'],
        ]);
        if (! empty($data['grade_id']) && empty($data['school_id'])) {
            throw ValidationException::withMessages(['school_id' => 'اختيار مدرسة مطلوب مع المرحلة.']);
        }
        if (! empty($data['grade_id']) && ! Grade::query()->whereKey($data['grade_id'])->where('school_id', $data['school_id'])->exists()) {
            throw ValidationException::withMessages(['grade_id' => 'المرحلة لا تتبع المدرسة المحددة.']);
        }

        $type = GarmentType::query()->findOrFail($data['garment_type_id']);
        if ($type->requires_size && empty($data['size'])) {
            throw ValidationException::withMessages(['size' => 'المقاس مطلوب لهذا الصنف.']);
        }
        if ($type->requires_color && empty($data['color'])) {
            throw ValidationException::withMessages(['color' => 'اللون مطلوب لهذا الصنف.']);
        }

        $attributes = [
            'school_id' => $data['school_id'] ?? null,
            'garment_type_id' => $type->id,
            'grade_id' => $data['grade_id'] ?? null,
            'size' => trim((string) ($data['size'] ?? '')),
            'color' => trim((string) ($data['color'] ?? '')),
        ];
        $attributesKey = hash('sha256', json_encode($attributes, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        if (InventoryVariant::query()->where('attributes_key', $attributesKey)->exists()) {
            throw ValidationException::withMessages(['size' => 'هذه التركيبة مسجلة بالفعل.']);
        }

        $variant = InventoryVariant::query()->create($data + ['attributes_key' => $attributesKey]);
        $audit->record('inventory_variant_created', $variant, $variant->id, array_keys($data));

        return back()->with('status', 'تم تسجيل تركيبة الصنف.');
    }

    public function deactivateSchool(School $school, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('catalog.manage');
        $school->update(['is_active' => false]);
        $audit->record('school_deactivated', $school, $school->id, ['is_active']);

        return back()->with('status', 'تم إيقاف المدرسة عن الاختيارات الجديدة مع حفظ سجلاتها السابقة.');
    }

    public function deactivateGrade(Grade $grade, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('catalog.manage');
        $grade->update(['is_active' => false]);
        $audit->record('grade_deactivated', $grade, $grade->id, ['is_active']);

        return back()->with('status', 'تم إيقاف المرحلة عن الاختيارات الجديدة مع حفظ سجلاتها السابقة.');
    }

    public function deactivateGarmentType(GarmentType $garmentType, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('catalog.manage');
        $garmentType->update(['is_active' => false]);
        $audit->record('garment_type_deactivated', $garmentType, $garmentType->id, ['is_active']);

        return back()->with('status', 'تم إيقاف الصنف عن الاستخدام الجديد مع حفظ سجلاته السابقة.');
    }

    public function deactivateVariant(InventoryVariant $variant, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('catalog.manage');
        $variant->update(['is_active' => false]);
        $audit->record('inventory_variant_deactivated', $variant, $variant->id, ['is_active']);

        return back()->with('status', 'تم إيقاف التركيبة عن البيع الجديد مع حفظ الحركات السابقة.');
    }
}
