import { render, screen, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { setClientLocale } from '@/lib/i18n/locale';

import { SiteFooter } from './SiteFooter';
import { SiteFooterGate } from './SiteFooterGate';

const pathname = vi.hoisted(() => ({ current: '/' }));
vi.mock('next/navigation', () => ({ usePathname: () => pathname.current }));

describe('SiteFooter', () => {
  beforeEach(() => setClientLocale('en'));

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
