<?php

declare(strict_types=1);

namespace App\Data\Moderation;

use App\Enums\SanctionReason;

/**
 * The reason staff give for a sanction, a bulk rejection or a cancelled
 * offer: one preset plus an optional internal note.
 */
final readonly class SanctionReasonInput
{
    public function __construct(
        public SanctionReason $reason,
        public ?string $note = null,
    ) {}

    /** One line for staff-facing records (report notes, audit), in the admin language. */
    public function summary(): string
    {
        $label = $this->reason->adminLabel();

        return $this->note === null || $this->note === '' ? $label : $label . ' — ' . $this->note;
    }

    /**
     * @return array{reason: string, note: string|null}
     */
    public function toAudit(): array
    {
        return ['reason' => $this->reason->value, 'note' => $this->note];
    }
}
