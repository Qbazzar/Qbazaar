import type { Metadata } from 'next';
import { NewTicketClient } from './NewTicketClient';
import { t } from '@/lib/i18n/messages';
import { resolveServerLocale } from '@/lib/i18n/server';

export async function generateMetadata(): Promise<Metadata> {
  await resolveServerLocale();

  return {
    title: t('support.new_ticket', 'تواصل مع الدعم'),
  };
}

export default function NewTicketPage() {
  return <NewTicketClient />;
}
