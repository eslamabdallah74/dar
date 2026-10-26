<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesAuthenticatedUser;
use App\Http\Controllers\Concerns\ResolvesCurrentWorkspace;

abstract class Controller
{
    use ResolvesAuthenticatedUser, ResolvesCurrentWorkspace;
}
