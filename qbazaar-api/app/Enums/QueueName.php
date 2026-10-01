<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The queues Horizon runs, one supervisor each, so slow work on one queue
 * (image conversions, sweeps) can never hold back another (chat broadcasts).
 */
enum QueueName: string
{
    /** WebSocket broadcasts and chat pushes: seconds matter. */
    case REALTIME = 'realtime';

    /** Mail, push, SMS and inbox notifications, plus their audience fan-out. */
    case NOTIFICATIONS = 'notifications';

    case DEFAULT = 'default';

    /** Scout index writes, retried while Meilisearch restarts. */
    case SEARCH = 'search';

    /** CPU-bound image work. */
    case MEDIA = 'media';

    /** Sweeps, exports and account deletion: long-running, never urgent. */
    case LOW = 'low';
}
