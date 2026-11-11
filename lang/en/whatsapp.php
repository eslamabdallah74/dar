<?php

return [
    'templates' => [
        'rent_due_reminder' => 'Rent Due Reminder',
        'rent_due_reminder_trigger' => 'Automatically before the due date',
        'rent_due_reminder_sample' => 'Hello {renter_name}, this is a reminder that the rent installment for unit {unit} in the amount of {amount} is due on {due_date}. Thank you.',
        'payment_receipt_instant' => 'Instant Digital Payment Receipt',
        'payment_receipt_instant_trigger' => 'Immediately upon recording a new payment',
        'payment_receipt_instant_sample' => 'Dear {renter_name}, payment of {amount} has been received successfully. Receipt #{receipt_no}. View your receipt at: {link}',
        'overdue_notice' => 'Overdue Rent Notice',
        'overdue_notice_trigger' => 'Days after the due date has passed',
        'overdue_notice_sample' => 'Dear {renter_name}, your rent installment for unit {unit} due on {due_date} is overdue. Please settle the payment promptly to avoid formal action.',
        'contract_renewal' => 'Lease Renewal Alert',
        'contract_renewal_trigger' => 'Before lease expiration',
        'contract_renewal_sample' => 'Hello {renter_name}, your lease for unit {unit} will expire on {end_date}. Please contact us if you wish to renew.',
    ],
    'statuses' => [
        'delivered' => 'Delivered & Read',
        'sent' => 'Sent',
        'pending' => 'Scheduled',
        'failed' => 'Failed',
    ],
    'connection' => [
        'connected' => 'Connected & Active',
        'disconnected' => 'Awaiting API credentials (WHATSAPP_API_TOKEN)',
        'provider' => 'Official WhatsApp Cloud API (Meta)',
        'unconfigured' => 'Not Configured',
    ],
    'messages' => [
        'test_sent_success' => 'WhatsApp message sent successfully via Meta Cloud API',
        'test_sent_failed' => 'Delivery failed: :error',
        'test_sent_processed' => 'Message delivery request processed successfully',
    ],
];
