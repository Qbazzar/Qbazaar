<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Media library overrides
|--------------------------------------------------------------------------
| Merged over the package defaults (vendor/spatie/laravel-medialibrary/
| config/media-library.php), so only the keys we change live here.
*/

return [

    // The `media` queue (Horizon supervisor-media) keeps image work away from
    // chat and notifications even when MEDIA_QUEUE is missing from .env.
    'queue_name' => env('MEDIA_QUEUE', 'media'),

    'remote' => [
        // Conversion paths are server-generated and never rewritten, so the
        // CDN and browsers may keep a copy for a year. Originals are only
        // reached through presigned URLs that change on every issue.
        'extra_headers' => [
            'CacheControl' => 'public, max-age=31536000, immutable',
        ],
    ],

];
