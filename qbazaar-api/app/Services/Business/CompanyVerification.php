<?php

declare(strict_types=1);

namespace App\Services\Business;

use App\Enums\AccountType;
use App\Enums\BusinessNotice;
use App\Exceptions\DomainException;
use App\Exceptions\ErrorCode;
use App\Models\User;
use App\Notifications\Business\BusinessAccountNotification;
use App\Services\Admin\AdminAuditLogger;
use App\Services\Catalog\CatalogCache;
use Illuminate\Support\Facades\DB;

/**
 * The verified-business badge: staff grant it after checking a business
 * (directly, or by approving its application) and can revoke it with a
 * reason the owner reads. The user row is locked for the change, every
 * change is written to the admin audit log, and the owner is notified from
 * the queue.
 */
class CompanyVerification
{
    public function __construct(
        private readonly AdminAuditLogger $audit,
        private readonly CatalogCache $catalog,
    ) {}

    public function verify(User $admin, User $company): void
    {
        $this->grant($admin, $company);

        $this->audit->record($admin, 'admin.companies.verify', $company);
        $company->notify(new BusinessAccountNotification(BusinessNotice::VERIFICATION_GRANTED));
    }

    public function revoke(User $admin, User $company, string $reason): void
    {
        DB::transaction(function () use ($company): void {
            $locked = $this->lock($company);

            if ($locked->business_verified_at === null) {
                throw new DomainException(ErrorCode::BUSINESS_NOT_VERIFIED);
            }

            $locked->forceFill(['business_verified_at' => null, 'business_verified_by' => null])->save();
            $company->setRawAttributes($locked->getAttributes(), true);
        });

        $this->audit->record($admin, 'admin.companies.revoke-verification', $company, ['reason' => $reason]);
        $company->notify(new BusinessAccountNotification(BusinessNotice::VERIFICATION_REVOKED, $reason));
        DB::afterCommit(fn () => $this->catalog->homeCurationChanged());
    }

    /**
     * Marks the account as a verified business, turning a private account
     * into a business one when it applied as such. The caller audits and
     * notifies, since the reason for the grant differs per caller.
     */
    public function grant(User $admin, User $company, bool $convertToBusiness = false): void
    {
        DB::transaction(function () use ($admin, $company, $convertToBusiness): void {
            $locked = $this->lock($company);

            if (! $locked->isBusiness() && ! $convertToBusiness) {
                throw new DomainException(ErrorCode::BUSINESS_ACCOUNT_REQUIRED);
            }

            if ($locked->isVerifiedBusiness()) {
                throw new DomainException(ErrorCode::BUSINESS_ALREADY_VERIFIED);
            }

            $locked->forceFill([
                'account_type' => AccountType::BUSINESS,
                'business_verified_at' => now(),
                'business_verified_by' => $admin->id,
            ])->save();

            $company->setRawAttributes($locked->getAttributes(), true);
        });

        // Approvals call this inside their own transaction: the cached feed is
        // dropped once the badge is committed, never before.
        DB::afterCommit(fn () => $this->catalog->homeCurationChanged());
    }

    private function lock(User $company): User
    {
        /** @var User */
        return User::query()->whereKey($company->id)->lockForUpdate()->firstOrFail();
    }
}
