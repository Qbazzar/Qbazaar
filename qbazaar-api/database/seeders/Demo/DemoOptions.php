<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

final readonly class DemoOptions
{
    public const string EMAIL_DOMAIN = 'demo.qbazaar.qa';

    public const string LOCAL_PASSWORD = 'qbazaar-demo';

    /**
     * @param bool $syncMedia run image conversions inline instead of queueing them
     */
    public function __construct(
        public int $users = 60,
        public int $ads = 400,
        public string $password = self::LOCAL_PASSWORD,
        public bool $syncMedia = false,
    ) {}
}
