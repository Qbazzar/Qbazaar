import { Avatar, type AvatarProps } from '@/components/design-system/Avatar';
import { cn } from '@/lib/utils';

/**
 * Avatar of a person whose name is printed right next to it: hidden from
 * assistive tech so the name is read once. Brand initials use the darker
 * brand-on-soft text, which reaches 4.5:1 on the soft fill.
 */
export function NamedAvatar({ tone = 'brand', className, ...props }: AvatarProps) {
  return (
    <span aria-hidden="true" className="flex shrink-0">
      <Avatar tone={tone} className={cn(tone === 'brand' && 'text-qb-brand-on-soft', className)} {...props} />
    </span>
  );
}
