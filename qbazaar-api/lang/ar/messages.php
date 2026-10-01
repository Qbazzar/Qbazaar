<?php

declare(strict_types=1);

return [
    'auth' => [
        'reset_link_sent' => 'إذا كان البريد الإلكتروني مسجلًا لدينا، فقد تم إرسال رابط إعادة تعيين كلمة المرور إليه.',
        'password_reset_success' => 'تم إعادة تعيين كلمة المرور بنجاح.',
        'email_verification_sent' => 'تم إرسال رابط التحقق إلى بريدك الإلكتروني.',
        'email_already_verified' => 'بريدك الإلكتروني مُتحقق منه بالفعل.',
        'email_verified' => 'تم التحقق من بريدك الإلكتروني بنجاح.',
    ],

    'account_change' => [
        'reauth' => [
            'subject' => 'رمز التأكيد من QBazaar',
            'line_code' => 'استخدم هذا الرمز لتأكيد التغيير على حسابك: :code',
            'line_expires' => 'تنتهي صلاحية الرمز خلال :minutes دقيقة.',
            'line_ignore' => 'إذا لم تطلب ذلك، فقد يكون أحدهم يستخدم حسابك. سجّل الخروج من الأجهزة الأخرى وتواصل مع الدعم.',
        ],
        'confirm_email' => [
            'subject' => 'أكّد بريدك الإلكتروني الجديد',
            'line_intro' => 'افتح الرابط أدناه لاستخدام هذا البريد في حسابك على QBazaar.',
            'action' => 'تأكيد البريد',
            'line_expires' => 'تنتهي صلاحية الرابط خلال :minutes دقيقة ويعمل مرة واحدة.',
            'line_ignore' => 'إذا لم تطلب ذلك، تجاهل هذه الرسالة ولن يتغير شيء.',
        ],
        'email_changed' => [
            'subject' => 'تم تغيير بريدك الإلكتروني على QBazaar',
            'line_intro' => 'تم تغيير البريد الإلكتروني لحسابك على QBazaar إلى :email.',
            'line_not_you' => 'إذا لم تقم بهذا التغيير، تواصل مع فريق الدعم فوراً.',
        ],
    ],

    'data_export' => [
        'queued' => 'تم استلام طلب تصدير بياناتك. سيصلك رابط التنزيل عبر البريد الإلكتروني قريبًا.',
        'mail' => [
            'subject' => 'تصدير بيانات QBazaar الخاصة بك جاهز',
            'greeting' => 'مرحبًا،',
            'line_intro' => 'أصبح ملف تصدير بياناتك جاهزًا للتنزيل.',
            'action' => 'تنزيل البيانات',
            'line_expires' => 'يعمل هذا الرابط مرة واحدة وتنتهي صلاحيته خلال :hours ساعة.',
            'line_ignore' => 'إذا لم تطلب هذا التصدير، يُرجى التواصل مع فريق الدعم فورًا.',
        ],
    ],

    'support_notifications' => [
        'reply' => [
            'subject' => 'رد جديد على تذكرة الدعم',
            'greeting' => 'مرحباً،',
            'line_intro' => 'ردّ فريق الدعم على تذكرتك ":subject":',
            'action' => 'عرض التذكرة',
        ],
    ],

    'ad_notifications' => [
        'approved' => [
            'subject' => 'إعلانك أصبح نشطاً',
            'greeting' => 'مرحبًا،',
            'line_intro' => 'تمت الموافقة على إعلانك ":title" وأصبح نشطًا الآن على QBazaar.',
            'action' => 'عرض الإعلان',
            'line_outro' => 'بإمكان المشترين الآن العثور على إعلانك ومراسلتك.',
        ],
        'rejected' => [
            'subject' => 'نحتاج مراجعة إعلانك',
            'greeting' => 'مرحبًا،',
            'line_intro' => 'يحتاج إعلانك ":title" إلى بعض التعديلات قبل نشره.',
            'line_reasons' => 'الأسباب: :reasons',
            'reasons' => [
                'banned_words' => 'يحتوي على كلمات لا تسمح بها سياستنا.',
                'phone' => 'يحتوي على رقم هاتف — يُرجى الإبقاء على وسائل التواصل ضمن المحادثة.',
                'external_link' => 'يحتوي على رابط خارجي.',
            ],
            'action' => 'تعديل الإعلان',
            'line_outro' => 'بعد التعديل، يمكنك إعادة تقديمه للمراجعة.',
        ],
        'expiring_soon' => [
            'subject' => 'إعلانك ينتهي قريبًا',
            'greeting' => 'مرحبًا،',
            'line_intro' => 'سينتهي إعلانك ":title" في :expires_at.',
            'action' => 'تجديد الإعلان',
            'line_outro' => 'التجديد يبقي إعلانك ظاهرًا لمدة :days يومًا إضافية.',
        ],
        'expired' => [
            'subject' => 'انتهت صلاحية إعلانك',
            'greeting' => 'مرحبًا،',
            'line_intro' => 'انتهت صلاحية إعلانك ":title" ولم يعد ظاهرًا في نتائج البحث.',
            'action' => 'تجديد الإعلان',
            'line_outro' => 'يمكنك إعادة تنشيطه بنقرة واحدة.',
        ],
    ],

    'notifications' => [
        'report_actioned' => [
            'title' => 'شكراً على بلاغك',
            'body' => 'راجعنا بلاغك واتخذنا الإجراء المناسب.',
        ],
        'report_dismissed' => [
            'title' => 'شكراً على بلاغك',
            'body' => 'راجعنا بلاغك ولم نجد فيه مخالفة لقواعدنا.',
        ],
        'ad_approved' => [
            'title' => 'إعلانك أصبح نشطاً',
            'body' => 'تمت الموافقة على إعلانك ":title" وأصبح ظاهرًا للمشترين الآن.',
        ],
        'ad_rejected' => [
            'title' => 'إعلانك يحتاج إلى تعديلات',
            'body' => 'لم نتمكن من نشر ":title". اضغط للاطلاع على ما يلزم تعديله.',
        ],
        'ad_expiring_soon' => [
            'title' => 'إعلانك ينتهي قريبًا',
            'body' => 'إعلانك ":title" سينتهي في :expires_at. جدّده ليبقى ظاهرًا.',
        ],
        'ad_expired' => [
            'title' => 'انتهت صلاحية إعلانك',
            'body' => 'انتهت صلاحية إعلانك ":title". جدّده بنقرة لإعادته للظهور.',
        ],
        'saved_search_match' => [
            'title' => 'إعلان جديد يطابق بحثك',
            'body' => 'إعلان جديد ":title" يطابق بحثك المحفوظ ":search".',
        ],
        'data_export_ready' => [
            'title' => 'تصدير بياناتك جاهز',
            'body' => 'اضغط لتنزيل ملف تصدير بياناتك الشخصية.',
        ],
        'support_reply' => [
            'title' => 'رد فريق الدعم على تذكرتك',
            'body' => 'ردّ فريق الدعم على ":subject".',
        ],
        'security_alert' => [
            'title' => 'تسجيل دخول جديد',
            'body' => 'تم تسجيل دخول جديد من :device. إذا لم يكن أنت، قم بتأمين حسابك.',
        ],
        'message_new' => [
            'title' => 'رسالة جديدة من :name',
        ],
        'offer_created' => [
            'title' => 'عرض جديد',
            'body' => 'وصلك عرض بقيمة :amount :currency على ":title".',
        ],
        'offer_countered' => [
            'title' => 'عرض مقابل',
            'body' => 'وصلك عرض مقابل بقيمة :amount :currency على ":title".',
        ],
        'offer_accepted' => [
            'title' => 'تم قبول العرض',
            'body' => 'تم قبول عرضك بقيمة :amount :currency على ":title".',
        ],
        'offer_rejected' => [
            'title' => 'تم رفض العرض',
            'body' => 'تم رفض عرضك بقيمة :amount :currency على ":title".',
        ],
        'offer_withdrawn' => [
            'title' => 'تم سحب العرض',
            'body' => 'تم سحب العرض بقيمة :amount :currency على ":title".',
        ],
        'offer_expired' => [
            'title' => 'انتهت صلاحية العرض',
            'body' => 'انتهت صلاحية العرض بقيمة :amount :currency على ":title".',
        ],
        'system_announcement' => [
            'greeting' => 'مرحباً،',
            'footer' => 'شكراً لكونك جزءاً من كيوبازار.',
        ],
        'ad_price_changed' => [
            'title' => 'انخفض سعر إعلان في مفضلتك',
            'body' => 'صار سعر ":title" :price :currency (كان :previous).',
        ],
        'ad_new_from_followed' => [
            'title' => 'إعلان جديد من :name',
            'body' => 'نشر :name إعلاناً جديداً ":title".',
        ],
    ],
];
