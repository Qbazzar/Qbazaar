<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| User-targeted messages — English
|--------------------------------------------------------------------------
|
| Surface strings tied to Sprint 2 user-management flows. Keys mirror the
| ErrorCode::messageKey() output for any error-code-bearing string, and use
| friendly snake_case for success messages.
|
*/

return [
    'errors' => [
        'not_found' => 'User not found.',
        'block_admin_forbidden' => 'You cannot block an administrator.',
        'block_self_forbidden' => 'You cannot block yourself.',
        'password_current_required' => 'The current password is incorrect.',
        'deactivation_password_required' => 'Please provide your password to deactivate the account.',
    ],

    'messages' => [
        'blocked' => 'User has been blocked.',
        'unblocked' => 'User has been unblocked.',
        'password_updated' => 'Your password has been updated.',
        'profile_updated' => 'Your profile has been updated.',
        'privacy_updated' => 'Your privacy settings have been updated.',
        'session_revoked' => 'Session revoked.',
    ],
];
