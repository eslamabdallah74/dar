<?php

namespace App\Http\Controllers;

use App\Http\Requests\Properties\StorePropertyRequest;
use App\Http\Requests\Properties\StoreUnitRequest;
use App\Http\Requests\Properties\UpdateUnitRequest;
use App\Models\Plan;
use App\Models\Property;
use App\Models\Unit;
use App\Services\UnitHierarchyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PropertyController extends Controller
{
    public function index(Request $request): Response
    {
        $workspace = $this->resolveWorkspace($request);

        $billableCounts = Unit::withoutGlobalScopes()
            ->where('workspace_id', $workspace->id)
            ->billableLeaves()
            ->select('property_id', DB::raw('count(*) as count'))
            ->groupBy('property_id')
            ->pluck('count', 'property_id');

        $properties = $workspace->properties()
            ->withCount('units')
            ->with(['units.leases' => fn ($q) => $q->where('status', 'active')])
            ->get()
            ->map(function (Property $property) use ($billableCounts) {
                $units = $property->units;
                $totalUnits = $units->count();
                $occupiedCount = $units->filter(fn ($u) => $u->leases->isNotEmpty())->count();
                $vacantCount = max(0, $totalUnits - $occupiedCount);
                $occupancyRate = $totalUnits > 0 ? (int) round(($occupiedCount / $totalUnits) * 100) : 0;
                $billableUnitsCount = (int) ($billableCounts[$property->id] ?? 0);

                $monthlyRevenue = (float) $units->flatMap->leases->sum('rent_amount');

                return [
                    'id' => $property->id,
                    'name' => $property->name,
                    'property_type' => $property->property_type,
                    'type_label' => match ($property->property_type) {
                        'building' => 'عمارة سكنية',
                        'villa' => 'فيلا',
                        default => 'عقار',
                    },
                    'address' => $property->address ?? 'القاهرة، جمهورية مصر العربية',
                    'total_units' => $totalUnits,
                    'billable_units' => $billableUnitsCount,
                    'occupied_units' => $occupiedCount,
                    'vacant_units' => $vacantCount,
                    'occupancy_rate' => $occupancyRate,
                    'monthly_revenue' => $monthlyRevenue,
                ];
            });

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

    public function show(Request $request, Property $property): Response
    {
        $workspace = $this->resolveWorkspace($request);

        abort_if($property->workspace_id !== $workspace->id, 403, 'غير مصرح لك بعرض هذا العقار.');

        $currency = $workspace->currency ?? 'EGP';
        $currencySymbol = match ($currency) {
            'SAR' => 'ر.س',
            'AED' => 'د.إ',
            'USD' => '$',
            default => 'ج.م',
        };

        $hierarchyService = app(UnitHierarchyService::class);

        $property->load([
            'units' => fn ($q) => $q->with([
                'parentUnit.parentUnit',
                'childUnits' => fn ($c) => $c->with([
                    'childUnits' => fn ($b) => $b->with([
                        'leases' => fn ($l) => $l->where('status', 'active')->with('renter'),
                        'maintenanceRequests' => fn ($m) => $m->whereIn('status', ['open', 'in_progress']),
                    ]),
                    'leases' => fn ($l) => $l->where('status', 'active')->with('renter'),
                    'maintenanceRequests' => fn ($m) => $m->whereIn('status', ['open', 'in_progress']),
                ]),
                'leases' => fn ($l) => $l->where('status', 'active')->with('renter'),
                'maintenanceRequests' => fn ($m) => $m->whereIn('status', ['open', 'in_progress']),
            ]),
            'maintenanceRequests.reportedByRenter',
        ]);

        // Pre-index active leases and parent/child tree in memory to avoid N+1 recursive SQL queries
        $allPropertyUnits = $property->units;
        $activeLeasesByUnitId = [];
        $parentMap = [];
        $childrenMap = [];
        foreach ($allPropertyUnits as $u) {
            $parentMap[$u->id] = $u->parent_unit_id;
            if ($u->parent_unit_id !== null) {
                $childrenMap[$u->parent_unit_id][] = $u->id;
            }
            $al = $u->leases->first();
            if ($al) {
                $activeLeasesByUnitId[$u->id] = $al;
            }
        }

        $getAncestorsInMemory = function (int $unitId) use ($parentMap): array {
            $ancestors = [];
            $curr = $parentMap[$unitId] ?? null;
            while ($curr !== null && ! in_array($curr, $ancestors, true)) {
                $ancestors[] = $curr;
                $curr = $parentMap[$curr] ?? null;
            }

            return $ancestors;
        };

        $getDescendantsInMemory = function (int $unitId) use ($childrenMap): array {
            $descendants = [];
            $queue = $childrenMap[$unitId] ?? [];
            while (! empty($queue)) {
                $childId = array_shift($queue);
                $descendants[] = $childId;
                if (! empty($childrenMap[$childId])) {
                    foreach ($childrenMap[$childId] as $grandChildId) {
                        $queue[] = $grandChildId;
                    }
                }
            }

            return $descendants;
        };

        $units = $allPropertyUnits->map(function (Unit $unit) use ($hierarchyService, $property, $activeLeasesByUnitId, $getAncestorsInMemory, $getDescendantsInMemory) {
            $activeLease = $unit->leases->first();
            $hasOpenMaintenance = $unit->maintenanceRequests->isNotEmpty();

            $status = 'vacant';
            $statusLabel = 'شاغرة';
            if ($activeLease) {
                $status = 'occupied';
                $statusLabel = 'مؤجرة';
            } elseif ($hasOpenMaintenance) {
                $status = 'maintenance';
                $statusLabel = 'تحت الصيانة';
            } elseif (! $unit->is_rentable) {
                $status = 'unavailable';
                $statusLabel = 'غير متاحة';
            }

            // Detect floor / grouping
            $floorName = 'الوحدات الرئيسية';
            if ($unit->parentUnit) {
                $floorName = $unit->parentUnit->name;
            } elseif (preg_match('/(الدور\s+[^\)\-]+|الطابق\s+[^\)\-]+)/u', $unit->name, $matches)) {
                $floorName = trim($matches[0]);
            } elseif (preg_match('/(?:APT|شقة|وحدة)[\s\-]*([1-9])\d{2}/ui', ($unit->code ?? '').' '.$unit->name, $numMatches)) {
                $floorNum = (int) $numMatches[1];
                $floorNames = [1 => 'الدور الأول', 2 => 'الدور الثاني', 3 => 'الدور الثالث', 4 => 'الدور الرابع', 5 => 'الدور الخامس'];
                $floorName = $floorNames[$floorNum] ?? ('الدور '.$floorNum);
            }

            // Ancestor / descendant active lease checks in memory
            $ancestorIds = $getAncestorsInMemory($unit->id);
            $descendantIds = $getDescendantsInMemory($unit->id);

            $ancestorHasActiveLease = false;
            foreach ($ancestorIds as $aId) {
                if (isset($activeLeasesByUnitId[$aId])) {
                    $ancestorHasActiveLease = true;
                    break;
                }
            }

            $descendantHasActiveLease = false;
            foreach ($descendantIds as $dId) {
                if (isset($activeLeasesByUnitId[$dId])) {
                    $descendantHasActiveLease = true;
                    break;
                }
            }

            // Can subdivide check:
            // Only apartments and rooms can have children.
            // Apartment can add rooms if it has no active lease.
            // Room can add beds if neither it nor its parent apartment has an active lease.
            $canSubdivide = false;
            $allowedChildType = null;
            $allowedChildLabel = null;

            if ($unit->unit_type === 'apartment') {
                $allowedChildType = 'room';
                $allowedChildLabel = 'إضافة أوضة';
                $canSubdivide = ! $activeLease;
            } elseif ($unit->unit_type === 'room') {
                $allowedChildType = 'bed';
                $allowedChildLabel = 'إضافة سرير';
                $canSubdivide = ! $activeLease && ! $ancestorHasActiveLease;
            }

            // Can rent check: rentable = true, and no conflict with self, ancestors, or descendants
            $canRent = $unit->is_rentable && ! $activeLease && ! $ancestorHasActiveLease && ! $descendantHasActiveLease;

            $breadcrumb = $hierarchyService->buildHierarchyBreadcrumb($unit, $property->name);

            return [
                'id' => $unit->id,
                'parent_unit_id' => $unit->parent_unit_id,
                'parent_unit_name' => $unit->parentUnit?->name,
                'floor_name' => $floorName,
                'code' => $unit->code ?? '',
                'name' => $unit->name,
                'unit_type' => $unit->unit_type,
                'type_label' => match ($unit->unit_type) {
                    'apartment' => 'شقة',
                    'room' => 'أوضة',
                    'bed' => 'سرير',
                    'shop' => 'محل',
                    default => 'وحدة',
                },
                'breadcrumb' => $breadcrumb,
                'is_rentable' => (bool) $unit->is_rentable,
                'can_rent' => $canRent,
                'can_subdivide' => $canSubdivide,
                'allowed_child_type' => $allowedChildType,
                'allowed_child_label' => $allowedChildLabel,
                'status' => $status,
                'status_label' => $statusLabel,
                'ancestor_has_active_lease' => $ancestorHasActiveLease,
                'descendant_has_active_lease' => $descendantHasActiveLease,
                'active_lease' => $activeLease ? [
                    'id' => $activeLease->id,
                    'contract_number' => 'CNT-'.str_pad((string) $activeLease->id, 5, '0', STR_PAD_LEFT),
                    'renter_id' => $activeLease->renter_id,
                    'renter_name' => $activeLease->renter?->name ?? 'مستأجر',
                    'renter_phone' => $activeLease->renter?->phone ?? '',
                    'renter_national_id' => $activeLease->renter?->national_id ?? '',
                    'rent_amount' => (float) $activeLease->rent_amount,
                    'security_deposit_amount' => $activeLease->security_deposit_amount ? (float) $activeLease->security_deposit_amount : null,
                    'security_deposit_status' => $activeLease->security_deposit_status,
                    'starts_on' => $activeLease->starts_on?->format('d/m/Y'),
                    'ends_on' => $activeLease->ends_on?->format('d/m/Y'),
                    'days_remaining' => $activeLease->ends_on ? max(0, (int) now()->startOfDay()->diffInDays($activeLease->ends_on, false)) : null,
                    'billing_interval' => $activeLease->billing_interval ?? 'monthly',
                ] : null,
                'child_units' => $unit->childUnits->map(fn (Unit $c) => [
                    'id' => $c->id,
                    'code' => $c->code ?? '',
                    'name' => $c->name,
                    'unit_type' => $c->unit_type,
                    'type_label' => match ($c->unit_type) {
                        'apartment' => 'شقة',
                        'room' => 'أوضة',
                        'bed' => 'سرير',
                        'shop' => 'محل',
                        default => 'وحدة',
                    },
                    'status' => $c->leases->isNotEmpty() ? 'occupied' : 'vacant',
                    'status_label' => $c->leases->isNotEmpty() ? 'مؤجرة' : 'شاغرة',
                    'is_rentable' => (bool) $c->is_rentable,
                ])->values()->all(),
                'has_maintenance' => $hasOpenMaintenance,
                'maintenance_requests' => $unit->maintenanceRequests->map(fn ($m) => [
                    'id' => $m->id,
                    'title' => $m->title,
                    'status' => $m->status,
                    'reported_at' => $m->reported_at?->format('d/m/Y'),
                ])->values()->all(),
            ];
        });

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
                'property_type' => $property->property_type,
                'property_type_label' => match ($property->property_type) {
                    'building' => 'عمارة سكنية',
                    'commercial' => 'عقار تجاري',
                    'villa' => 'فيلا مستقلة',
                    'standalone' => 'وحدة أو محل مستقل',
                    default => 'عقار',
                },
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
                'reported_at' => $m->reported_at?->format('d/m/Y'),
                'renter_name' => $m->reportedByRenter?->name ?? 'إدارة العقار',
            ]),
        ]);
    }

    public function create(Request $request): Response
    {
        $workspace = $this->resolveWorkspace($request);

        return Inertia::render('Properties/Create', [
            'property_types' => [
                [
                    'value' => 'building',
                    'label' => 'عمارة سكنية',
                    'badge' => 'الأكثر استخداماً',
                    'description' => 'عمارة متعددة الأدوار تحتوي على شقق سكنية أو غرف ومحلات.',
                    'icon' => 'building',
                    'suggested_units' => 'apartments_and_shops',
                ],
                [
                    'value' => 'commercial',
                    'label' => 'عقار تجاري / إداري',
                    'badge' => 'محلات ومكاتب',
                    'description' => 'مركز أو برج تجاري يضم محلات تجارية ومكاتب إدارية.',
                    'icon' => 'store',
                    'suggested_units' => 'shops_and_offices',
                ],
                [
                    'value' => 'villa',
                    'label' => 'فيلا / تاون هاوس',
                    'badge' => 'عقار عائلي',
                    'description' => 'فيلا مستقلة قابلة للتأجير بالكامل أو مقسمة إلى أجنحة وغرف.',
                    'icon' => 'home',
                    'suggested_units' => 'whole_or_rooms',
                ],
                [
                    'value' => 'standalone',
                    'label' => 'شقة أو محل مستقل',
                    'badge' => 'وحدة منفصلة',
                    'description' => 'شقة أو محل تجاري مستقل بذاته في موقع محدد دون عمارة تابعة.',
                    'icon' => 'door-open',
                    'suggested_units' => 'single',
                ],
                [
                    'value' => 'other',
                    'label' => 'عقار آخر / أرض أو مخزن',
                    'badge' => 'متعدد الاستخدام',
                    'description' => 'مستودع، أرض فضاء، استراحة، أو أي نوع عقار مخصص آخر.',
                    'icon' => 'box',
                    'suggested_units' => 'custom',
                ],
            ],
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

    public function store(StorePropertyRequest $request): RedirectResponse
    {
        $workspace = $this->resolveWorkspace($request);
        $validated = $request->validated();

        $coverImagePath = null;
        if ($request->hasFile('cover_image_file')) {
            $coverImagePath = $request->file('cover_image_file')->store('properties', 'public');
        }

        // Build combined address if address is empty but detailed fields are filled
        $address = $validated['address'] ?? null;
        if (! $address) {
            $addressParts = array_filter([
                ! empty($validated['building_number']) ? 'مبنى '.$validated['building_number'] : null,
                ! empty($validated['street']) ? 'شارع '.$validated['street'] : null,
                $validated['district'] ?? null,
                $validated['city'] ?? null,
            ]);
            $address = implode('، ', $addressParts);
        }

        $property = DB::transaction(function () use ($workspace, $validated, $coverImagePath, $address) {
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
                'total_area' => $validated['total_area'] ?? null,
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

            // Auto-generate units if submitted
            if (! empty($validated['units'])) {
                foreach ($validated['units'] as $unitData) {
                    $property->units()->create([
                        'workspace_id' => $workspace->id,
                        'name' => $unitData['name'],
                        'code' => $unitData['code'] ?? null,
                        'unit_type' => $unitData['unit_type'] ?? 'apartment',
                        'is_rentable' => $unitData['is_rentable'] ?? true,
                        'parent_unit_id' => null,
                    ]);
                }
            }

            return $property;
        });

        if ($request->input('after_save') === 'index') {
            return redirect()->route('properties.index')->with('success', 'تمت إضافة العقار بنجاح');
        }

        return redirect()->route('properties.show', $property->id)->with('success', 'تم إنشاء العقار بنجاح وتجهيز وحداته');
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
                $unitLimit = $starterPlan?->unit_limit ?? 5;
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
                $unitLimit = $starterPlan?->unit_limit ?? 5;
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
}
