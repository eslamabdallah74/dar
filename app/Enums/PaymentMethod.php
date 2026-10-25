<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case Cheque = 'cheque';
    case Card = 'card';
    case Online = 'online';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'نقدي',
            self::BankTransfer => 'تحويل بنكي',
            self::Cheque => 'شيك',
            self::Card => 'بطاقة بنكية',
            self::Online => 'دفع إلكتروني (بوابة الدفع)',
            self::Other => 'إنستاباي / فيزا / إلكتروني',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return [
            ['value' => self::Cash->value, 'label' => self::Cash->label()],
            ['value' => self::BankTransfer->value, 'label' => self::BankTransfer->label()],
            ['value' => self::Cheque->value, 'label' => self::Cheque->label()],
            ['value' => self::Other->value, 'label' => self::Other->label()],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
