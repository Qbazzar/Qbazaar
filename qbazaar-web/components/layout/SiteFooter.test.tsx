import { readdirSync } from 'node:fs';
import path from 'node:path';

import { render, screen, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { setClientLocale } from '@/lib/i18n/locale';

import { SiteFooter } from './SiteFooter';
import { SiteFooterGate } from './SiteFooterGate';

const pathname = vi.hoisted(() => ({ current: '/' }));
vi.mock('next/navigation', () => ({ usePathname: () => pathname.current }));

const APP_DIR = path.resolve(__dirname, '../../app');
const PAGE_FILE = /^page\.(tsx|ts|jsx|js)$/;

/** The URL segments of every page under app/, route groups removed and dynamic segments kept as [x]. */
function appRoutes(dir = APP_DIR, segments: string[] = []): string[][] {
  return readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
    if (entry.isFile()) return PAGE_FILE.test(entry.name) ? [segments] : [];
    const isGroup = entry.name.startsWith('(') && entry.name.endsWith(')');
    return appRoutes(path.join(dir, entry.name), isGroup ? segments : [...segments, entry.name]);
  });
}

function routeExists(href: string, routes: string[][]) {
  const parts = href.split(/[?#]/)[0].split('/').filter(Boolean);
  return routes.some((route) => route.length === parts.length && route.every((seg, i) => seg.startsWith('[') || seg === parts[i]));
}

describe('SiteFooter', () => {
  beforeEach(() => setClientLocale('en'));

  it('points every link at a page of the app', () => {
    render(<SiteFooter />);
    const routes = appRoutes();
    const hrefs = within(screen.getByRole('contentinfo')).getAllByRole('link').map((link) => link.getAttribute('href') ?? '');

    expect(hrefs.length).toBeGreaterThan(0);
    expect(hrefs.filter((href) => !routeExists(href, routes))).toEqual([]);
  });

  it('renders every column as a heading list and as a collapsible row', () => {
    const { container } = render(<SiteFooter />);

    expect(screen.getByRole('navigation', { name: 'Site links' })).toBeInTheDocument();
    expect(screen.getAllByRole('heading', { level: 2 }).map((h) => h.textContent)).toEqual([
      'Classifieds',
      'Information',
      'Account',
      'Discover',
    ]);
    expect([...container.querySelectorAll('details > summary')].map((s) => s.textContent)).toEqual([
      'Classifieds',
      'Information',
      'Account',
      'Discover',
    ]);
  });

  it('links only real routes and keeps the social icons decorative until they have URLs', () => {
    render(<SiteFooter />);
    const links = within(screen.getByRole('contentinfo')).getAllByRole('link');

    expect(screen.getAllByRole('link', { name: 'Privacy Policy' })[0]).toHaveAttribute('href', '/p/privacy');
    expect(screen.getAllByRole('link', { name: 'Companies' })[0]).toHaveAttribute('href', '/companies');
    expect(links.every((link) => link.getAttribute('href')?.startsWith('/'))).toBe(true);
    expect(screen.queryByRole('link', { name: 'Instagram' })).toBeNull();
    expect(screen.getByText('Q BAZAAR')).toHaveClass('font-qb-brand');
  });
});

describe('SiteFooterGate', () => {
  it.each([
    ['/', true],
    ['/ads/1', true],
    ['/post-ad', true],
    ['/login', false],
    ['/verify-email', false],
  ])('on %s shows the footer: %s', (path, shown) => {
    pathname.current = path;
    render(
      <SiteFooterGate>
        <footer>footer</footer>
      </SiteFooterGate>,
    );

    expect(screen.queryByText('footer') !== null).toBe(shown);
  });
});
