<?php

declare(strict_types=1);

/*
 * Business accounts: what the applicant reads about their application and
 * the verified badge (notifications) and the application API messages.
 */
return [
    'notifications' => [
        'action' => 'عرض حالة التوثيق',
        'application_approved' => [
            'title' => 'تم قبول طلب حساب الأعمال',
            'body' => 'أصبح حسابك حساب أعمال موثّقاً. :reason',
        ],
        'application_rejected' => [
            'title' => 'تم رفض طلب حساب الأعمال',
            'body' => 'السبب: :reason',
        ],
        'application_needs_info' => [
            'title' => 'نحتاج معلومات إضافية لطلبك',
            'body' => 'يرجى تحديث طلب حساب الأعمال: :reason',
        ],
        'verification_granted' => [
            'title' => 'تم توثيق حسابك التجاري',
            'body' => 'تظهر علامة التوثيق الآن بجانب اسم نشاطك التجاري.',
        ],
        'verification_revoked' => [
            'title' => 'تم إلغاء توثيق حسابك التجاري',
            'body' => 'السبب: :reason',
        ],
    ],

    'api' => [
        'documents_required' => 'أرفق مستنداً واحداً على الأقل (السجل التجاري أو الرخصة).',
        'documents_max' => 'يمكنك إرفاق :max مستندات كحد أقصى.',
    ],
];
