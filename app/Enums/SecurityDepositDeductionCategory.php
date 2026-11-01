<?php

declare(strict_types=1);

namespace App\Enums;

enum SecurityDepositDeductionCategory: string
{
    case UnitDamage = 'unit_damage';
    case Maintenance = 'maintenance';
    case UnpaidDues = 'unpaid_dues';
    case UnpaidRent = 'unpaid_rent';
    case Cleaning = 'cleaning';
    case Other = 'other';

    // Legacy categories preserved for backwards compatibility with existing records
    case FurnitureDamage = 'furniture_damage';
    case FixtureDamage = 'fixture_damage';
    case MissingItems = 'missing_items';
    case OtherDamage = 'other_damage';

    public function label(): string
    {
        return match ($this) {
            self::UnitDamage => 'تلفيات في الوحدة',
            self::Maintenance => 'إصلاحات وصيانة',
            self::UnpaidDues => 'مستحقات غير مدفوعة',
            self::UnpaidRent => 'متأخرات إيجارية مستحقة',
            self::Cleaning => 'تنظيف الوحدة',
            self::Other => 'أخرى',
            self::FurnitureDamage => 'تلف أثاث أو أجهزة',
            self::FixtureDamage => 'تلف تركيبات ومرافق',
            self::MissingItems => 'مفقودات ومحتويات ناقصة',
            self::OtherDamage => 'أضرار موثقة أخرى',
        };
    }

    /**
     * Unpaid rent and dues are offset against the renter's outstanding rent charges.
     */
    public function settlesRentArrears(): bool
    {
        return $this === self::UnpaidRent || $this === self::UnpaidDues;
    }

    /**
     * The 5 primary options suggested in the UI workflow.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return [
            ['value' => self::UnitDamage->value, 'label' => self::UnitDamage->label()],
            ['value' => self::Maintenance->value, 'label' => self::Maintenance->label()],
            ['value' => self::UnpaidDues->value, 'label' => self::UnpaidDues->label()],
            ['value' => self::Cleaning->value, 'label' => self::Cleaning->label()],
            ['value' => self::Other->value, 'label' => self::Other->label()],
        ];
    }
}
