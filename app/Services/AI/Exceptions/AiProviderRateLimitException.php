<?php

declare(strict_types=1);

namespace App\Services\AI\Exceptions;

use RuntimeException;

class AiProviderRateLimitException extends RuntimeException
{
    public function __construct(string $message = 'تم الوصول للحد الأقصى، يرجى الانتظار.')
    {
        parent::__construct($message);
    }
}
