<?php

declare(strict_types=1);

namespace App\Services\AI\Exceptions;

use RuntimeException;

class AiConfigurationException extends RuntimeException
{
    public function __construct(string $message = 'المساعد الذكي غير متاح حالياً.')
    {
        parent::__construct($message);
    }
}
