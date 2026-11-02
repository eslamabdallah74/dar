<?php

declare(strict_types=1);

namespace App\Enums;

enum PropertyType: string
{
    case Building = 'building';
    case Apartment = 'apartment';
    case Shop = 'shop';
    case Commercial = 'commercial';
    case Villa = 'villa';
    case Standalone = 'standalone';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Building => 'عمارة سكنية',
            self::Apartment => 'شقة',
            self::Shop => 'محل تجاري',
            self::Commercial => 'مركز / مبنى تجاري',
            self::Villa => 'فيلا / تاون هاوس',
            self::Standalone => 'وحدة مستقلة',
            self::Other => 'عقار آخر / أرض أو مخزن',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Building => 'متعددة الوحدات',
            self::Apartment => 'كاملة أو غرف وأسرّة',
            self::Shop => 'تجاري مستقل',
            self::Commercial => 'محلات ومكاتب',
            self::Villa => 'عقار عائلي',
            self::Standalone => 'وحدة منفصلة',
            self::Other => 'متعدد الاستخدام',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Building => 'عمارة متعددة الأدوار تحتوي على شقق سكنية أو غرف، مع إمكانية إضافة محلات أرضية.',
            self::Apartment => 'شقة منفصلة للتأجير بالكامل، أو مقسمة إلى غرف مستقلة، أو بنظام الأسرّة الفردية.',
            self::Shop => 'محل أو معرض تجاري مستقل بذاته بنشاط ومساحة تجارية دون أدوار أو شقق سكنية.',
            self::Commercial => 'مركز أو مجمع يضم محلات تجارية ومكاتب إدارية متعددة.',
            self::Villa => 'فيلا مستقلة قابلة للتأجير بالكامل أو مقسمة إلى أجنحة وغرف.',
            self::Standalone => 'شقة أو محل تجاري مستقل بذاته في موقع محدد دون عمارة تابعة.',
            self::Other => 'مستودع، أرض فضاء، استراحة، أو أي نوع عقار مخصص آخر.',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Building => 'building',
            self::Apartment, self::Villa => 'home',
            self::Shop, self::Commercial => 'store',
            self::Standalone => 'door-open',
            self::Other => 'box',
        };
    }

    public function suggestedUnits(): string
    {
        return match ($this) {
            self::Building => 'building_floors_apartments',
            self::Apartment => 'apartment_rental_modes',
            self::Shop => 'single_shop',
            self::Commercial => 'shops_and_offices',
            self::Villa => 'whole_or_rooms',
            self::Standalone => 'single',
            self::Other => 'custom',
        };
    }

    /**
     * @return array<int, array{value: string, label: string, badge: string, description: string, icon: string, suggested_units: string}>
     */
    public static function options(): array
    {
        $excluded = [self::Standalone, self::Commercial];
        $cases = array_values(array_filter(self::cases(), fn (self $type) => ! in_array($type, $excluded, true)));

        return array_map(fn (self $type) => [
            'value' => $type->value,
            'label' => $type->label(),
            'badge' => $type->badge(),
            'description' => $type->description(),
            'icon' => $type->icon(),
            'suggested_units' => $type->suggestedUnits(),
        ], $cases);
    }
}
