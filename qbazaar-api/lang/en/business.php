<?php

declare(strict_types=1);

/*
 * Business accounts: what the applicant reads about their application and
 * the verified badge (notifications) and the application API messages.
 */
return [
    'notifications' => [
        'action' => 'View verification status',
        'application_approved' => [
            'title' => 'Your business application was approved',
            'body' => 'Your account is now a verified business account. :reason',
        ],
        'application_rejected' => [
            'title' => 'Your business application was rejected',
            'body' => 'Reason: :reason',
        ],
        'application_needs_info' => [
            'title' => 'We need more information about your application',
            'body' => 'Please update your business application: :reason',
        ],
        'verification_granted' => [
            'title' => 'Your business account is verified',
            'body' => 'The verified badge now shows next to your business name.',
        ],
        'verification_revoked' => [
            'title' => 'Your business verification was removed',
            'body' => 'Reason: :reason',
        ],
    ],

    'api' => [
        'documents_required' => 'Attach at least one document (commercial registration or licence).',
        'documents_max' => 'You can attach up to :max documents.',
    ],
];
