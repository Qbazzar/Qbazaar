<?php

declare(strict_types=1);

namespace App\Data\Ledger;

use App\Enums\LedgerReferenceType;
use App\Enums\LedgerTransactionType;

/**
 * The business record a posting belongs to, e.g. an order or a settlement.
 */
final readonly class LedgerReference
{
    public function __construct(
        public LedgerReferenceType $type,
        public string $id,
    ) {}

    public static function order(string $orderId): self
    {
        return new self(LedgerReferenceType::ORDER, $orderId);
    }

    public static function settlement(string $settlementId): self
    {
        return new self(LedgerReferenceType::SETTLEMENT, $settlementId);
    }

    public static function withdrawal(string $withdrawalId): self
    {
        return new self(LedgerReferenceType::WITHDRAWAL, $withdrawalId);
    }

    /**
     * The idempotency key of a flow on this record. Each record runs each
     * flow at most once, so a retried request or job finds the first posting.
     */
    public function keyFor(LedgerTransactionType $type): string
    {
        return "{$this->type->value}:{$this->id}:{$type->value}";
    }

    /**
     * The key shared by the flows that close this record one way or the
     * other (an escrow is released or refunded, a withdrawal is paid or
     * rejected). The unique key lets the database accept only the first.
     */
    public function outcomeKey(): string
    {
        return "{$this->type->value}:{$this->id}:outcome";
    }
}
