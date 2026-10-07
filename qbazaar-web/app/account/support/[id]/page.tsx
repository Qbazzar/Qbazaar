import type { Metadata } from 'next';
import { TicketDetailClient } from './TicketDetailClient';

interface PageProps {
  params: Promise<{ id: string }>;
}

export async function generateMetadata({
  params,
}: PageProps): Promise<Metadata> {
  const { id } = await params;
  return { title: id };
}

export default async function TicketDetailPage({ params }: PageProps) {
  const { id } = await params;
  return <TicketDetailClient id={id} />;
}
