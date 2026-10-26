<?php

namespace App\Models\Scopes;

use App\Support\CurrentWorkspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * @implements Scope<Model>
 */
class WorkspaceScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $currentWorkspace = app(CurrentWorkspace::class);

        if ($currentWorkspace->check()) {
            $builder->where($model->qualifyColumn('workspace_id'), $currentWorkspace->id());
        }
    }
}
