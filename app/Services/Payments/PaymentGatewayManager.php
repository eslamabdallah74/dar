<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGatewayInterface;
use InvalidArgumentException;

class PaymentGatewayManager
{
    protected array $drivers = [];

    public function driver(?string $driver = null): PaymentGatewayInterface
    {
        $driver = $driver ?: config('services.payment_gateway.driver', 'log');

        if (! isset($this->drivers[$driver])) {
            $this->drivers[$driver] = $this->createDriver($driver);
        }

        return $this->drivers[$driver];
    }

    protected function createDriver(string $driver): PaymentGatewayInterface
    {
        return match ($driver) {
            'moyasar' => app(MoyasarPaymentGateway::class),
            'log' => app(LogPaymentGateway::class),
            default => throw new InvalidArgumentException("بوابة الدفع الإلكتروني غير مدعومة: {$driver}"),
        };
    }
}
