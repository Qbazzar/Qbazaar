<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Application-level exception carrying a stable ErrorCode.
 *
 * Throw this from services / actions whenever you hit a business-rule
 * violation that maps to one of the codes in App\Exceptions\ErrorCode.
 * The global render handler in bootstrap/app.php turns it into the
 * standard error envelope (success:false, code, message, …).
 *
 * Plain RuntimeException base — no Symfony HTTP coupling — so domain layers
 * stay framework-agnostic; the HTTP status is derived from
 * $errorCode->httpStatus().
 *
 * Note: we can't shadow the parent `$code` property as readonly because
 * \Exception declares it non-readonly. We expose the strongly-typed
 * ErrorCode via a separate property + accessor.
 */
class DomainException extends RuntimeException
{
    /**
     * @param array<string, mixed>|null $details
     */
    public function __construct(
        public readonly ErrorCode $errorCode,
        ?string $message = null,
        public readonly ?array $details = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            $message ?? __($errorCode->messageKey()),
            $errorCode->httpStatus(),
            $previous,
        );
    }
}
