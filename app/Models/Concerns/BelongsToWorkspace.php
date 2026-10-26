<?php

namespace App\Models\Concerns;

use App\Models\Scopes\WorkspaceScope;
use App\Models\Workspace;
use App\Support\CurrentWorkspace;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToWorkspace
{
    public static function bootBelongsToWorkspace(): void
    {
        static::addGlobalScope(new WorkspaceScope);

        static::creating(function ($model) {
            $currentWorkspace = app(CurrentWorkspace::class);
            if ($currentWorkspace->check()) {
                $user = auth()->user();
                if (! $user || ! $user->isAdmin()) {
                    $model->workspace_id = $currentWorkspace->id();
                }
            }
        });

        static::updating(function ($model) {
            if ($model->isDirty('workspace_id')) {
                $user = auth()->user();
                if (! $user || ! $user->isAdmin()) {
                    throw new AuthorizationException('لا يمكن تعديل مساحة العمل للسجل بعد إنشائه.');
                }
            }
        });
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
