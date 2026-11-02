<?php

namespace App\Support;

use App\Models\Workspace;

class CurrentWorkspace
{
    protected ?Workspace $workspace = null;

    public function set(?Workspace $workspace): void
    {
        $this->workspace = $workspace;
    }

    public function get(): ?Workspace
    {
        return $this->workspace;
    }

    public function id(): ?int
    {
        return $this->workspace?->id;
    }

    public function check(): bool
    {
        return $this->workspace !== null;
    }

    public function clear(): void
    {
        $this->workspace = null;
    }
}
