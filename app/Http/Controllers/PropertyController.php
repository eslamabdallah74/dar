<?php

namespace App\Http\Controllers;

use App\Enums\PropertyType;
use App\Http\Requests\Properties\StorePropertyRequest;
use App\Http\Requests\Properties\StoreUnitRequest;
use App\Http\Requests\Properties\UpdatePropertyRequest;
use App\Http\Requests\Properties\UpdateUnitRequest;
use App\Http\Resources\PropertyResource;
use App\Models\Plan;
use App\Models\Property;
use App\Models\Unit;
use App\Services\Properties\PropertyUnitBoardService;
use App\Services\PropertyUnitGeneratorService;
use App\Services\Storage\DocumentStorageService;
use App\Services\UnitHierarchyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PropertyController extends Controller
{
    public function index(Request $request, UnitHierarchyService $hierarchyService): Response
    {
        $workspace = $this->resolveWorkspace($request);

        $billableCounts = Unit::withoutGlobalScopes()
            ->where('workspace_id', $workspace->id)
            ->billableLeaves()
            ->select('property_id', DB::raw('count(*) as count'))
            ->groupBy('property_id')
            ->pluck('count', 'property_id');

        $billableCountsArray = $billableCounts->all();
        $properties = $workspace->properties()
            ->withCount('units')
            ->with(['units.leases' => fn ($q) => $q->where('status', 'active')->with('renter')])
            ->get()
            ->map(fn (Property $property) => (new PropertyResource($property, $billableCountsArray, $hierarchyService))->resolve());

        $totalUnitsCount = $properties->sum('total_units');
        $totalOccupied = $properties->sum('occupied_units');
        $overallOccupancy = $totalUnitsCount > 0 ? (int) round(($totalOccupied / $totalUnitsCount) * 100) : 0;
        $totalMonthlyIncome = $properties->sum('monthly_revenue');
        $totalBillableUnits = Unit::billableCountForWorkspace($workspace->id);

        return Inertia::render('Properties/Index', [
            'properties' => $properties,
            'stats' => [
                'total_properties' => $properties->count(),
                'total_units' => $totalUnitsCount,
                'total_billable_units' => $totalBillableUnits,
                'overall_occupancy' => $overallOccupancy,
                'total_monthly_revenue' => $totalMonthlyIncome,
            ],
        ]);
    }

    public function show(Request $request, Property $property, PropertyUnitBoardService $unitBoard): Response
    {
        $workspace = $this->resolveWorkspace($request);

        abort_if($property->workspace_id !== $workspace->id, 403, 'غير مصرح لك بعرض هذا العقار.');

        $currency = $workspace->currency ?? 'EGP';
        $currencySymbol = $workspace->currencySymbol();

        ['units' => $units, 'apartment_gender' => $propertyApartmentGender] = $unitBoard->build($property);
        $property->load('maintenanceRequests.reportedByRenter');

        $totalUnits = $units->count();
        $occupiedCount = $units->where('status', 'occupied')->count();
        $vacantCount = $units->where('status', 'vacant')->count();
        $maintenanceCount = $units->where('status', 'maintenance')->count();
        $occupancyRate = $totalUnits > 0 ? (int) round(($occupiedCount / $totalUnits) * 100) : 0;
        $monthlyRevenue = (float) $units->sum(fn ($u) => $u['active_lease']['rent_amount'] ?? 0);
        $billableUnitsCount = $property->units()->billableLeaves()->count();

        $hierarchySummary = [
            'apartments' => $units->where('unit_type', 'apartment')->count(),
            'rooms' => $units->where('unit_type', 'room')->count(),
            'beds' => $units->where('unit_type', 'bed')->count(),
            'shops' => $units->where('unit_type', 'shop')->count(),
        ];

        return Inertia::render('Properties/Show', [
            'property' => [
                'id' => $property->id,
                'name' => $property->name,
                'code' => $property->code,
                'property_type' => $property->property_type->value,
                'property_type_label' => $property->type_label,
                'apartment_gender' => $propertyApartmentGender,
                'address' => $property->address,
                'city' => $property->city,
                'district' => $property->district,
                'street' => $property->street,
                'building_number' => $property->building_number,
                'postal_code' => $property->postal_code,
                'google_maps_url' => $property->google_maps_url,
                'floors_count' => $property->floors_count ?? 1,
                'total_area' => $property->total_area,
                'year_built' => $property->year_built,
                'finishing_type' => $property->finishing_type,
                'amenities' => $property->amenities ?? [],
                'electricity_meter_number' => $property->electricity_meter_number,
                'water_meter_number' => $property->water_meter_number,
                'gas_meter_number' => $property->gas_meter_number,
                'guard_name' => $property->guard_name,
                'guard_phone' => $property->guard_phone,
                'deed_number' => $property->deed_number,
                'notes' => $property->notes,
                'cover_image_url' => $property->cover_image_url,
                'created_at' => $property->created_at?->format('d/m/Y'),
            ],
            'currency' => $currency,
            'currency_symbol' => $currencySymbol,
            'stats' => [
                'total_units' => $totalUnits,
                'billable_units' => $billableUnitsCount,
                'occupied' => $occupiedCount,
                'vacant' => $vacantCount,
                'maintenance' => $maintenanceCount,
                'occupancy_rate' => $occupancyRate,
                'monthly_revenue' => $monthlyRevenue,
                'hierarchy_summary' => $hierarchySummary,
            ],
            'units' => $units,
            'recent_maintenance' => $property->maintenanceRequests->take(5)->map(fn ($m) => [
                'id' => $m->id,
                'title' => $m->title,
                'status' => $m->status,
                'reported_at' => $m->reported_at->format('d/m/Y'),
                'renter_name' => $m->reportedByRenter->name ?? 'إدارة العقار',
            ]),
        ]);
    }

    public function create(Request $request): Response
    {
        // Only authorizes access to the active workspace; the form itself needs no workspace data.
        $this->resolveWorkspace($request);

        return Inertia::render('Properties/Create', [
            'property_types' => PropertyType::options(),
            'amenities_list' => [
                ['id' => 'elevator', 'label' => 'مصعد كهربائي', 'icon' => 'ArrowUpDown'],
                ['id' => 'parking', 'label' => 'موقف سيارات / جراج', 'icon' => 'Car'],
                ['id' => 'security_guard', 'label' => 'حارس أمن / مشرف', 'icon' => 'ShieldCheck'],
                ['id' => 'cctv', 'label' => 'كاميرات مراقبة CCTV', 'icon' => 'Video'],
                ['id' => 'water_tank', 'label' => 'خزان ومضخة مياه', 'icon' => 'Droplets'],
                ['id' => 'natural_gas', 'label' => 'غاز طبيعي مركزي', 'icon' => 'Flame'],
                ['id' => 'central_ac', 'label' => 'تكييف مركزي', 'icon' => 'Wind'],
                ['id' => 'fiber_internet', 'label' => 'إنترنت ألياف ضوئية', 'icon' => 'Wifi'],
                ['id' => 'intercom', 'label' => 'انتركم مرئي / صوتي', 'icon' => 'PhoneCall'],
                ['id' => 'fire_system', 'label' => 'نظام إطفاء وإنذار حريق', 'icon' => 'BellRing'],
                ['id' => 'generator', 'label' => 'مولد كهرباء طوارئ', 'icon' => 'Zap'],
                ['id' => 'waste_chute', 'label' => 'ممر ومجمع نفايات', 'icon' => 'Trash2'],
            ],
            'finishing_types' => [
                ['value' => 'deluxe', 'label' => 'الترا ديلوكس / فاخر جداً'],
                ['value' => 'super_lux', 'label' => 'سوبر لوكس'],
                ['value' => 'lux', 'label' => 'لوكس'],
                ['value' => 'standard', 'label' => 'تشطيب قياسي / عادي'],
                ['value' => 'semi_finished', 'label' => 'نصف تشطيب (محارة وحلوق)'],
                ['value' => 'commercial', 'label' => 'تشطيب تجاري / إداري'],
            ],
        ]);
    }

    public function store(
        StorePropertyRequest $request,
        PropertyUnitGeneratorService $generatorService,
        DocumentStorageService $storage
    ): RedirectResponse {
        $workspace = $this->resolveWorkspace($request);
        $validated = $request->validated();

        $coverImagePath = null;
        if ($request->hasFile('cover_image_file')) {
            $coverImagePath = $storage->storePublic($request->file('cover_image_file'), 'properties');
        }

        $address = $validated['address'] ?? null;
        if (! $address) {
            $address = $this->composeAddress($validated);
        }

        $property = DB::transaction(function () use ($workspace, $validated, $coverImagePath, $address, $generatorService) {
            $property = $workspace->properties()->create([
                'name' => $validated['name'],
                'code' => $validated['code'] ?? null,
                'property_type' => $validated['property_type'],
                'address' => $address ?: null,
                'city' => $validated['city'] ?? null,
                'district' => $validated['district'] ?? null,
                'street' => $validated['street'] ?? null,
                'building_number' => $validated['building_number'] ?? null,
                'postal_code' => $validated['postal_code'] ?? null,
                'google_maps_url' => $validated['google_maps_url'] ?? null,
                'floors_count' => $validated['floors_count'] ?? 1,
                'floor_number' => $validated['floor_number'] ?? null,
                'total_area' => $validated['total_area'] ?? null,
                'commercial_activity' => $validated['commercial_activity'] ?? null,
                'year_built' => $validated['year_built'] ?? null,
                'finishing_type' => $validated['finishing_type'] ?? null,
                'amenities' => $validated['amenities'] ?? [],
                'electricity_meter_number' => $validated['electricity_meter_number'] ?? null,
                'water_meter_number' => $validated['water_meter_number'] ?? null,
                'gas_meter_number' => $validated['gas_meter_number'] ?? null,
                'guard_name' => $validated['guard_name'] ?? null,
                'guard_phone' => $validated['guard_phone'] ?? null,
                'deed_number' => $validated['deed_number'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'cover_image' => $coverImagePath,
            ]);

            // Auto-generate units according to property type and configured rental structure
            $generatorService->generateForProperty(
                $property,
                $validated['generation_config'] ?? null,
                $validated['units'] ?? null
            );

            return $property;
        });

        if ($request->input('after_save') === 'index') {
            return redirect()->route('properties.index')->with('success', 'تمت إضافة العقار وتجهيز وحداته بنجاح');
        }

        return redirect()->route('properties.show', $property->id)->with('success', 'تم إنشاء العقار بنجاح وتجهيز وحداته');
    }

    public function update(
        UpdatePropertyRequest $request,
        Property $property,
        DocumentStorageService $storage
    ): RedirectResponse {
        $workspace = $this->resolveWorkspace($request);
        abort_if($property->workspace_id !== $workspace->id, 403, 'غير مصرح لك بتعديل هذا العقار.');

        $validated = $request->validated();

        if ($request->hasFile('cover_image_file')) {
            if ($property->cover_image) {
                $storage->delete($property->cover_image, $storage->getPublicDisk());
            }
            $validated['cover_image'] = $storage->storePublic($request->file('cover_image_file'), 'properties');
        }

        if (empty($validated['address'])) {
            $composedAddress = $this->composeAddress($validated);
            if ($composedAddress !== '') {
                $validated['address'] = $composedAddress;
            }
        }

        // Updating property attributes preserves all existing units, contracts, and tenants
        $property->update($validated);

        if ($request->header('referer')) {
            return back()->with('success', 'تم تحديث بيانات العقار بنجاح');
        }

        return redirect()->route('properties.show', $property->id)->with('success', 'تم تحديث بيانات العقار بنجاح');
    }

    public function storeUnit(StoreUnitRequest $request, Property $property): RedirectResponse
    {
        $workspace = $this->resolveWorkspace($request);

        abort_if($property->workspace_id !== $workspace->id, 403, 'غير مصرح لك بإدارة هذا العقار.');

        $validated = $request->validated();
        $isRentable = isset($validated['is_rentable']) ? (bool) $validated['is_rentable'] : true;
        $parentUnitId = $validated['parent_unit_id'] ?? null;

        // Enforce strict hierarchy through centralized UnitHierarchyService
        app(UnitHierarchyService::class)->validateHierarchy(
            unitType: $validated['unit_type'],
            parentUnitId: $parentUnitId,
            property: $property,
            workspace: $workspace,
        );

        DB::transaction(function () use ($workspace, $property, $validated, $isRentable, $parentUnitId) {
            $subscription = $workspace->activeSubscription()->with('plan')->first();
            $unitLimit = $subscription?->plan?->unit_limit;

            if ($subscription === null) {
                // Default to free starter plan limit (5 units) if no subscription exists
                $starterPlan = Plan::where('slug', 'starter')->first();
                $unitLimit = $starterPlan->unit_limit ?? 5;
            }

            $property->units()->create([
                'workspace_id' => $workspace->id,
                'parent_unit_id' => $parentUnitId,
                'name' => $validated['name'],
                'code' => $validated['code'] ?? null,
                'unit_type' => $validated['unit_type'],
                'is_rentable' => $isRentable,
            ]);

            if ($unitLimit !== null) {
                $projectedBillable = Unit::billableCountForWorkspace($workspace->id);
                if ($projectedBillable > $unitLimit) {
                    throw ValidationException::withMessages([
                        'unit_limit' => "لا يمكن إضافة الوحدة: لقد تجاوزت الحد الأقصى للوحدات المؤجرة المسموح به في باقة اشتراكك ({$unitLimit} وحدة). يرجى ترقية باقة الاشتراك.",
                    ]);
                }
            }
        });

        return back()->with('success', 'تمت إضافة الوحدة بنجاح');
    }

    public function updateUnit(UpdateUnitRequest $request, Property $property, Unit $unit): RedirectResponse
    {
        $workspace = $this->resolveWorkspace($request);

        abort_if($property->workspace_id !== $workspace->id, 403, 'غير مصرح لك بإدارة هذا العقار.');
        abort_if($unit->property_id !== $property->id || $unit->workspace_id !== $workspace->id, 403, 'غير مصرح لك بتعديل هذه الوحدة.');

        $validated = $request->validated();
        $isRentable = isset($validated['is_rentable']) ? (bool) $validated['is_rentable'] : true;
        $parentUnitId = $validated['parent_unit_id'] ?? null;

        // Enforce strict hierarchy and update rules through UnitHierarchyService
        app(UnitHierarchyService::class)->validateHierarchy(
            unitType: $validated['unit_type'],
            parentUnitId: $parentUnitId,
            property: $property,
            workspace: $workspace,
            existingUnit: $unit,
        );

        DB::transaction(function () use ($workspace, $unit, $validated, $isRentable, $parentUnitId) {
            $subscription = $workspace->activeSubscription()->with('plan')->first();
            $unitLimit = $subscription?->plan?->unit_limit;

            if ($subscription === null) {
                $starterPlan = Plan::where('slug', 'starter')->first();
                $unitLimit = $starterPlan->unit_limit ?? 5;
            }

            $unit->update([
                'parent_unit_id' => $parentUnitId,
                'name' => $validated['name'],
                'code' => $validated['code'] ?? null,
                'unit_type' => $validated['unit_type'],
                'is_rentable' => $isRentable,
            ]);

            if ($unitLimit !== null) {
                $projectedBillable = Unit::billableCountForWorkspace($workspace->id);
                if ($projectedBillable > $unitLimit) {
                    throw ValidationException::withMessages([
                        'unit_limit' => "لا يمكن تعديل الوحدة: التعديل يتجاوز الحد الأقصى للوحدات المؤجرة المسموح به في باقة اشتراكك ({$unitLimit} وحدة).",
                    ]);
                }
            }
        });

        return back()->with('success', 'تم تعديل بيانات الوحدة بنجاح');
    }

    public function destroyUnit(Request $request, Property $property, Unit $unit): RedirectResponse
    {
        $workspace = $this->resolveWorkspace($request);

        abort_if($property->workspace_id !== $workspace->id, 403, 'غير مصرح لك بإدارة هذا العقار.');
        abort_if($unit->property_id !== $property->id || $unit->workspace_id !== $workspace->id, 403, 'غير مصرح لك بحذف هذه الوحدة.');

        if ($unit->childUnits()->exists()) {
            throw ValidationException::withMessages([
                'unit' => 'لا يمكن حذف هذه الوحدة لأنها تحتوي على وحدات تابعة. يرجى حذف أو نقل الوحدات الفرعية أولاً.',
            ]);
        }

        if ($unit->leases()->exists()) {
            throw ValidationException::withMessages([
                'unit' => 'لا يمكن حذف هذه الوحدة لوجود سجلات عقود إيجار سابقة أو حالية مرتبطة بها للحفاظ على السجلات المالية.',
            ]);
        }

        $unit->delete();

        return back()->with('success', 'تم حذف الوحدة بنجاح');
    }

    /**
     * Build the address line from the detailed address fields when no full address was entered.
     *
     * @param  array<string, mixed>  $validated
     */
    private function composeAddress(array $validated): string
    {
        $addressParts = array_filter([
            ! empty($validated['building_number']) ? 'مبنى '.$validated['building_number'] : null,
            ! empty($validated['street']) ? 'شارع '.$validated['street'] : null,
            $validated['district'] ?? null,
            $validated['city'] ?? null,
        ], static fn (mixed $addressPart): bool => $addressPart !== null && $addressPart !== '');

        return implode('، ', $addressParts);
    }
}
