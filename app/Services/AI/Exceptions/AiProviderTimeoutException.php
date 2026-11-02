<?php

declare(strict_types=1);

namespace App\Services\AI\Exceptions;

use RuntimeException;

class AiProviderTimeoutException extends RuntimeException
{
    public function __construct(string $message = 'انتهت مهلة الاتصال، يرجى المحاولة مرة أخرى.')
    {
        parent::__construct($message);
    }
}
