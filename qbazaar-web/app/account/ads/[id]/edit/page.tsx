import { Suspense } from 'react';
import { EditAdClient } from './EditAdClient';

interface PageProps {
  params: Promise<{ id: string }>;
}

/**
 * `/account/ads/{id}/edit` — server shell that simply hands off to the
 * client island. The client component runs the auth guard, fetches the ad,
 * and rebuilds the wizard in edit mode.
 */
export default async function EditAdPage({ params }: PageProps) {
  const { id } = await params;
  return (
    <Suspense fallback={null}>
      <EditAdClient adId={id} />
    </Suspense>
  );
}
