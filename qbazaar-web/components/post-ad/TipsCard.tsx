import { CircleCheck } from 'lucide-react';

import { cardVariants } from '@/components/design-system/Card';
import { Icon } from '@/components/design-system/Icon';
import { t } from '@/lib/i18n/messages';

import { AsideTitle } from './ProfileCard';

const TIP_KEYS = ['photos', 'price', 'honest', 'details', 'light'] as const;

export function TipsCard() {
  return (
    <section aria-labelledby="post-ad-tips" className={cardVariants()}>
      <AsideTitle id="post-ad-tips">{t('post_ad.tips.title')}</AsideTitle>
      <ul className="flex flex-col gap-4">
        {TIP_KEYS.map((key) => (
          <li key={key} className="flex items-start gap-2.5 text-qb-caption leading-[1.5] text-qb-ink-body">
            <Icon icon={CircleCheck} size="sm" className="mt-px size-[18px] text-qb-brand" />
            {t(`post_ad.tips.${key}`)}
          </li>
        ))}
      </ul>
    </section>
  );
}
