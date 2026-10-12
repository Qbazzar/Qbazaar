import type { ReactNode } from 'react';

import { PageEnter } from '@/components/layout/PageEnter';

/** Remounts on navigation, so each screen plays its entrance as the reference's do. */
export default function Template({ children }: { children: ReactNode }) {
  return <PageEnter>{children}</PageEnter>;
}
