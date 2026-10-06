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
      className={cn('size-5 shrink-0 fill-qb-info text-white', className)}
    />
  );
}

/**
 * "Private Seller" chip of the seller card (Montserrat in the reference, like
 * every status chip there).
 */
export function SellerTypeChip({ accountType, className }: { accountType: AccountType; className?: string }) {
  return (
    <span
      className={cn(
        'inline-flex h-5 w-fit items-center rounded-[4px] border border-qb-brand bg-qb-brand-soft px-2 font-qb-label text-qb-tiny leading-none font-medium whitespace-nowrap text-qb-brand',
        className,
      )}
    >
      {t(`users.profile.seller_type.${accountType}`)}
    </span>
  );
}
