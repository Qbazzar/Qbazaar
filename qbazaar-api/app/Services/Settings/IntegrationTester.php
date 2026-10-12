<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Mail\IntegrationTestMail;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Twilio\Rest\Client as TwilioClient;

/**
 * Sends one real message through the currently applied email / SMS config so
 * an admin can verify credentials. Exceptions carry no credential text.
 */
class IntegrationTester
{
    public function sendEmail(string $to): void
    {
        $mailer = config('mail.default') === 'resend' ? 'resend' : null;

        Mail::mailer($mailer)->to($to)->send(new IntegrationTestMail);
    }

    public function sendSms(string $to): void
    {
        $sid = (string) config('services.twilio.sid');
        $token = (string) config('services.twilio.token');
        $serviceSid = (string) config('services.twilio.messaging_service_sid');
        $from = (string) config('services.twilio.from');

        if ($sid === '' || $token === '' || ($serviceSid === '' && $from === '')) {
            throw new RuntimeException('SMS is not configured.');
        }

        (new TwilioClient($sid, $token))->messages->create($to, [
            ...($serviceSid !== '' ? ['messagingServiceSid' => $serviceSid] : ['from' => $from]),
            'body' => 'QBazaar test SMS.',
        ]);
    }
}
