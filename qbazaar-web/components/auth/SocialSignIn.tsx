'use client';

import Image from 'next/image';
import { toast } from 'sonner';

import { focusRing } from '@/components/design-system/focus-ring';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

const PROVIDERS = [
  { id: 'google', name: 'Google' },
  { id: 'facebook', name: 'Facebook' },
  { id: 'apple', name: 'Apple' },
] as const;

/**
 * The social row of login.html (`.qb-socials` + `.qb-div`): three equal
 * tiles, then "or continue with" between two hairlines.
 *
 * Google and Apple sign-in wait for the client keys the owner has not
 * issued yet, and the API has no Facebook provider, so a tile says so
 * instead of starting a sign-in that cannot finish.
 */
export function SocialSignIn() {
  return (
    <div className="font-qb">
      <div className="mt-[30px] flex gap-2.5 qb-tablet:gap-4">
        {PROVIDERS.map((provider) => (
          <button
            key={provider.id}
            type="button"
            aria-label={t('auth.social.continue_with', { provider: provider.name })}
            onClick={() => toast.info(t('auth.social.unavailable', { provider: provider.name }))}
            className={cn(
              'flex h-14 flex-1 cursor-pointer items-center justify-center rounded-qb-md border border-qb-line bg-qb-surface hover:border-qb-auth-social-hover',
              focusRing,
            )}
          >
            <Image src={`/brand/social/${provider.id}.svg`} alt="" width={26} height={26} unoptimized />
          </button>
        ))}
      </div>
      <p className="mt-[22px] flex items-center gap-4 text-qb-body-sm text-qb-auth-muted before:h-px before:flex-1 before:bg-qb-line after:h-px after:flex-1 after:bg-qb-line">
        {t('auth.social.divider')}
      </p>
    </div>
  );
}
