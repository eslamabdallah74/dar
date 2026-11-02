<?php

declare(strict_types=1);

namespace App\Services\AI\Exceptions;

use RuntimeException;

class AiExecutionException extends RuntimeException
{
    public function __construct(string $message = 'تعذر تنفيذ الطلب.')
    {
        parent::__construct($message);
    }
}
