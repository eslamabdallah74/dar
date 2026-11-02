<?php

declare(strict_types=1);

namespace App\Services\AI\Exceptions;

use RuntimeException;

class AiWorkspaceDisabledException extends RuntimeException
{
    public function __construct(string $message = 'المساعد الذكي غير مفعّل لهذه المنشأة.')
    {
        parent::__construct($message);
    }
}
