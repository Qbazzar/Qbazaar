import { render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it } from 'vitest';

import { setClientLocale } from '@/lib/i18n/locale';

import { Breadcrumb } from './Breadcrumb';

const items = [
  { label: 'Home', href: '/' },
  { label: 'Car & Vehicles', href: '/c/cars-vehicles' },
  { label: 'Cars' },
];

describe('Breadcrumb', () => {
  beforeEach(() => setClientLocale('en'));

  it('is a labelled navigation landmark with an ordered trail', () => {
    render(<Breadcrumb items={items} />);
    const nav = screen.getByRole('navigation', { name: 'Breadcrumb' });

    expect(nav.querySelectorAll('ol > li')).toHaveLength(3);
    expect(screen.getAllByRole('link').map((link) => link.getAttribute('href'))).toEqual(['/', '/c/cars-vehicles']);
  });

  it('marks the last item as the current page', () => {
    render(<Breadcrumb items={items} />);

    expect(screen.getByText('Cars').closest('[aria-current]')).toHaveAttribute('aria-current', 'page');
    expect(screen.queryByRole('link', { name: 'Cars' })).toBeNull();
  });

  it('turns the links orange under the pointer, as .qb-nav:hover does', () => {
    render(<Breadcrumb items={items} />);

    expect(screen.getByRole('link', { name: 'Home' })).toHaveClass('text-qb-breadcrumb', 'hover:text-qb-brand');
  });

  it('separates the items with a decorative ">" glyph that turns round in Arabic', () => {
    const { container } = render(<Breadcrumb items={items} />);
    const separators = container.querySelectorAll('[aria-hidden="true"]');

    expect(separators).toHaveLength(2);
    separators.forEach((separator) => {
      expect(separator).toHaveTextContent('>');
      expect(separator).toHaveClass('text-qb-breadcrumb', 'rtl:-scale-x-100');
    });
    expect(container.querySelector('svg')).toBeNull();
  });

  it('keeps the trail on one scrolling line on phones only', () => {
    const { container } = render(<Breadcrumb items={items} />);

    expect(container.querySelector('ol')).toHaveClass('flex-wrap', 'max-qb-tablet:flex-nowrap', 'max-qb-tablet:overflow-x-auto');
  });

  it('isolates every label, so a Latin label keeps its order in an Arabic trail', () => {
    setClientLocale('ar');
    const { container } = render(<Breadcrumb items={[{ label: 'الرئيسية', href: '/' }, { label: '24/7 plumber' }]} />);

    expect(screen.getByRole('navigation', { name: 'مسار التنقل' })).toBeInTheDocument();
    expect([...container.querySelectorAll('bdi')].map((bdi) => bdi.textContent)).toEqual(['الرئيسية', '24/7 plumber']);
  });
});
