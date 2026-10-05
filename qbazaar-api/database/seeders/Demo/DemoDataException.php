<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use RuntimeException;

/**
 * A demo run that refused to start or ended in an inconsistent state, with
 * a message meant for the person running the command.
 */
final class DemoDataException extends RuntimeException {}
