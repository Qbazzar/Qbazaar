'use client';

import * as React from 'react';
import { useTheme } from 'next-themes';
import { MoonIcon, SunIcon } from '@/components/design-system/icons';
import { Button } from '@/components/ui/button';
import { t } from '@/lib/i18n/messages';

/**
 * Light/Dark theme toggle. Wraps next-themes. Renders a stable
 * placeholder until the client has hydrated so the SSR shell doesn't
 * mismatch (next-themes warns when the resolved theme isn't known yet).
 */
export function ThemeToggle() {
  const { resolvedTheme, setTheme } = useTheme();
  const [mounted, setMounted] = React.useState(false);

  React.useEffect(() => {
    setMounted(true);
  }, []);

  const isDark = mounted && resolvedTheme === 'dark';
  const label = isDark
    ? t('common.theme.switch_to_light', 'الوضع النهاري')
    : t('common.theme.switch_to_dark', 'الوضع الليلي');

  return (
    <Button
      type="button"
      size="icon-sm"
      variant="ghost"
      aria-label={label}
      title={label}
      onClick={() => setTheme(isDark ? 'light' : 'dark')}
      disabled={!mounted}
    >
      {isDark ? <SunIcon className="size-4" /> : <MoonIcon className="size-4" />}
    </Button>
  );
}
