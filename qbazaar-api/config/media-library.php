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
];
