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

    'account_change' => [
        'reauth' => [
            'subject' => 'Your QBazaar confirmation code',
            'line_code' => 'Use this code to confirm the change to your account: :code',
            'line_expires' => 'The code expires in :minutes minutes.',
            'line_ignore' => 'If you did not ask for this, someone may be using your account. Sign out of other devices and contact support.',
        ],
        'confirm_email' => [
            'subject' => 'Confirm your new email address',
            'line_intro' => 'Open the link below to use this address for your QBazaar account.',
            'action' => 'Confirm email',
            'line_expires' => 'The link expires in :minutes minutes and works once.',
            'line_ignore' => 'If you did not ask for this, ignore this email; nothing will change.',
        ],
        'email_changed' => [
            'subject' => 'Your QBazaar email was changed',
            'line_intro' => 'The email address on your QBazaar account was changed to :email.',
            'line_not_you' => 'If you did not make this change, contact our support team right away.',
        ],
    ],

    'data_export' => [
        'queued' => 'Your data export has been queued. You will receive an email with a download link shortly.',
        'mail' => [
            'subject' => 'Your QBazaar data export is ready',
            'greeting' => 'Hello,',
            'line_intro' => 'Your personal data export is ready for download.',
            'action' => 'Download my data',
            'line_expires' => 'This link works once and expires in :hours hours.',
            'line_ignore' => 'If you did not request this export, please contact our support team immediately.',
        ],
    ],

    'support_notifications' => [
        'reply' => [
            'subject' => 'New reply to your support ticket',
            'greeting' => 'Hello,',
            'line_intro' => 'Our support team replied to your ticket ":subject":',
            'action' => 'View ticket',
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
            'subject' => 'Your ad expires soon',
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
        'report_actioned' => [
            'title' => 'Thanks for your report',
            'body' => 'We reviewed your report and took action.',
        ],
        'report_dismissed' => [
            'title' => 'Thanks for your report',
            'body' => 'We reviewed your report and found no breach of our rules.',
        ],
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
            'body' => 'Your ad ":title" will expire on :expires_at. Renew to stay visible.',
        ],
        'ad_expired' => [
            'title' => 'Your ad has expired',
            'body' => 'Your ad ":title" has expired. Renew it in one tap to bring it back.',
        ],
        'saved_search_match' => [
            'title' => 'New ad matches your search',
            'body' => 'A new ad ":title" matches your saved search ":search".',
        ],
        'data_export_ready' => [
            'title' => 'Your data export is ready',
            'body' => 'Tap to download your personal data export.',
        ],
        'support_reply' => [
            'title' => 'Support replied to your ticket',
            'body' => 'Our support team replied to ":subject".',
        ],
        'security_alert' => [
            'title' => 'New sign-in detected',
            'body' => 'A new sign-in from :device. If this wasn\'t you, secure your account now.',
        ],
        'message_new' => [
            'title' => 'New message from :name',
        ],
        'offer_created' => [
            'title' => 'New offer',
            'body' => 'You received an offer of :amount :currency on ":title".',
        ],
        'offer_countered' => [
            'title' => 'Counter-offer received',
            'body' => 'You received a counter-offer of :amount :currency on ":title".',
        ],
        'offer_accepted' => [
            'title' => 'Offer accepted',
            'body' => 'Your offer of :amount :currency on ":title" was accepted.',
        ],
        'offer_rejected' => [
            'title' => 'Offer declined',
            'body' => 'Your offer of :amount :currency on ":title" was declined.',
        ],
        'offer_withdrawn' => [
            'title' => 'Offer withdrawn',
            'body' => 'The offer of :amount :currency on ":title" was withdrawn.',
        ],
        'offer_expired' => [
            'title' => 'Offer expired',
            'body' => 'The offer of :amount :currency on ":title" has expired.',
        ],
        'system_announcement' => [
            'greeting' => 'Hello,',
            'footer' => 'Thanks for being a part of QBazaar.',
        ],
        'ad_price_changed' => [
            'title' => 'Price drop on a favourite',
            'body' => '":title" is now :price :currency (was :previous).',
        ],
        'ad_new_from_followed' => [
            'title' => 'New ad from :name',
            'body' => ':name just posted ":title".',
        ],
    ],
];
