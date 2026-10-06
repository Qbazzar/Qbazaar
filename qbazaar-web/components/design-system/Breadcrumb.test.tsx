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

    expect(screen.getByText('Cars')).toHaveAttribute('aria-current', 'page');
    expect(screen.queryByRole('link', { name: 'Cars' })).toBeNull();
  });

  it('uses mirrored, decorative separators', () => {
    const { container } = render(<Breadcrumb items={items} />);
    const separators = container.querySelectorAll('svg');

    expect(separators).toHaveLength(2);
    separators.forEach((svg) => {
      expect(svg).toHaveAttribute('aria-hidden', 'true');
      expect(svg).toHaveClass('rtl:-scale-x-100');
    });
  });

  it('labels the landmark in Arabic', () => {
    setClientLocale('ar');
    render(<Breadcrumb items={items} />);

    expect(screen.getByRole('navigation', { name: 'مسار التنقل' })).toBeInTheDocument();
  });
});
