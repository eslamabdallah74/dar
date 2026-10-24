<?php

namespace App\Enums;

/**
 * Queue names used throughout the Dar application.
 * Using an enum provides type-safety, autocompletion,
 * and eliminates magic strings in dispatchers and workers.
 */
enum QueueName: string
{
    case Default = 'default';
    case High = 'high';
    case Messaging = 'messaging';
    case Notifications = 'notifications';
    case Deposits = 'security_deposits';
    case Invoicing = 'rent_charges';
    case Reports = 'reports';
    case Cleanup = 'cleanup';

    // Backwards-compatible constants for legacy/uppercase references
    public const DEFAULT = 'default';

    public const HIGH = 'high';

    public const MESSAGING = 'messaging';

    public const NOTIFICATIONS = 'notifications';

    public const DEPOSITS = 'security_deposits';

    public const INVOICING = 'rent_charges';

    public const REPORTS = 'reports';

    public const CLEANUP = 'cleanup';

    /**
     * Get all active queue names ordered by priority for workers:
     * php artisan queue:work --queue=...
     */
    public static function priorityList(): array
    {
        return [
            self::High->value,
            self::Messaging->value,
            self::Notifications->value,
            self::Deposits->value,
            self::Invoicing->value,
            self::Default->value,
            self::Reports->value,
            self::Cleanup->value,
        ];
    }
}
