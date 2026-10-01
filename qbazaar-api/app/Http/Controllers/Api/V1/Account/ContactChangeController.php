<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Account;

use App\Actions\Account\ConfirmEmailChangeAction;
use App\Actions\Account\ConfirmPhoneChangeAction;
use App\Actions\Account\RequestEmailChangeAction;
use App\Actions\Account\RequestPhoneChangeAction;
use App\Exceptions\DomainException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Account\ChangeEmailRequest;
use App\Http\Requests\Api\V1\Account\ChangePhoneRequest;
use App\Http\Requests\Api\V1\Account\ConfirmPhoneChangeRequest;
use App\Http\Resources\Api\V1\Account\AccountProfileResource;
use App\Models\User;
use App\Services\Account\ReauthCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Changing the email or phone. Both start with a step-up code emailed to
 * the current address (sign-in is passwordless, so there is no password to
 * ask for), then prove the new contact: a link for email, an OTP for phone.
 *
 * @group Account
 */
class ContactChangeController extends Controller
{
    /**
     * Email a step-up code to the current address.
     *
     * @authenticated
     */
    public function sendReauthCode(Request $request, ReauthCodeService $reauth): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->authorize('update', $user);

        return response()->json([
            'expires_in' => $reauth->issue($user),
            'can_resend_in' => (int) config('qbazaar.account.reauth_code_cooldown_seconds'),
        ], Response::HTTP_ACCEPTED);
    }

    /**
     * Ask to move the account to a new email; a confirmation link goes to it.
     *
     * @authenticated
     */
    public function requestEmailChange(ChangeEmailRequest $request, RequestEmailChangeAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->authorize('update', $user);

        $email = (string) $request->validated('email');
        $action->execute($user, $email, (string) $request->validated('reauth_code'));

        return response()->json([
            'pending_email' => $email,
            'expires_in' => (int) config('qbazaar.account.email_change_link_ttl_minutes') * 60,
        ], Response::HTTP_ACCEPTED);
    }

    /**
     * Opened from the link in the email. Browsers are redirected to the web
     * app's result page; JSON clients get the updated profile or an error.
     *
     * @unauthenticated
     */
    public function confirmEmailChange(Request $request, ConfirmEmailChangeAction $action): Response
    {
        $wantsHtml = ! $request->expectsJson();

        try {
            $user = $action->execute(
                (string) $request->query('user'),
                (string) $request->query('email'),
                (string) $request->query('from'),
            );
        } catch (DomainException $exception) {
            if ($wantsHtml) {
                return $this->redirectToResult($exception->errorCode->value);
            }

            throw $exception;
        }

        if ($wantsHtml) {
            return $this->redirectToResult('success');
        }

        return response()->json((new AccountProfileResource($user))->resolve($request));
    }

    /**
     * Ask to move the account to a new phone; an OTP is texted to it.
     *
     * @authenticated
     */
    public function requestPhoneChange(ChangePhoneRequest $request, RequestPhoneChangeAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->authorize('update', $user);

        $result = $action->execute($user, (string) $request->validated('phone'), (string) $request->validated('reauth_code'));

        return response()->json([
            'sent_to' => $result->recipient,
            'expires_in' => $result->expiresIn,
            'can_resend_in' => $result->canResendIn,
        ], Response::HTTP_ACCEPTED);
    }

    /**
     * Confirm the new phone with the texted OTP.
     *
     * @authenticated
     */
    public function confirmPhoneChange(ConfirmPhoneChangeRequest $request, ConfirmPhoneChangeAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->authorize('update', $user);

        $updated = $action->execute($user, (string) $request->validated('code'));

        return response()->json((new AccountProfileResource($updated))->resolve($request));
    }

    private function redirectToResult(string $status): Response
    {
        $webBase = rtrim((string) config('qbazaar.web_url'), '/');

        return redirect()->away($webBase . '/account/email-change/result?status=' . urlencode($status));
    }
}
