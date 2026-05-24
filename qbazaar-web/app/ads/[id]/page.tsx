import type { Metadata } from 'next';
import { AdDetailClient } from './AdDetailClient';

interface PageProps {
  params: Promise<{ id: string }>;
}

export async function generateMetadata({
  params,
}: PageProps): Promise<Metadata> {
  const { id } = await params;
  return {
    title: id,
  };
}

/**
 * Ad detail — `/ads/{id}`.
 *
 * Server entrypoint hands the id off to the client island. The detail page
 * is data-heavy (gallery, custom fields, seller card) so we don't try to do
 * any of it server-side for now — the `AdDetailClient` reads from TanStack
 * Query and renders skeletons while the request is in flight.
 */
export default async function AdDetailPage({ params }: PageProps) {
  const { id } = await params;
  return <AdDetailClient id={id} />;
}
