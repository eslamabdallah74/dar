<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Workspace Limits
    |--------------------------------------------------------------------------
    |
    | Initial default limits assigned to any newly registered workspace.
    | A null value indicates unlimited.
    |
    */
    'default_limits' => [
        'parent_unit_limit' => (int) env('DEFAULT_WORKSPACE_PARENT_UNIT_LIMIT', 1),
        'active_rental_limit' => (int) env('DEFAULT_WORKSPACE_ACTIVE_RENTAL_LIMIT', 1),
    ],
];
