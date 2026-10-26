<?php

namespace App\Services;

use App\Enums\Gender;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Renter;
use App\Models\Unit;
use App\Models\Workspace;
use Illuminate\Validation\ValidationException;

class UnitHierarchyService
{
    /**
     * Allowed unit types with their Arabic labels and hierarchy rules.
     */
    public const TYPE_APARTMENT = 'apartment';

    public const TYPE_ROOM = 'room';

    public const TYPE_BED = 'bed';

    public const TYPE_SHOP = 'shop';

    public const ALLOWED_TYPES = [
        self::TYPE_APARTMENT,
        self::TYPE_ROOM,
        self::TYPE_BED,
        self::TYPE_SHOP,
    ];

    /**
     * Validate hierarchy rules for creating or updating a unit.
     *
     * @throws ValidationException
     */
    public function validateHierarchy(
        string $unitType,
        ?int $parentUnitId,
        Property $property,
        Workspace $workspace,
        ?Unit $existingUnit = null
    ): void {
        // 1. Validate unit_type is one of the supported types
        if (! in_array($unitType, self::ALLOWED_TYPES, true)) {
            throw ValidationException::withMessages([
                'unit_type' => 'نوع الوحدة غير صالح. الأنواع المسموح بها: شقة، أوضة، سرير، محل.',
            ]);
        }

        // 2. If updating an existing unit, prevent self-parent and circular parent assignment
        if ($existingUnit !== null && $parentUnitId !== null) {
            if ($parentUnitId === (int) $existingUnit->id) {
                throw ValidationException::withMessages([
                    'parent_unit_id' => 'لا يمكن للوحدة أن تكون أبًا لنفسها.',
                ]);
            }

            if (in_array($parentUnitId, $existingUnit->getDescendantIds(), true)) {
                throw ValidationException::withMessages([
                    'parent_unit_id' => 'لا يمكن اختيار وحدة تابعة لتكون وحدة أب (تجنب العلاقات الدائرية).',
                ]);
            }
        }

        // 3. If updating an existing unit with children, ensure type change does not invalidate descendants
        if ($existingUnit !== null && $existingUnit->unit_type !== $unitType) {
            $this->validateDescendantsCompatibility($existingUnit, $unitType);
        }

        // 4. Validate according to unit type
        match ($unitType) {
            self::TYPE_APARTMENT => $this->validateApartment($parentUnitId),
            self::TYPE_ROOM => $this->validateRoom($parentUnitId, $property, $workspace, $existingUnit),
            self::TYPE_BED => $this->validateBed($parentUnitId, $property, $workspace, $existingUnit),
            self::TYPE_SHOP => $this->validateShop($parentUnitId),
        };
    }

    /**
     * Validate rules for an Apartment.
     * An apartment cannot have a parent unit.
     */
    protected function validateApartment(?int $parentUnitId): void
    {
        if ($parentUnitId !== null) {
            throw ValidationException::withMessages([
                'parent_unit_id' => 'الشقة وحدة رئيسية ولا يمكن أن تكون تابعة لأوضة أو سرير.',
            ]);
        }
    }

    /**
     * Validate rules for a Room.
     * A room must have a parent whose type is apartment in the same property & workspace.
     */
    protected function validateRoom(
        ?int $parentUnitId,
        Property $property,
        Workspace $workspace,
        ?Unit $existingUnit = null
    ): void {
        if ($parentUnitId === null) {
            throw ValidationException::withMessages([
                'parent_unit_id' => 'يجب تحديد شقة كـ وحدة أب للأوضة.',
            ]);
        }

        $parent = $this->resolveAndAuthorizeParent($parentUnitId, $property, $workspace);

        if ($parent->unit_type === self::TYPE_ROOM) {
            throw ValidationException::withMessages([
                'parent_unit_id' => 'لا يمكن إضافة أوضة داخل أوضة. يجب أن تكون الأوضة تابعة لشقة.',
            ]);
        }

        if ($parent->unit_type === self::TYPE_BED) {
            throw ValidationException::withMessages([
                'parent_unit_id' => 'لا يمكن إضافة أوضة داخل سرير. يجب أن تكون الأوضة تابعة لشقة.',
            ]);
        }

        if ($parent->unit_type === self::TYPE_SHOP) {
            throw ValidationException::withMessages([
                'parent_unit_id' => 'المحل وحدة مستقلة ولا يمكن ربطه بوحدة أب.',
            ]);
        }

        if ($parent->unit_type !== self::TYPE_APARTMENT) {
            throw ValidationException::withMessages([
                'parent_unit_id' => 'يجب أن تكون الأوضة تابعة لشقة.',
            ]);
        }

        // Check if subdividing an apartment that currently has an active whole-unit lease
        if ($existingUnit === null || $existingUnit->parent_unit_id !== $parent->id) {
            $this->checkParentActiveLeaseConflict($parent);
        }
    }

