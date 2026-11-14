<?php

declare(strict_types=1);

namespace App\Enums;

enum TenantStatus: string
{
    case Green = 'green';
    case Yellow = 'yellow';
    case Red = 'red';

    public function label(): string
    {
        return match ($this) {
            self::Green => 'ملتزم',
            self::Yellow => 'يحتاج متابعة',
            self::Red => 'غير ملتزم',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Green => 'مستأجر ملتزم بالسداد وموثوق في التعامل',
            self::Yellow => 'مستأجر يحتاج متابعة دورية أو لديه تأخيرات',
            self::Red => 'مستأجر غير ملتزم بالسداد أو لديه مشكلات سابقة',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Green => 'emerald',
            self::Yellow => 'amber',
            self::Red => 'rose',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Green => 'bg-emerald-50 text-emerald-800 border-emerald-200',
            self::Yellow => 'bg-amber-50 text-amber-800 border-amber-200',
            self::Red => 'bg-rose-50 text-rose-800 border-rose-200',
        };
    }

    public function dotClass(): string
    {
        return match ($this) {
            self::Green => 'bg-emerald-500',
            self::Yellow => 'bg-amber-500',
            self::Red => 'bg-rose-500',
        };
    }

    /**
     * @return array{value: string, label: string, description: string, color: string, badge_class: string, dot_class: string}
     */
    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'label' => $this->label(),
            'description' => $this->description(),
            'color' => $this->color(),
            'badge_class' => $this->badgeClass(),
            'dot_class' => $this->dotClass(),
        ];
    }
}
