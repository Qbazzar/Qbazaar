<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Health\ReadinessProbe;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group Health
 */
class ReadinessController extends Controller
{
    /**
     * GET /api/v1/health/ready — dependency checks for uptime monitors.
     *
     * 200 with `ok` or `degraded`, 503 with `down` when the database, Redis
     * or the cache cannot be reached. An ops endpoint, so the body is the
     * bare report rather than the API envelope (the route skips
     * ApiResponseWrapper), and it is never cached.
     *
     * @unauthenticated
     */
    public function __invoke(ReadinessProbe $probe): JsonResponse
    {
        $report = $probe->run();

        return response()
            ->json($report->toArray(), $report->isReady() ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE)
            ->header('Cache-Control', 'no-store');
    }
}
