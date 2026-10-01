<?php

declare(strict_types=1);

/*
| Read by Laravel's TrustProxies middleware on every request. Behind
| Cloudflare every connection comes from Cloudflare's edge, so without this
| each client would share a handful of IPs and every per-IP rate limit would
| trip for everyone at once. Only X-Forwarded-For and -Proto are trusted
| (bootstrap/app.php); a client still cannot spoof its address, because the
| right-most hop that is not a trusted proxy wins.
|
| TRUSTED_PROXIES (comma separated) replaces the list, e.g. when another
| proxy sits in front. Cloudflare publishes its ranges at
| https://www.cloudflare.com/ips/ — refresh the list when they change.
*/
return [
    'proxies' => env('TRUSTED_PROXIES') ?: [
        '173.245.48.0/20',
        '103.21.244.0/22',
        '103.22.200.0/22',
        '103.31.4.0/22',
        '141.101.64.0/18',
        '108.162.192.0/18',
        '190.93.240.0/20',
        '188.114.96.0/20',
        '197.234.240.0/22',
        '198.41.128.0/17',
        '162.158.0.0/15',
        '104.16.0.0/13',
        '104.24.0.0/14',
        '172.64.0.0/13',
        '131.0.72.0/22',
        '2400:cb00::/32',
        '2606:4700::/32',
        '2803:f800::/32',
        '2405:b500::/32',
        '2405:8100::/32',
        '2a06:98c0::/29',
        '2c0f:f248::/32',
    ],
];
