import Image from 'next/image';
import Link from 'next/link';
import { CheckIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { t } from '@/lib/i18n/messages';

/**
 * Split auth layout — hero pane on one side, form on the other.
 *
 * The hero pane is hidden below `lg` so mobile gets the form full-width.
 * RTL: Tailwind's logical properties (`ms-*`, `me-*`) + `grid` order keep
 * the hero on the visual "start" side regardless of direction.
 */
export default function AuthLayout({ children }: { children: ReactNode }) {
  return (
    <div className="bg-cream-50 flex min-h-svh items-center justify-center p-4 sm:p-8">
      <div className="bg-card border-border w-full max-w-5xl overflow-hidden rounded-3xl border shadow-sm">
        <div className="grid min-h-[640px] lg:grid-cols-2">
          <HeroPane />
          <section className="bg-card flex flex-col justify-center px-6 py-10 sm:px-12 sm:py-14">
            <div className="mx-auto w-full max-w-sm">
              <Link
                href="/"
                className="text-coral mb-6 inline-flex items-center gap-2 text-xs font-bold tracking-[0.18em] uppercase"
              >
                <span aria-hidden="true">←</span>
                <span>{t('brand.name')}</span>
              </Link>
              {children}
            </div>
          </section>
        </div>
      </div>
    </div>
  );
}

function HeroPane() {
  const bullets = [
    t('auth.hero.bullet_free'),
    t('auth.hero.bullet_verified'),
    t('auth.hero.bullet_local'),
  ];

  return (
    <aside
      className="bg-terracotta text-primary-foreground relative hidden overflow-hidden p-12 lg:flex lg:flex-col lg:justify-between"
      aria-hidden="false"
    >
      <div className="relative z-10 flex items-center gap-3">
        <Image
          src="/brand/logo.png"
          alt="QBazaar"
          width={44}
          height={44}
          priority
          className="drop-shadow-sm"
        />
        <span className="font-display text-2xl">{t('brand.name')}</span>
      </div>

      <div className="relative z-10">
        <p className="text-xs font-bold tracking-[0.18em] uppercase opacity-80">
          {t('auth.hero.eyebrow')}
        </p>
        <h2 className="font-display mt-4 text-4xl leading-[1.05] tracking-tight md:text-5xl">
          {t('auth.hero.title_line1')}
          <br />
          <em className="opacity-90">{t('auth.hero.title_line2')}</em>
        </h2>
        <p className="mt-5 max-w-sm text-base leading-relaxed opacity-90">
          {t('auth.hero.subtitle')}
        </p>

        <ul className="mt-8 space-y-3 text-sm">
          {bullets.map((line) => (
            <li key={line} className="flex items-center gap-2.5">
              <span className="bg-primary-foreground/15 inline-flex size-6 items-center justify-center rounded-full">
                <CheckIcon className="size-3.5" aria-hidden="true" />
              </span>
              <span>{line}</span>
            </li>
          ))}
        </ul>
      </div>

      {/* Decorative oversized logo silhouette */}
      <div
        aria-hidden="true"
        className="pointer-events-none absolute -end-24 -bottom-24 opacity-15"
      >
        <Image
          src="/brand/logo.png"
          alt=""
          width={420}
          height={420}
          className="select-none"
        />
      </div>
    </aside>
  );
}
