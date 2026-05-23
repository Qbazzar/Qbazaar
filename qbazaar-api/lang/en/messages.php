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
];
