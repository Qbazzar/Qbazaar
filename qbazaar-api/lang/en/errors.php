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

    'user' => [
        'not' => [
            'found' => 'User not found.',
        ],
    ],
];
