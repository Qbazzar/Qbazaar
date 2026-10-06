import type { InputHTMLAttributes, ReactNode, SelectHTMLAttributes, TextareaHTMLAttributes } from 'react';
import { ChevronDown } from 'lucide-react';

import { cn } from '@/lib/utils';

/** Box shared by Input, Select and Textarea (`.qb-field input` in Qbazaar-front). */
const controlBase = [
  'w-full rounded-qb-md border border-qb-line bg-qb-surface font-qb text-qb-body text-qb-ink',
  'placeholder:text-qb-placeholder outline-none transition-colors',
  'focus-visible:border-qb-brand focus-visible:ring-2 focus-visible:ring-qb-brand/20',
  'aria-invalid:border-qb-danger aria-invalid:focus-visible:ring-qb-danger/20',
  'disabled:cursor-not-allowed disabled:bg-qb-fill disabled:text-qb-ink-subtle',
].join(' ');

export interface InputProps extends InputHTMLAttributes<HTMLInputElement> {
  /** Decorative icon at the start of the field (e.g. a search glass). */
  startIcon?: ReactNode;
}

export function Input({ className, startIcon, type = 'text', ...props }: InputProps) {
  const input = (
    <input
      type={type}
      className={cn(controlBase, 'h-[52px] px-4', startIcon ? 'ps-11' : null, className)}
      {...props}
    />
  );
  if (!startIcon) return input;

  return (
    <div className="relative">
      <span
        aria-hidden="true"
        className="pointer-events-none absolute inset-y-0 start-4 flex items-center text-qb-ink-subtle [&_svg]:size-5"
      >
        {startIcon}
      </span>
      {input}
    </div>
  );
}

export type TextareaProps = TextareaHTMLAttributes<HTMLTextAreaElement>;

export function Textarea({ className, rows = 4, ...props }: TextareaProps) {
  return <textarea rows={rows} className={cn(controlBase, 'min-h-[120px] px-4 py-3.5', className)} {...props} />;
}

export type SelectProps = SelectHTMLAttributes<HTMLSelectElement>;

/** Native select: keeps the platform picker on phones and needs no client JS. */
export function Select({ className, children, ...props }: SelectProps) {
  return (
    <div className="relative">
      <select className={cn(controlBase, 'h-[52px] appearance-none ps-4 pe-11', className)} {...props}>
        {children}
      </select>
      <ChevronDown
        aria-hidden="true"
        className="pointer-events-none absolute inset-y-0 end-4 my-auto size-5 text-qb-ink-subtle"
      />
    </div>
  );
}
