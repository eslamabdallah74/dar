<?php

namespace App\Enums;

/**
 * Queue names used throughout the Dar application.
 * Using an enum provides type-safety, autocompletion,
 * and eliminates magic strings in dispatchers and workers.
 */
enum QueueName: string
{
    case Messaging = 'messaging';
    case Notifications = 'notifications';
    case Deposits = 'security_deposits';
    case Invoicing = 'rent_charges';
    case Reports = 'reports';
    case Cleanup = 'cleanup';

}
