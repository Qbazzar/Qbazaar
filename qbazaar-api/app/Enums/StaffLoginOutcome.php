<?php

declare(strict_types=1);

namespace App\Enums;

enum StaffLoginOutcome
{
    /** The password was enough: the session is signed in. */
    case AUTHENTICATED;

    /** The password was right, but the authenticator code is still owed. */
    case TWO_FACTOR_REQUIRED;
}
