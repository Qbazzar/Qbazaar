import type { Metadata } from 'next';
import { Suspense } from 'react';

import { HelpIndexClient } from './HelpIndexClient';
import { t } from '@/lib/i18n/messages';

export const metadata: Metadata = {
  title: t('help.title', 'مركز المساعدة'),
  description: t('help.subtitle'),
};

export default function HelpIndexPage() {
  return (
    <Suspense fallback={null}>
      <HelpIndexClient />
    </Suspense>
  );
}
