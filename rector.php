<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\Cast\RecastingRemovalRector;

/*
 * Rector runs in this project as a dead-code detector (unused variable assignments,
 * unused private method parameters, dead branches, redundant docblock tags, ...).
 * CI runs it with --dry-run, so any proposed change fails the quality gate.
 */
return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/app',
        __DIR__.'/bootstrap/app.php',
        __DIR__.'/config',
        __DIR__.'/database',
        __DIR__.'/routes',
        __DIR__.'/tests',
    ])
    ->withCache(__DIR__.'/storage/framework/cache/rector')
    ->withPreparedSets(deadCode: true)
    ->withSkip([
        // Cast removal is reported by PHPStan (cast.useless) instead, where each case is reviewed:
        // removing a cast from a money value based on an inferred type can change runtime output.
        RecastingRemovalRector::class,
    ]);
