<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Error Messages — English
|--------------------------------------------------------------------------
|
| Keys mirror ErrorCode::messageKey(), which uses the lower-snake form of
| each enum case: 'errors.<lowercased.dotted.case>'. Keep this file in sync
| with App\Exceptions\ErrorCode whenever new codes are added.
|
*/

return [
    'validation' => [
        'failed' => 'The given data was invalid.',
    ],

    'rate' => [
        'limit' => [
            'exceeded' => 'Too many requests. Please slow down and try again later.',
        ],
    ],

    'server' => [
        'error' => 'An unexpected error occurred. Please try again later.',
    ],

    'auth' => [
        'invalid' => [
            'credentials' => 'Invalid credentials.',
        ],
        'account' => [
            'suspended' => 'This account has been suspended.',
        ],
        'phone' => [
            'not' => [
                'verified' => 'Phone number is not verified.',
            ],
            'exists' => 'A user with this phone number already exists.',
        ],
        'otp' => [
            'expired' => 'OTP has expired.',
            'invalid' => 'Invalid OTP code.',
        ],
        'rate' => [
            'limited' => 'Too many auth requests. Please try again later.',
        ],
        'email' => [
            'exists' => 'A user with this email already exists.',
        ],
        'token' => [
            'expired' => 'The token has expired.',
            'invalid' => 'The token is invalid.',
        ],
    ],

    'not_found' => 'The requested resource was not found.',

    'forbidden' => 'You are not authorised to perform this action.',

    'category' => [
        'not' => [
            'found' => 'Category not found.',
        ],
    ],

    'location' => [
        'not' => [
            'found' => 'Location not found.',
        ],
    ],

    'ad' => [
        'not' => [
            'found' => 'Ad not found.',
            'active' => 'This ad is not currently active.',
            'publishable' => 'This ad cannot be published in its current state.',
        ],
        'edit' => [
            'forbidden' => 'You cannot edit this ad.',
        ],
        'invalid' => [
            'transition' => 'That status change is not allowed.',
            'status_transition' => 'That status change is not allowed.',
        ],
        'auto' => [
            'moderation' => [
                'rejected' => 'The ad was rejected by automated moderation.',
            ],
        ],
        'daily' => [
            'publish' => [
                'limit' => 'You have reached the daily publishing limit.',
            ],
        ],
        'expired' => 'This ad has expired.',
        'own' => [
            'offer' => [
                'forbidden' => 'You cannot place an offer on your own ad.',
            ],
        ],
        'images' => [
            'required' => 'At least one image is required.',
            'too_many' => 'You can attach at most :max images to an ad.',
        ],
        'image' => [
            'not' => [
                'found' => 'Image not found for this ad.',
            ],
        ],
        'custom' => [
            'fields' => [
                'invalid' => 'The custom fields for this category are invalid.',
            ],
        ],
    ],

    'upload' => [
        'too' => [
            'large' => 'The uploaded file is too large.',
        ],
        'invalid' => [
            'mime' => 'The uploaded file type is not allowed.',
        ],
        'max' => [
            'images' => [
                'reached' => 'You have reached the maximum number of images.',
            ],
        ],
        'magic' => [
            'bytes' => [
                'mismatch' => 'The uploaded file failed integrity verification.',
            ],
        ],
    ],

    'search' => [
        'index' => [
            'unavailable' => 'Search is temporarily unavailable. Please try again shortly.',
        ],
        'invalid' => [
            'params' => 'Some of your search filters are invalid.',
        ],
        'saved' => [
            'limit' => 'You have reached the maximum of 10 saved searches.',
            'not' => [
                'found' => 'Saved search not found.',
            ],
        ],
    ],

    'offer' => [
        'not' => [
            'found' => 'Offer not found.',
            'seller' => 'Only the seller can perform this action.',
            'pending' => 'This offer is no longer pending.',
        ],
        'expired' => 'This offer has expired.',
        'already' => [
            'actioned' => 'This offer has already been actioned.',
        ],
        'active' => [
            'exists' => 'You already have a pending offer on this ad.',
        ],
        'own' => [
            'ad' => 'You cannot make an offer on your own ad.',
        ],
        'ad' => [
            'not' => [
                'active' => 'You can only offer on active ads.',
            ],
        ],
        'forbidden' => 'You are not authorised to act on this offer.',
    ],

    'msg' => [
        'blocked' => 'You cannot message this user.',
        'rate' => [
            'limited' => 'You are sending messages too quickly. Please slow down.',
        ],
        'flagged' => 'Your message was blocked by automated moderation.',
        'conversation' => [
            'not' => [
                'found' => 'Conversation not found.',
            ],
            'own' => [
                'ad' => 'You cannot start a conversation about your own ad.',
            ],
        ],
        'not' => [
            'found' => 'Message not found.',
            'participant' => 'You are not a participant of this conversation.',
        ],
    ],

    'report' => [
        'self' => [
            'forbidden' => 'You cannot report yourself.',
        ],
        'duplicate' => 'You have already reported this recently. Please wait before reporting it again.',
        'invalid' => [
            'target' => 'The reported item could not be found.',
        ],
    ],

    'notif' => [
        'not' => [
            'found' => 'Notification not found.',
        ],
        'forbidden' => 'You are not authorised to access this notification.',
        'device' => [
            'token' => [
                'invalid' => 'The device token is invalid.',
            ],
        ],
    ],

    'user' => [
        'not' => [
            'found' => 'User not found.',
        ],
        'block' => [
            'admin' => [
                'forbidden' => 'You cannot block an administrator.',
            ],
            'self' => [
                'forbidden' => 'You cannot block yourself.',
            ],
        ],
        'password' => [
            'current' => [
                'required' => 'The current password is incorrect.',
            ],
        ],
        'deactivation' => [
            'password' => [
                'required' => 'Please provide your password to deactivate the account.',
            ],
        ],
    ],
];
