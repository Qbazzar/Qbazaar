/**
 * Renders admin-authored long-form HTML/markdown content.
 *
 * The backend already sanitises CMS bodies (see Sprint 12 backend) and only
 * trusted admins can author them, so `dangerouslySetInnerHTML` is safe here.
 * We wrap the markup in a `.cms-prose` block that provides typography
 * defaults (headings, lists, paragraphs, links) tuned to the QBFront palette.
 */
import { cn } from '@/lib/utils';

interface Props {
  html: string;
  className?: string;
}

export function MarkdownContent({ html, className }: Props) {
  return (
    <div
      className={cn('cms-prose', className)}
      // The body comes from the admin CMS; the backend strips dangerous tags.
      dangerouslySetInnerHTML={{ __html: html }}
    />
  );
}
