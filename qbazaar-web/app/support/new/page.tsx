import type { Metadata } from 'next';
import { NewTicketClient } from './NewTicketClient';
import { t } from '@/lib/i18n/messages';

export const metadata: Metadata = {
  title: t('support.new_ticket', 'تواصل مع الدعم'),
};

export default function NewTicketPage() {
  return <NewTicketClient />;
}
