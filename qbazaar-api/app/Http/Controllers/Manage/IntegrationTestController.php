<?php

declare(strict_types=1);

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Http\Requests\Manage\SendIntegrationTestRequest;
use App\Models\User;
use App\Services\Settings\IntegrationTester;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class IntegrationTestController extends Controller
{
    public function email(SendIntegrationTestRequest $request, IntegrationTester $tester): RedirectResponse
    {
        /** @var User $admin */
        $admin = $request->user();
        $to = $admin->email;

        return $this->attempt(
            fn () => $tester->sendEmail($to),
            'email',
            'test_email',
            __('admin.integrations.test_email_sent', ['to' => $to]),
        );
    }

    public function sms(SendIntegrationTestRequest $request, IntegrationTester $tester): RedirectResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        if (! $admin->phone_verified || $admin->phone === '') {
            return $this->failure('sms', 'test_phone', __('admin.integrations.test_sms_no_phone'));
        }

        $to = $admin->phone;

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
        try {
            $send();
        } catch (Throwable $e) {
            Log::warning('integration_settings.test_failed', ['tab' => $tab, 'exception' => $e::class]);

            return $this->failure($tab, $errorKey, __('admin.integrations.test_failed'));
        }

        return redirect()->to($this->settingsUrl($tab))->with('status', $success);
    }

    private function failure(string $tab, string $errorKey, string $message): RedirectResponse
    {
        return redirect()->to($this->settingsUrl($tab))->withErrors([$errorKey => $message]);
    }

    private function settingsUrl(string $tab): string
    {
        return route('admin.settings.edit') . '#' . $tab;
    }
}
