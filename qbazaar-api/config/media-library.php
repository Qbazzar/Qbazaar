<?php

declare(strict_types=1);

use App\Enums\QueueName;
use App\Jobs\Media\PerformConversionsJob;
use Spatie\MediaLibrary\ResponsiveImages\Jobs\GenerateResponsiveImagesJob;

/*
| Only the keys QBazaar changes; MediaLibrary merges its own defaults for
| everything else (see vendor/spatie/laravel-medialibrary/config).
*/
return [
    'queue_name' => env('MEDIA_QUEUE') ?: QueueName::MEDIA->value,

    'jobs' => [
        'perform_conversions' => PerformConversionsJob::class,
        'generate_responsive_images' => GenerateResponsiveImagesJob::class,
    ],

    'remote' => [
        // Conversion paths are server-generated and never rewritten, so the
        // CDN and browsers may keep a copy for a year. Originals are only
        // reached through presigned URLs that change on every issue.
        'extra_headers' => [
            'CacheControl' => 'public, max-age=31536000, immutable',
        ],
    ],
];
