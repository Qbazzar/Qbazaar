import Link from 'next/link';
import { cn } from '@/lib/utils';
import { t } from '@/lib/i18n/messages';

export interface AuthTabsProps {
  active: 'login' | 'register';
}

/**
 * QBFront `.auth-tabs` segmented control linking to /login and /register.
 * Renders as `<Link>` so SSR + browser back-button work.
 */
export function AuthTabs({ active }: AuthTabsProps) {
  return (
    <nav aria-label="Auth tabs" className="auth-tabs">
      <Tab href="/login" active={active === 'login'}>
        {t('auth.tabs.login', 'تسجيل الدخول')}
      </Tab>
      <Tab href="/register" active={active === 'register'}>
        {t('auth.tabs.register', 'إنشاء حساب')}
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
      className={cn('auth-tabs__btn', active && 'is-active')}
    >
      {children}
    </Link>
  );
}
