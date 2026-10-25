<?php

declare(strict_types=1);

namespace App\Enums;

enum MaintenanceStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'قيد الانتظار',
            self::InProgress => 'قيد التنفيذ',
            self::Completed => 'مكتمل',
        };
    }

    /**
     * Map loose or legacy strings (such as 'open' or 'closed') to enum instance.
     */
    public static function fromLoose(?string $value): self
    {
        return match ($value) {
            'open', 'pending' => self::Pending,
            'in_progress' => self::InProgress,
            'closed', 'completed' => self::Completed,
            default => self::Pending,
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $status) => [
            'value' => $status->value,
            'label' => $status->label(),
        ], self::cases());
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
