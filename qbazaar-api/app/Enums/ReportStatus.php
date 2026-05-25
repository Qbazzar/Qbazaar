<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Lifecycle of a moderation report.
 *
 *  - PENDING   → fresh, awaiting admin review (default).
 *  - REVIEWED  → an admin has looked but taken no action yet (kept for audit).
 *  - DISMISSED → admin decided the report does not warrant action.
 *  - ACTIONED  → admin acted (suspended user / hid ad / deleted message …).
 *
 * Only PENDING is mutable from the user's side (they can't transition, but
 * the duplicate-report guard relies on PENDING vs terminal). Admin
 * transitions are gated by the Sprint 11 Filament resource.
 */
enum ReportStatus: string
{
    case PENDING = 'pending';
    case REVIEWED = 'reviewed';
    case DISMISSED = 'dismissed';
    case ACTIONED = 'actioned';
}
