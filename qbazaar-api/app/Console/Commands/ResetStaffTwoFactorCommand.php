<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Admin\TwoFactor\StaffTwoFactorService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Break-glass reset for a staff member who lost both their authenticator and
 * their recovery codes, including the last super admin, whom no colleague
 * outranks. Needs shell access to the server, and is written to the audit
 * log as the staff member's own reset.
 */
class ResetStaffTwoFactorCommand extends Command
{
    protected $signature = 'admin:two-factor-reset {email : The staff member\'s email}';

    protected $description = 'Remove a staff member\'s two-factor sign-in so they can enrol again';

    public function handle(StaffTwoFactorService $twoFactor): int
    {
        $user = User::query()->where('email', Str::lower((string) $this->argument('email')))->first();

        if ($user === null || ! $user->isStaff()) {
            $this->error('No staff member has that email.');

            return self::FAILURE;
        }

        $twoFactor->disable($user, $user);
        $this->info('Two-factor sign-in removed. They will be asked to enrol again if their role requires it.');

        return self::SUCCESS;
    }
}
