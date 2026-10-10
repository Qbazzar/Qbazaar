import { BadgeCheck } from 'lucide-react';

import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';
import type { AccountType, PublicUser } from '@/lib/api/types';

/** The name a seller goes by: the business name for business accounts. */
export function sellerDisplayName(user: Pick<PublicUser, 'full_name' | 'business_name'>): string {
  return user.business_name?.trim() || user.full_name;
}

/** A seller counts as verified once their phone number is. */
export function isVerifiedSeller(user: Pick<PublicUser, 'verification_badges'>): boolean {
  return Boolean(user.verification_badges?.phone_verified);
}

/** Blue tick after a verified seller's name. */
export function VerifiedMark({ className }: { className?: string }) {
  return (
    <BadgeCheck
      role="img"
      aria-label={t('users.profile.verified')}
      className={cn('size-5 shrink-0 fill-qb-info text-qb-surface', className)}
    />
  );
}

/**
 * "Private Seller" chip of the ad detail's seller card: white with a 1 px
 * brand outline, r8, Montserrat 10 px / 500 (typo.js CHIPS).
 */
export function SellerTypeChip({ accountType, className }: { accountType: AccountType; className?: string }) {
  return (
    <span
      className={cn(
        'inline-flex w-fit shrink-0 items-center rounded-qb-sm border border-qb-brand bg-qb-surface px-3 py-[5px] font-qb-label text-qb-tiny font-medium whitespace-nowrap text-qb-brand',
        className,
      )}
    >
      {t(`users.profile.seller_type.${accountType}`)}
    </span>
  );
}
