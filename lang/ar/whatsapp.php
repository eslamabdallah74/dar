<?php

return [
    'templates' => [
        'rent_due_reminder' => 'تذكير موعد استحقاق الإيجار',
        'rent_due_reminder_trigger' => 'تلقائياً قبل موعد الاستحقاق',
        'rent_due_reminder_sample' => 'مرحباً {اسم_المستأجر}، نود تذكيركم بأن القسط الإيجاري للوحدة {الوحدة} بقيمة {المبلغ} مستحق في {تاريخ_الاستحقاق}. شكراً لتعاونكم.',
        'payment_receipt_instant' => 'سند قبض إلكتروني فوري',
        'payment_receipt_instant_trigger' => 'فور تسجيل دفعة جديدة في النظام',
        'payment_receipt_instant_sample' => 'عزيزي {اسم_المستأجر}، تم استلام مبلغ {المبلغ} بنجاح، سند قبض رقم {رقم_السند}. يمكنكم الاطلاع على الإيصال عبر الرابط التالي: {رابط_السند}',
        'overdue_notice' => 'إشعار متأخرات سداد',
        'overdue_notice_trigger' => 'بعد مرور أيام على موعد الاستحقاق',
        'overdue_notice_sample' => 'عزيزي {اسم_المستأجر}، نلفت عنايتكم لتأخر سداد قسط الإيجار للوحدة {الوحدة} المستحق بتاريخ {تاريخ_الاستحقاق}. يرجى المبادرة بالسداد تجنباً للإجراءات النظامية.',
        'contract_renewal' => 'تنبيه قرب انتهاء العقد',
        'contract_renewal_trigger' => 'قبل انتهاء العقد',
        'contract_renewal_sample' => 'مرحباً {اسم_المستأجر}، نود إشعاركم بأن عقد إيجاركم للوحدة {الوحدة} ينتهي في {تاريخ_الانتهاء}. يرجى التواصل معنا في حال رغبتكم بالتجديد.',
    ],
    'statuses' => [
        'delivered' => 'تم التسليم والقراءة',
        'sent' => 'تم الإرسال',
        'pending' => 'مجدول',
        'failed' => 'فشل الإرسال',
    ],
    'connection' => [
        'connected' => 'متصل ونشط',
        'disconnected' => 'بانتظار ضبط مفاتيح الربط (WHATSAPP_API_TOKEN)',
        'provider' => 'WhatsApp Cloud API الرسمية (Meta)',
        'unconfigured' => 'غير مهيأ',
    ],
    'messages' => [
        'test_sent_success' => 'تم إرسال رسالة WhatsApp بنجاح عبر بوابة Meta Cloud API',
        'test_sent_failed' => 'فشل الإرسال: :error',
        'test_sent_processed' => 'تمت معالجة طلب الإرسال بنجاح',
    ],
];
