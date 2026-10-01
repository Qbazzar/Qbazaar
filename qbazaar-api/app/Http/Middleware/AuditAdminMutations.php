<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Admin\AdminAuditLogger;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Writes one audit entry for every successful state-changing request in the
 * admin panel, so no mutation goes unrecorded even when its controller has
 * no dedicated logging. Actions that record a richer entry themselves
 * (through AdminAuditLogger) suppress this generic one.
 */
class AuditAdminMutations
{
    private const REDACTED_INPUT = ['_token', '_method', 'password', 'password_confirmation', 'current_password'];

    private const MAX_VALUE_LENGTH = 500;

    public function __construct(private readonly AdminAuditLogger $audit) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->audit->forgetRecorded();

        $response = $next($request);

        $admin = $request->user();

        if ($admin instanceof User && $this->shouldRecord($request, $response)) {
            $this->audit->record(
                $admin,
                (string) $request->route()?->getName(),
                $this->subjectOf($request),
                [
                    'input' => $this->summarise($request->except(self::REDACTED_INPUT)),
                    'ip' => $request->ip(),
                ],
            );
        }

        return $response;
    }

    private function shouldRecord(Request $request, Response $response): bool
    {
        if ($request->isMethodSafe() || $this->audit->hasRecorded()) {
            return false;
        }

        if ($response->getStatusCode() >= 400) {
            return false;
        }

        // Validation failures and refusals come back as redirects that flash
        // an error during this very request.
        $flashed = $request->hasSession() ? (array) $request->session()->get('_flash.new', []) : [];

        return array_intersect($flashed, ['errors', 'error']) === [];
    }

    private function subjectOf(Request $request): ?Model
    {
        foreach ($request->route()?->parameters() ?? [] as $parameter) {
            if ($parameter instanceof Model) {
                return $parameter;
            }
        }

        return null;
    }

    /**
     * @param array<array-key, mixed> $input
     * @return array<array-key, mixed>
     */
    private function summarise(array $input): array
    {
        return array_map(fn (mixed $value): mixed => match (true) {
            is_array($value) => $this->summarise($value),
            $value instanceof UploadedFile => $value->getClientOriginalName(),
            is_string($value) => Str::limit($value, self::MAX_VALUE_LENGTH),
            default => $value,
        }, $input);
    }
}
