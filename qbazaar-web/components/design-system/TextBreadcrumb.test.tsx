import { render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it } from 'vitest';

import { setClientLocale } from '@/lib/i18n/locale';

import { TextBreadcrumb } from './TextBreadcrumb';

const items = [
  { label: 'Home', href: '/' },
  { label: 'Car & Vehicles', href: '/c/cars-vehicles' },
  { label: 'BMW M3 Competition..' },
];

describe('TextBreadcrumb', () => {
  beforeEach(() => setClientLocale('en'));

  it('is a labelled navigation landmark with an ordered trail', () => {
    render(<TextBreadcrumb items={items} />);
    const nav = screen.getByRole('navigation', { name: 'Breadcrumb' });

    expect(nav.querySelectorAll('ol > li')).toHaveLength(3);
    expect(screen.getAllByRole('link').map((link) => link.getAttribute('href'))).toEqual(['/', '/c/cars-vehicles']);
  });

  it('marks the last item as the current page', () => {
    render(<TextBreadcrumb items={items} />);

    expect(screen.getByText('BMW M3 Competition..').closest('[aria-current]')).toHaveAttribute('aria-current', 'page');
    expect(screen.queryByRole('link', { name: 'BMW M3 Competition..' })).toBeNull();
  });

  it('separates the items with a decorative ">" glyph that turns round in Arabic', () => {
    const { container } = render(<TextBreadcrumb items={items} />);
    const separators = container.querySelectorAll('[aria-hidden="true"]');

    expect(separators).toHaveLength(2);
    separators.forEach((separator) => {
      expect(separator).toHaveTextContent('>');
      expect(separator).toHaveClass('rtl:-scale-x-100');
    });
    expect(container.querySelector('svg')).toBeNull();
  });

  it('isolates every label, so a Latin title keeps its order in an Arabic trail', () => {
    setClientLocale('ar');
    const { container } = render(<TextBreadcrumb items={[{ label: 'الرئيسية', href: '/' }, { label: '24/7 plumber' }]} />);

    expect(screen.getByRole('navigation', { name: 'مسار التنقل' })).toBeInTheDocument();
    expect([...container.querySelectorAll('bdi')].map((bdi) => bdi.textContent)).toEqual(['الرئيسية', '24/7 plumber']);
  });
});
