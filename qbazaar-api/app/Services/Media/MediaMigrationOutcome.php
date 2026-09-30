<?php

declare(strict_types=1);

namespace App\Services\Media;

enum MediaMigrationOutcome
{
    case Migrated;
    case AlreadyInPlace;
    case SourceMissing;
}
