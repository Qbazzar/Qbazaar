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
    'purchase_request' => [
        'created' => 'Buy request: :quantity pcs for :amount :currency',
        'updated' => 'Buy request updated: :quantity pcs for :amount :currency',
        'accepted' => 'Buy request accepted',
        'rejected' => 'Buy request declined',
        'cancelled' => 'Buy request cancelled',
        'paid' => 'Purchase completed',
    ],
];
