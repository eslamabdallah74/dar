<?php

return [
    'lease_expiry_subject' => 'Lease Expiration Reminder - Unit :unit',
    'lease_expiry_body' => "Dear :name,\n\nWe would like to remind you that your lease for unit (:unit) will expire in :days days on :date.\n\nIf you wish to renew your lease, please contact property management to complete the necessary arrangements.\n\nBest regards.",
    'overdue_notice_subject' => 'Overdue Rent Notice - Unit :unit',
    'overdue_notice_body' => "Dear :name,\n\nThis is a notification regarding an overdue rent balance for unit (:unit) of :amount :currency, which was due on :date.\n\nPlease arrange payment promptly to avoid any penalties or further administrative action.\n\nBest regards.",
    'maintenance_created_subject' => 'New Maintenance Request #:id - :property',
    'maintenance_created_body' => "A new maintenance request has been submitted for unit (:unit): :title.\n\nDescription: :description",
];
