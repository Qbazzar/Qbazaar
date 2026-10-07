import { render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it } from 'vitest';

import { setClientLocale } from '@/lib/i18n/locale';

import { PageShell } from './PageShell';

describe('PageShell', () => {
  beforeEach(() => setClientLocale('en'));

  it('frames the page with one h1, the summary line and the content', () => {
    render(
      <PageShell title="Help center" meta="5 topics · 15 articles" actions={<button type="button">Save</button>}>
        <p>Body</p>
      </PageShell>,
    );
    const main = screen.getByRole('main');

    expect(main).toHaveClass('bg-qb-page', 'font-qb');
    expect(screen.getByRole('heading', { level: 1, name: 'Help center' })).toHaveClass('font-qb', 'qb-desktop:text-qb-h1');
    expect(screen.getByText('5 topics · 15 articles')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Save' })).toBeInTheDocument();
    expect(screen.getByText('Body')).toBeInTheDocument();
  });

  it('shows the breadcrumb from tablet up, like the reference', () => {
    render(<PageShell title="Terms" breadcrumb={[{ label: 'Home', href: '/' }, { label: 'Terms' }]} />);
    const nav = screen.getByRole('navigation', { name: 'Breadcrumb', hidden: true });

    expect(nav).toHaveClass('hidden', 'qb-tablet:block');
    expect(screen.getByRole('link', { name: 'Home', hidden: true })).toHaveAttribute('href', '/');
  });

  it('leaves out the breadcrumb and summary line when there are none', () => {
    const { container } = render(<PageShell title="Terms" />);

    expect(container.querySelector('nav')).toBeNull();
    expect(container.querySelector('header p')).toBeNull();
  });
});