    /**
     * Validate rules for a Bed.
     * A bed must have a parent whose type is room in the same property & workspace.
     */
    protected function validateBed(
        ?int $parentUnitId,
        Property $property,
        Workspace $workspace,
        ?Unit $existingUnit = null
    ): void {
        if ($parentUnitId === null) {
            throw ValidationException::withMessages([
                'parent_unit_id' => 'يجب تحديد أوضة كـ وحدة أب للسرير.',
            ]);
        }

        $parent = $this->resolveAndAuthorizeParent($parentUnitId, $property, $workspace);

        if ($parent->unit_type === self::TYPE_APARTMENT) {
            throw ValidationException::withMessages([
                'parent_unit_id' => 'لا يمكن إضافة سرير داخل شقة مباشرة. يجب أن يكون السرير تابعًا لأوضة.',
            ]);
        }

        if ($parent->unit_type === self::TYPE_BED) {
            throw ValidationException::withMessages([
                'parent_unit_id' => 'لا يمكن إضافة سرير داخل سرير. يجب أن يكون السرير تابعًا لأوضة.',
            ]);
        }

        if ($parent->unit_type === self::TYPE_SHOP) {
            throw ValidationException::withMessages([
                'parent_unit_id' => 'المحل وحدة مستقلة ولا يمكن ربطه بوحدة أب.',
            ]);
        }

        if ($parent->unit_type !== self::TYPE_ROOM) {
            throw ValidationException::withMessages([
                'parent_unit_id' => 'يجب أن يكون السرير تابعًا لأوضة.',
            ]);
        }

        // Check if subdividing a room that currently has an active lease (or whose apartment has an active lease)
        if ($existingUnit === null || $existingUnit->parent_unit_id !== $parent->id) {
            $this->checkParentActiveLeaseConflict($parent);
        }
    }

    /**
     * Validate rules for a Shop.
     * A shop is a standalone unit without parent or children.
     */
    protected function validateShop(?int $parentUnitId): void
    {
        if ($parentUnitId !== null) {
            throw ValidationException::withMessages([
                'parent_unit_id' => 'المحل وحدة مستقلة ولا يمكن ربطه بوحدة أب.',
            ]);
        }
    }

    /**
     * Resolve parent unit and ensure it belongs to the authorized property and workspace.
     */
    protected function resolveAndAuthorizeParent(int $parentUnitId, Property $property, Workspace $workspace): Unit
    {
        $parent = Unit::withoutGlobalScopes()->find($parentUnitId);

        if (! $parent || (int) $parent->workspace_id !== (int) $workspace->id) {
            throw ValidationException::withMessages([
                'parent_unit_id' => 'الوحدة الأب المحددة لا تنتمي لمساحة العمل الحالية.',
            ]);
        }

        if ((int) $parent->property_id !== (int) $property->id) {
            throw ValidationException::withMessages([
                'parent_unit_id' => 'الوحدة الأب المحددة تنتمي إلى عقار آخر.',
            ]);
        }

        return $parent;
    }

    /**
     * Check if parent unit or any of its ancestors has an active lease preventing subdivision.
     */
    protected function checkParentActiveLeaseConflict(Unit $parent): void
    {
        $ancestorAndSelfIds = array_values(array_unique(array_merge(
            [(int) $parent->id],
            $parent->getAncestorIds()
        )));

        $hasActiveLease = Lease::withoutGlobalScopes()
            ->whereIn('unit_id', $ancestorAndSelfIds)
            ->where('status', 'active')
            ->where('ends_on', '>=', today())
            ->exists();

        if ($hasActiveLease) {
            throw ValidationException::withMessages([
                'parent_unit_id' => 'لا يمكن تقسيم هذه الوحدة حاليًا لوجود عقد إيجار نشط يتعارض مع التقسيم المطلوب.',
            ]);
        }
    }

    /**
     * Validate that changing a unit's type does not violate hierarchy constraints of its existing descendants.
     */
    protected function validateDescendantsCompatibility(Unit $unit, string $newUnitType): void
    {
        $directChildren = $unit->childUnits()->get();

        if ($directChildren->isEmpty()) {
            return;
        }

        // If unit has children, it cannot become a Bed or Shop (terminal units)
        if ($newUnitType === self::TYPE_BED || $newUnitType === self::TYPE_SHOP) {
            throw ValidationException::withMessages([
                'unit_type' => 'لا يمكن تغيير نوع هذه الوحدة قبل معالجة الوحدات التابعة لها، لأن التغيير سيؤدي إلى هيكل غير صالح.',
            ]);
        }

        // If new type is room, all children must be beds
        if ($newUnitType === self::TYPE_ROOM) {
            foreach ($directChildren as $child) {
                if ($child->unit_type !== self::TYPE_BED) {
                    throw ValidationException::withMessages([
                        'unit_type' => 'لا يمكن تغيير نوع هذه الوحدة قبل معالجة الوحدات التابعة لها، لأن التغيير سيؤدي إلى هيكل غير صالح.',
                    ]);
                }
            }
        }

        // If new type is apartment, all children must be rooms
        if ($newUnitType === self::TYPE_APARTMENT) {
            foreach ($directChildren as $child) {
                if ($child->unit_type !== self::TYPE_ROOM) {
                    throw ValidationException::withMessages([
                        'unit_type' => 'لا يمكن تغيير نوع هذه الوحدة قبل معالجة الوحدات التابعة لها، لأن التغيير سيؤدي إلى هيكل غير صالح.',
                    ]);
                }
            }
        }
    }

