<?php

declare(strict_types=1);

/*
| Chat bubbles stored as `message_key` + `params` (see App\Enums\ChatMessageKey).
*/

return [
    'offer' => [
        'made' => 'عرض: :amount :currency',
        'countered' => 'عرض مقابل: :amount :currency',
        'accepted' => 'تم قبول العرض',
        'rejected' => 'تم رفض العرض',
        'withdrawn' => 'تم سحب العرض',
    ],
    'image' => 'صورة',
    'purchase_request' => [
        'created' => 'طلب شراء: :quantity قطعة بمبلغ :amount :currency',
        'updated' => 'تم تعديل طلب الشراء: :quantity قطعة بمبلغ :amount :currency',
        'accepted' => 'تم قبول طلب الشراء',
        'rejected' => 'تم رفض طلب الشراء',
        'cancelled' => 'تم إلغاء طلب الشراء',
        'paid' => 'تمت عملية الشراء',
    ],
];
