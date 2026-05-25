<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Application Messages — English
|--------------------------------------------------------------------------
|
| Used by success responses that surface a `message_key` for the client to
| look up in its own translations bundle. We still send a translated
| `message` alongside, but the contract guarantees `message_key` is stable
| so SDKs can pin against it instead of fragile English copy.
|
*/

return [
    'auth' => [
        'reset_link_sent' => 'If an account exists for that email, a password-reset link has been sent.',
        'password_reset_success' => 'Your password has been reset successfully.',
        'email_verification_sent' => 'A verification link has been sent to your email address.',
        'email_already_verified' => 'Your email address is already verified.',
        'email_verified' => 'Your email address has been verified.',
    ],

    'data_export' => [
        'queued' => 'Your data export has been queued. You will receive an email with a download link shortly.',
        'mail' => [
            'subject' => 'Your QBazaar data export is ready',
            'greeting' => 'Hello,',
            'line_intro' => 'Your personal data export is ready for download.',
            'action' => 'Download my data',
            'line_expires' => 'This link will expire in :hours hours for your security.',
            'line_ignore' => 'If you did not request this export, please contact our support team immediately.',
        ],
    ],

    'ad_notifications' => [
        'approved' => [
            'subject' => 'Your ad is now live',
            'greeting' => 'Hello,',
            'line_intro' => 'Your ad ":title" has been approved and is now active on QBazaar.',
            'action' => 'View ad',
            'line_outro' => 'Buyers can now find and message you about this listing.',
        ],
        'rejected' => [
            'subject' => 'We need to review your ad',
            'greeting' => 'Hello,',
            'line_intro' => 'Your ad ":title" needs a few changes before it can go live.',
            'line_reasons' => 'Reasons: :reasons',
            'reasons' => [
                'banned_words' => 'It contains words our policy does not allow.',
                'phone' => 'It contains a phone number — please keep contact details in chat.',
                'external_link' => 'It contains an external link.',
            ],
            'action' => 'Edit my ad',
            'line_outro' => 'Once you update the listing, resubmit it for review.',
        ],
        'expiring_soon' => [
            'subject' => 'Your ad expires in 24 hours',
            'greeting' => 'Hello,',
            'line_intro' => 'Your ad ":title" will expire on :expires_at.',
            'action' => 'Renew now',
            'line_outro' => 'Renewing keeps your listing visible for another :days days.',
        ],
        'expired' => [
            'subject' => 'Your ad has expired',
            'greeting' => 'Hello,',
            'line_intro' => 'Your ad ":title" expired and is no longer visible in search.',
            'action' => 'Renew ad',
            'line_outro' => 'You can bring it back live in one click.',
        ],
    ],

    /*
    |------------------------------------------------------------------
    | In-app notification copy
    |------------------------------------------------------------------
    |
    | Used by every Notification's `toArray()` to build the DB-channel
    | payload (title + body). Short, declarative — these render inside the
    | notification bell's compact list, so we keep them under 80 chars where
    | possible and never include HTML.
    */
    'notifications' => [
        'ad_approved' => [
            'title' => 'Your ad is live',
            'body' => 'Your ad ":title" has been approved and is now visible to buyers.',
        ],
        'ad_rejected' => [
            'title' => 'Your ad needs changes',
            'body' => 'We couldn\'t publish ":title". Tap to see what needs fixing.',
        ],
        'ad_expiring_soon' => [
            'title' => 'Your ad expires soon',
            'body' => 'Your ad ":title" will expire in 24 hours. Renew to stay visible.',
        ],
        'ad_expired' => [
            'title' => 'Your ad has expired',
            'body' => 'Your ad ":title" has expired. Renew it in one tap to bring it back.',
        ],
        'data_export_ready' => [
            'title' => 'Your data export is ready',
            'body' => 'Tap to download your personal data export.',
        ],
        'security_alert' => [
            'title' => 'New sign-in detected',
            'body' => 'A new sign-in from :device. If this wasn\'t you, secure your account now.',
        ],
        'system_announcement' => [
            'greeting' => 'Hello,',
            'footer' => 'Thanks for being a part of QBazaar.',
        ],
    ],
];
