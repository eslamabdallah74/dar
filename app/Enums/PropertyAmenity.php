<?php

declare(strict_types=1);

namespace App\Enums;

enum PropertyAmenity: string
{
    case Elevator = 'elevator';
    case Parking = 'parking';
    case SecurityGuard = 'security_guard';
    case Cctv = 'cctv';
    case WaterTank = 'water_tank';
    case NaturalGas = 'natural_gas';
    case CentralAc = 'central_ac';
    case FiberInternet = 'fiber_internet';
    case Intercom = 'intercom';
    case FireSystem = 'fire_system';
    case Generator = 'generator';
    case WasteChute = 'waste_chute';

    public function label(): string
    {
        return match ($this) {
            self::Elevator => 'مصعد كهربائي',
            self::Parking => 'موقف سيارات / جراج',
            self::SecurityGuard => 'حارس أمن / مشرف',
            self::Cctv => 'كاميرات مراقبة CCTV',
            self::WaterTank => 'خزان ومضخة مياه',
            self::NaturalGas => 'غاز طبيعي مركزي',
            self::CentralAc => 'تكييف مركزي',
            self::FiberInternet => 'إنترنت ألياف ضوئية',
            self::Intercom => 'انتركم مرئي / صوتي',
            self::FireSystem => 'نظام إطفاء وإنذار حريق',
            self::Generator => 'مولد كهرباء طوارئ',
            self::WasteChute => 'ممر ومجمع نفايات',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Elevator => 'ArrowUpDown',
            self::Parking => 'Car',
            self::SecurityGuard => 'ShieldCheck',
            self::Cctv => 'Video',
            self::WaterTank => 'Droplets',
            self::NaturalGas => 'Flame',
            self::CentralAc => 'Wind',
            self::FiberInternet => 'Wifi',
            self::Intercom => 'PhoneCall',
            self::FireSystem => 'BellRing',
            self::Generator => 'Zap',
            self::WasteChute => 'Trash2',
        };
    }

    /**
     * @return array<int, array{id: string, label: string, icon: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $case) => [
            'id' => $case->value,
            'label' => $case->label(),
            'icon' => $case->icon(),
        ], self::cases());
    }
}
