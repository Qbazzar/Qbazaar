<?php

declare(strict_types=1);

/*
| Chat bubbles stored as `message_key` + `params` (see App\Enums\ChatMessageKey).
*/

return [
    'offer' => [
        'made' => 'Offer: :amount :currency',
        'countered' => 'Counter-offer: :amount :currency',
        'accepted' => 'Offer accepted',
        'rejected' => 'Offer rejected',
        'withdrawn' => 'Offer withdrawn',
    ],
    'image' => 'Photo',
];
