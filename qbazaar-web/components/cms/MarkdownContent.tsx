/**
 * Renders staff-authored long-form HTML (CMS pages, help articles) inside a
 * `.cms-prose` block that provides typography defaults tuned to the QBFront
 * palette.
 *
 * The API already sanitizes these bodies on write; DOMPurify runs again here
 * so a bypass on either side alone cannot reach the visitor.
 */
import DOMPurify from 'isomorphic-dompurify';

import { cn } from '@/lib/utils';

interface Props {
  html: string;
  className?: string;
}

export function MarkdownContent({ html, className }: Props) {
  const safeHtml = DOMPurify.sanitize(html, { USE_PROFILES: { html: true } });

  return (
    <div
      className={cn('cms-prose', className)}
      dangerouslySetInnerHTML={{ __html: safeHtml }}
    />
  );
}
