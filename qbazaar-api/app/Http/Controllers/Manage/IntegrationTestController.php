<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Http\Requests\Manage\SendIntegrationTestRequest;
use App\Services\Settings\IntegrationTester;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class IntegrationTestController extends Controller
{
    public function email(SendIntegrationTestRequest $request, IntegrationTester $tester): RedirectResponse
    {
        $to = $request->validated('test_email');

        return $this->attempt(
            fn () => $tester->sendEmail($to),
            'email',
            'test_email',
            __('admin.integrations.test_email_sent', ['to' => $to]),
        );
    }

    public function sms(SendIntegrationTestRequest $request, IntegrationTester $tester): RedirectResponse
    {
        $to = $request->validated('test_phone');

        return $this->attempt(
            fn () => $tester->sendSms($to),
            'sms',
            'test_phone',
            __('admin.integrations.test_sms_sent', ['to' => $to]),
        );
    }

    /**
     * Failures log the exception class only: provider messages can echo credentials.
     */
    private function attempt(callable $send, string $tab, string $errorKey, string $success): RedirectResponse
    {
        $back = redirect()->to(route('admin.settings.edit') . '#' . $tab);

        try {
            $send();
        } catch (Throwable $e) {
            Log::warning('integration_settings.test_failed', ['tab' => $tab, 'exception' => $e::class]);

            return $back->withErrors([$errorKey => __('admin.integrations.test_failed')]);
        }

        return $back->with('status', $success);
    }
}
