/**
 * Renders staff-authored long-form HTML (CMS pages, help articles) with the
 * design-system typography.
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

/** Element styles for the sanitized body: there is no prose plugin, so they live here as arbitrary variants. */
const prose = [
  'max-w-[860px] font-qb text-qb-body leading-[1.75] break-words text-qb-ink-body qb-desktop:text-qb-body-lg qb-desktop:leading-[1.75]',
  '[&>:first-child]:mt-0 [&>:last-child]:mb-0',
  '[&_:is(h1,h2,h3,h4)]:mt-8 [&_:is(h1,h2,h3,h4)]:mb-3 [&_:is(h1,h2,h3,h4)]:font-qb [&_:is(h1,h2,h3,h4)]:font-semibold [&_:is(h1,h2,h3,h4)]:tracking-normal [&_:is(h1,h2,h3,h4)]:text-qb-ink',
  '[&_h1]:text-qb-h3 [&_h2]:text-qb-h4 [&_h3]:text-qb-h5 [&_h4]:text-qb-body-lg qb-desktop:[&_h1]:text-qb-h2 qb-desktop:[&_h2]:text-qb-h3',
  '[&_p]:my-4 [&_:is(ul,ol)]:my-4 [&_:is(ul,ol)]:ps-6 [&_ul]:list-disc [&_ol]:list-decimal [&_li]:my-1.5 [&_li]:marker:text-qb-brand',
  '[&_a]:rounded-qb-xs [&_a]:text-qb-brand [&_a]:underline [&_a]:underline-offset-4 [&_a:hover]:text-qb-brand-hover [&_strong]:font-semibold [&_strong]:text-qb-ink',
  '[&_a:focus-visible]:outline-2 [&_a:focus-visible]:outline-offset-2 [&_a:focus-visible]:outline-solid [&_a:focus-visible]:outline-qb-brand-active',
  '[&_blockquote]:my-6 [&_blockquote]:rounded-qb-md [&_blockquote]:border-s-4 [&_blockquote]:border-qb-brand [&_blockquote]:bg-qb-brand-soft [&_blockquote]:px-5 [&_blockquote]:py-3 [&_blockquote_a]:text-qb-brand-on-soft',
  '[&_code]:rounded-qb-xs [&_code]:bg-qb-fill [&_code]:px-1.5 [&_code]:py-0.5 [&_code]:text-[0.9em]',
  '[&_pre]:my-6 [&_pre]:overflow-x-auto [&_pre]:rounded-qb-lg [&_pre]:bg-qb-fill [&_pre]:p-4 [&_pre_code]:bg-transparent [&_pre_code]:p-0',
  '[&_hr]:my-8 [&_hr]:border-qb-line [&_img]:my-6 [&_img]:h-auto [&_img]:max-w-full [&_img]:rounded-qb-lg',
  '[&_table]:my-6 [&_table]:block [&_table]:w-full [&_table]:overflow-x-auto [&_table]:border-collapse [&_table]:text-qb-body',
  '[&_:is(th,td)]:border [&_:is(th,td)]:border-qb-line [&_:is(th,td)]:px-3 [&_:is(th,td)]:py-2 [&_:is(th,td)]:text-start [&_th]:bg-qb-fill [&_th]:font-semibold [&_th]:text-qb-ink',
].join(' ');

export function MarkdownContent({ html, className }: Props) {
  const safeHtml = DOMPurify.sanitize(html, { USE_PROFILES: { html: true } });

  return <div className={cn(prose, className)} dangerouslySetInnerHTML={{ __html: safeHtml }} />;
}
