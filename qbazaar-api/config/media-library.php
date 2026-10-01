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

    'remote' => [
        // Conversion paths are server-generated and never rewritten, so the
        // CDN and browsers may keep a copy for a year. Originals are only
        // reached through presigned URLs that change on every issue.
        'extra_headers' => [
            'CacheControl' => 'public, max-age=31536000, immutable',
        ],
    ],

];
