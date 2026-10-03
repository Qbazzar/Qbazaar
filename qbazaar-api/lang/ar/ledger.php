<?php

declare(strict_types=1);

/*
| Wallet statement lines, keyed by LedgerTransactionType::descriptionKey().
*/

return [
    'descriptions' => [
        'order_payment_received' => 'استلام قيمة الطلب وحجزها حتى التسليم',
        'escrow_released' => 'تحويل قيمة البيع بعد التسليم',
        'commission_charged' => 'عمولة QBazaar على بيع نقدي',
        'commission_settled' => 'تسديد العمولة',
        'withdrawal_requested' => 'طلب سحب',
        'withdrawal_paid' => 'تحويل السحب إلى حسابك البنكي',
        'withdrawal_rejected' => 'رفض السحب وإرجاع المبلغ إلى محفظتك',
        'refund' => 'استرداد قيمة طلب',
        'promotion_purchased' => 'ترويج إعلان',
        'adjustment' => 'تعديل على الرصيد من QBazaar',
        'reversal' => 'تصحيح قيد سابق',
    ],
];
