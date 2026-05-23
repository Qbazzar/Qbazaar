<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Authentication Language Lines
|--------------------------------------------------------------------------
|
| Keys mirror the ErrorCode::messageKey() output (errors.<lowercased.dotted.case>)
| plus a few cross-cutting validation / not_found keys used by the global
| exception handler.
|
*/

return [
    'failed' => 'These credentials do not match our records.',
    'password' => 'The provided password is incorrect.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',
];
