<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Models\Lease;

trait ResolvesRouteLease
{
    /**
     * The {lease} route parameter, when it has been bound to a model.
     */
    protected function routeLease(): ?Lease
    {
        $lease = $this->route('lease');

        return $lease instanceof Lease ? $lease : null;
    }
}
