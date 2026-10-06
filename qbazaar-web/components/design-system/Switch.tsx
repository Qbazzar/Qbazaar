'use client';

import { Switch as SwitchPrimitive } from '@base-ui/react/switch';

import { cn } from '@/lib/utils';

import { focusRing } from './focus-ring';

export interface SwitchProps extends Omit<SwitchPrimitive.Root.Props, 'className'> {
  className?: string;
}

/**
 * On/off toggle of the settings rows and the saved-search "Notification on"
 * chip (381:8815): green when on. Built on Base UI, so it is a real
 * `role="switch"` with keyboard support; give it an accessible name.
 */
export function Switch({ className, ...props }: SwitchProps) {
  return (
    <SwitchPrimitive.Root
      className={cn(
        'relative inline-flex h-6 w-11 shrink-0 cursor-pointer items-center rounded-qb-pill bg-qb-line p-0.5 transition-colors',
        'data-checked:bg-qb-success data-disabled:cursor-not-allowed data-disabled:opacity-50',
        focusRing,
        className,
      )}
      {...props}
    >
      <SwitchPrimitive.Thumb
        className={cn(
          'block size-5 rounded-full bg-qb-surface shadow-qb-soft transition-transform motion-reduce:transition-none',
          'data-checked:translate-x-5 rtl:data-checked:-translate-x-5',
        )}
      />
    </SwitchPrimitive.Root>
  );
}
