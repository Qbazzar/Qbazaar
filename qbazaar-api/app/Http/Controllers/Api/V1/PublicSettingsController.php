<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Settings\IntegrationSettings;
use Illuminate\Http\JsonResponse;

/**
 * Read-only public values (contact details, social links, GA4 id, Turnstile
 * site key) for the web footer and contact page. Credentials never appear
 * here: the payload is built from fields flagged public only.
 */
class PublicSettingsController extends Controller
{
    public function __invoke(IntegrationSettings $settings): JsonResponse
    {
        return response()
            ->json($settings->publicPayload())
            ->header('Cache-Control', 'public, max-age=300');
    }
}
