import Link from 'next/link';
import { cn } from '@/lib/utils';
import { t } from '@/lib/i18n/messages';

export interface AuthTabsProps {
  active: 'login' | 'register';
}

/**
 * Segmented control linking to /login and /register. Renders as `<Link>` so
 * SSR + browser back-button work, and so users without JS still navigate.
 */
export function AuthTabs({ active }: AuthTabsProps) {
  return (
    <nav
      aria-label="Auth tabs"
      className="bg-muted text-muted-foreground flex gap-1 rounded-xl p-1 text-sm font-semibold"
    >
      <Tab href="/login" active={active === 'login'}>
        {t('auth.tabs.login')}
      </Tab>
      <Tab href="/register" active={active === 'register'}>
        {t('auth.tabs.register')}
      </Tab>
    </nav>
  );
}

function Tab({
  href,
  active,
  children,
}: {
  href: string;
  active: boolean;
  children: React.ReactNode;
}) {
  return (
    <Link
      href={href}
      aria-current={active ? 'page' : undefined}
      className={cn(
        'flex-1 rounded-lg px-4 py-2 text-center transition-colors',
        active
          ? 'bg-card text-foreground shadow-sm'
          : 'hover:text-foreground',
      )}
    >
      {children}
    </Link>
  );
}
