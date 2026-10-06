import { Suspense } from 'react';
import type { Metadata } from 'next';
import { MyTicketsClient } from './MyTicketsClient';
import { t } from '@/lib/i18n/messages';
import { resolveServerLocale } from '@/lib/i18n/server';
import { pageGutter } from '@/components/design-system/page-gutter';
import { cn } from '@/lib/utils';

export async function generateMetadata(): Promise<Metadata> {
  await resolveServerLocale();

  return {
    title: t('support.my_tickets', 'تذاكر الدعم'),
  };
}

export default function MyTicketsPage() {
  return (
    // Support keeps its own look until it is reskinned; it only needs the page gutter.
    <div className={cn('mx-auto w-full max-w-[1440px] py-6 qb-tablet:py-10', pageGutter)}>
      <Suspense fallback={null}>
        <MyTicketsClient />
      </Suspense>
    </div>
  );
}
