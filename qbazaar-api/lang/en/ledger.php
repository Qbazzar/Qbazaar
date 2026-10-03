<?php

declare(strict_types=1);

/*
| Wallet statement lines, keyed by LedgerTransactionType::descriptionKey().
*/

return [
    'descriptions' => [
        'order_payment_received' => 'Order payment received and held until handover',
        'escrow_released' => 'Sale proceeds released after handover',
        'commission_charged' => 'QBazaar commission on a cash sale',
        'commission_settled' => 'Commission payment',
        'withdrawal_requested' => 'Withdrawal requested',
        'withdrawal_paid' => 'Withdrawal paid to your bank account',
        'withdrawal_rejected' => 'Withdrawal rejected and returned to your wallet',
        'refund' => 'Order refund',
        'promotion_purchased' => 'Ad promotion',
        'adjustment' => 'Balance adjustment by QBazaar',
        'reversal' => 'Correction of an earlier entry',
        'commission_refunded' => 'Commission returned on a sale cancelled after a dispute',
    ],
];
