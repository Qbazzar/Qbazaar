import type { Metadata } from 'next';
import { TicketDetailClient } from './TicketDetailClient';
import { pageGutter } from '@/components/design-system/page-gutter';
import { cn } from '@/lib/utils';

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
  return (
    // Support keeps its own look until it is reskinned; it only needs the page gutter.
    <div className={cn('mx-auto w-full max-w-[1440px] py-6 qb-tablet:py-10', pageGutter)}>
      <TicketDetailClient id={id} />
    </div>
  );
}
