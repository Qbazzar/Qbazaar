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
        'no_markup' => 'The :attribute may not contain < or > characters.',
        'category_not_selectable' => 'Choose an active sub-category that has no further sub-categories.',
    ],

    'rate' => [
        'limit' => [
            'exceeded' => 'Too many requests. Please slow down and try again later.',
        ],
    ],

    'server' => [
        'error' => 'An unexpected error occurred. Please try again later.',
    ],

    'request' => [
        'in' => [
            'progress' => 'The same request is still being processed. Please try again in a moment.',
        ],
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
        'registration' => [
            'required' => 'No account uses this email yet. Send your name, phone and account type to create one.',
        ],
        'device' => [
            'challenge' => [
                'invalid' => 'The device verification has expired. Please sign in again.',
            ],
        ],
        'social' => [
            'token' => [
                'invalid' => 'The sign-in token could not be verified.',
            ],
            'provider' => [
                'unavailable' => 'This sign-in method is not available right now.',
            ],
        ],
        'password' => [
            'login' => [
                'disabled' => 'Password sign-in is turned off. Sign in with an email code instead.',
            ],
        ],
    ],

    'turnstile' => [
        'failed' => 'The security check failed. Please try again.',
    ],

    'not' => [
        'found' => 'The requested resource was not found.',
    ],

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

    'fav' => [
        'limit' => [
            'reached' => 'You have reached the maximum number of favourites. Remove some to add new ones.',
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
        'chat' => [
            'disabled' => 'This seller does not accept messages.',
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

    'review' => [
        'not' => [
            'eligible' => 'You can only review a seller after a completed deal (an accepted offer) on this ad.',
        ],
        'already' => [
            'exists' => 'You have already reviewed this ad.',
        ],
        'own' => [
            'ad' => 'You cannot review your own ad.',
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

    'cms' => [
        'page' => [
            'not' => [
                'found' => 'Page not found.',
            ],
        ],
    ],

    'help' => [
        'article' => [
            'not' => [
                'found' => 'Help article not found.',
            ],
        ],
        'category' => [
            'not' => [
                'found' => 'Help category not found.',
            ],
        ],
    ],

    'ticket' => [
        'not' => [
            'found' => 'Support ticket not found.',
        ],
        'forbidden' => 'You are not authorised to access this ticket.',
        'invalid' => [
            'transition' => 'This ticket cannot be updated in its current state.',
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

    'data' => [
        'export' => [
            'link' => [
                'expired' => 'This download link has already been used or has expired. Request a new export from your account.',
            ],
        ],
    ],

    'account' => [
        'reauth' => [
            'invalid' => 'The confirmation code is wrong or has expired. Request a new code.',
        ],
        'email' => [
            'change' => [
                'link' => [
                    'invalid' => 'This confirmation link has already been used or is no longer valid.',
                ],
            ],
        ],
        'debt' => [
            'outstanding' => 'You still owe :amount QAR in commission. Settle it before deleting your account.',
        ],
        'has' => [
            'open' => [
                'order' => 'You have an order in progress. Complete or cancel it before deleting your account.',
            ],
        ],
        'wallet' => [
            'not' => [
                'empty' => 'You still have :amount QAR in your wallet. Withdraw it before deleting your account.',
            ],
        ],
    ],

    'address' => [
        'not' => [
            'found' => 'Address not found.',
        ],
        'limit' => [
            'reached' => 'You have reached the maximum number of saved addresses.',
        ],
    ],

    'follow' => [
        'self' => [
            'forbidden' => 'You cannot follow yourself.',
        ],
        'blocked' => 'You cannot follow this user.',
    ],

    'business' => [
        'account' => [
            'required' => 'This is only available to business accounts.',
        ],
    ],

    'order' => [
        'not' => [
            'found' => 'Order not found.',
        ],
        'invalid' => [
            'transition' => 'This order cannot move to that status.',
        ],
        'ad' => [
            'has' => [
                'active' => [
                    'order' => 'This item already has an order in progress.',
                ],
            ],
        ],
        'seller' => [
            'debt' => [
                'ceiling' => 'The seller has unpaid commission above the allowed limit and cannot take new orders until it is settled.',
            ],
        ],
        'forbidden' => 'You are not allowed to do this on this order.',
    ],

    'wallet' => [
        'insufficient' => [
            'balance' => 'Your wallet balance is not enough for this.',
        ],
        'exceeds' => [
            'commission' => [
                'debt' => 'The amount is more than the commission you owe.',
            ],
        ],
    ],

    'purchase' => [
        'request' => [
            'not' => [
                'found' => 'Purchase request not found.',
                'pending' => 'This purchase request is no longer pending.',
            ],
            'open' => [
                'exists' => 'You already have a pending purchase request on this ad.',
            ],
            'forbidden' => 'You are not allowed to do this on this purchase request.',
            'own' => [
                'ad' => 'You cannot buy your own ad.',
            ],
            'ad' => [
                'not' => [
                    'available' => 'This ad cannot be bought right now.',
                ],
            ],
        ],
        'quantity' => [
            'unavailable' => 'The seller does not have that many units.',
        ],
    ],

    'checkout' => [
        'delivery' => [
            'unavailable' => 'The seller does not deliver this item; choose pickup.',
        ],
    ],
];
