'use client';

import { useId } from 'react';
import { Switch as SwitchPrimitive } from '@base-ui/react/switch';

import { focusRing } from '@/components/design-system/focus-ring';
import { cn } from '@/lib/utils';

export interface SettingsToggle {
  key: string;
  title: string;
  description: string;
  checked: boolean;
}

interface SettingsToggleListProps {
  items: readonly SettingsToggle[];
  onChange: (key: string, checked: boolean) => void;
  disabled?: boolean;
}

/**
 * Switch rows of the Data Protection and Email Message panels (mkToggle in
 * account.html): one white card, a line under each row, a 46×26 switch that
 * is orange when on and grey when off.
 */
export function SettingsToggleList({ items, onChange, disabled }: SettingsToggleListProps) {
  return (
    <ul className="rounded-qb-xl border border-qb-line bg-qb-surface px-6 py-2">
      {items.map((item) => (
        <ToggleRow key={item.key} item={item} onChange={onChange} disabled={disabled} />
      ))}
    </ul>
  );
}

function ToggleRow({
  item,
  onChange,
  disabled,
}: {
  item: SettingsToggle;
  onChange: (key: string, checked: boolean) => void;
  disabled?: boolean;
}) {
  const id = useId();
  return (
    <li className="flex items-center justify-between gap-3.5 border-b border-qb-line py-5">
      <div className="min-w-0 flex-1">
        <p id={`${id}-title`} className="text-qb-body font-medium text-qb-ink">
          {item.title}
        </p>
        <p id={`${id}-desc`} className="mt-1 text-qb-caption text-qb-ink-subtle">
          {item.description}
        </p>
      </div>
      <SwitchPrimitive.Root
        checked={item.checked}
        onCheckedChange={(checked) => onChange(item.key, checked)}
        disabled={disabled}
        aria-labelledby={`${id}-title`}
        aria-describedby={`${id}-desc`}
        className={cn(
          'relative h-[26px] w-[46px] shrink-0 cursor-pointer rounded-[20px] bg-qb-acct-toggle-off transition-colors duration-200',
          'data-checked:bg-qb-brand data-disabled:cursor-progress motion-reduce:transition-none',
          focusRing,
        )}
      >
        <SwitchPrimitive.Thumb
          className={cn(
            'absolute start-[3px] top-[3px] size-5 rounded-full bg-qb-surface transition-[inset-inline-start] duration-200 motion-reduce:transition-none',
            'data-checked:start-[23px]',
          )}
        />
      </SwitchPrimitive.Root>
    </li>
  );
}
