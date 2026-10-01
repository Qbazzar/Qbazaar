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
];