    /**
     * Build full breadcrumb path for a unit.
     * Example: "عمارة النور / شقة 101 / أوضة 1 / سرير 1"
     */
    public function buildHierarchyBreadcrumb(Unit $unit, ?string $propertyName = null): string
    {
        $parts = [];

        if ($propertyName) {
            $parts[] = $propertyName;
        } elseif ($unit->relationLoaded('property') && $unit->property) {
            $parts[] = $unit->property->name;
        }

        $ancestorNames = [];
        $currentParent = $unit->parentUnit;

        while ($currentParent !== null) {
            $ancestorNames[] = $currentParent->name;
            $currentParent = $currentParent->parentUnit;
        }

        // Ancestors were gathered bottom-up, reverse them for top-down display
        $ancestorNames = array_reverse($ancestorNames);

        foreach ($ancestorNames as $name) {
            $parts[] = $name;
        }

        $parts[] = $unit->name;

        return implode(' / ', $parts);
    }

    /**
     * Compute apartment gender classification from active renters or occupants.
     *
     * @param  iterable<mixed>  $rentersOrOccupants
     * @return array{
     *     status: 'male'|'female'|'mixed'|'unspecified',
     *     label: string,
     *     short_label: string,
     *     description: string,
     *     badge_class: string,
     *     male_count: int,
     *     female_count: int,
     *     unspecified_count: int,
     *     total_active_renters: int
     * }
     */
    public function computeApartmentGender(iterable $rentersOrOccupants): array
    {
        $maleCount = 0;
        $femaleCount = 0;
        $unspecifiedCount = 0;

        foreach ($rentersOrOccupants as $occupant) {
            match ($this->occupantGender($occupant)) {
                Gender::Male->value => $maleCount++,
                Gender::Female->value => $femaleCount++,
                default => $unspecifiedCount++,
            };
        }

        $totalOccupants = $maleCount + $femaleCount + $unspecifiedCount;

        $classification = match (true) {
            $maleCount > 0 && $femaleCount > 0 => [
                'status' => 'mixed',
                'label' => 'سكن مختلط',
                'short_label' => 'مختلط',
                'description' => "الشقة تضم حالياً نزلاء ذكور ({$maleCount}) وإناث ({$femaleCount})",
                'badge_class' => 'bg-amber-50 text-amber-800 border-amber-200',
            ],
            $maleCount > 0 => [
                'status' => 'male',
                'label' => Gender::Male->apartmentLabel(),
                'short_label' => 'شباب',
                'description' => "الشقة مصنفة سكن شباب لوجود نزلاء ذكور ({$maleCount})",
                'badge_class' => 'bg-sky-50 text-sky-800 border-sky-200',
            ],
            $femaleCount > 0 => [
                'status' => 'female',
                'label' => Gender::Female->apartmentLabel(),
                'short_label' => 'بنات',
                'description' => "الشقة مصنفة سكن بنات لوجود نزيلات إناث ({$femaleCount})",
                'badge_class' => 'bg-rose-50 text-rose-800 border-rose-200',
            ],
            default => [
                'status' => 'unspecified',
                'label' => 'غير محدد',
                'short_label' => 'غير محدد',
                'description' => $totalOccupants === 0
                    ? 'الشقة شاغرة حالياً — تتحدد فئة السكن تلقائياً مع أول مستأجر'
                    : 'يوجد مستأجرون حاليون دون تحديد الجنس',
                'badge_class' => 'bg-slate-50 text-slate-600 border-slate-200',
            ],
        };

        return [
            ...$classification,
            'male_count' => $maleCount,
            'female_count' => $femaleCount,
            'unspecified_count' => $unspecifiedCount,
            'total_active_renters' => $totalOccupants,
        ];
    }

    /**
     * Read the gender of a Renter model, an occupant array, or any object exposing `gender`.
     */
    private function occupantGender(mixed $occupant): mixed
    {
        if (is_array($occupant)) {
            return $occupant['gender'] ?? null;
        }

        $gender = is_object($occupant) ? ($occupant->gender ?? null) : null;

        return $gender instanceof Gender ? $gender->value : $gender;
    }
}
