<?php

declare(strict_types=1);

namespace App\Enums;

enum ExpenseStatus: string
{
    case PendingAmount = 'pending_amount';
    case Ready = 'ready';
    case Recorded = 'recorded';

    public function label(): string
    {
        return match ($this) {
            self::PendingAmount => 'بانتظار المبلغ',
            self::Ready => 'جاهز',
            self::Recorded => 'تم تسجيلها',
        };
    }

    /**
     * Map loose or legacy strings (such as 'ready_to_apply' or 'applied') to enum instance.
     */
    public static function fromLoose(?string $value): self
    {
        return match ($value) {
            'pending_amount' => self::PendingAmount,
            'ready', 'ready_to_apply' => self::Ready,
            'recorded', 'applied' => self::Recorded,
            default => self::Recorded,
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
