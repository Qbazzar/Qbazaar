import { render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it } from 'vitest';

import { setClientLocale } from '@/lib/i18n/locale';

import { CatalogHeader, CatalogStats, todayStat } from './CatalogHeader';

describe('CatalogHeader', () => {
  beforeEach(() => setClientLocale('en'));

  it('renders the breadcrumb, the page heading and the actions', () => {
    render(
      <CatalogHeader
        title="Cars"
        breadcrumb={[{ label: 'Home', href: '/' }, { label: 'Vehicles', href: '/c/vehicles' }, { label: 'Cars' }]}
        actions={<button type="button">Save Search</button>}
      />,
    );

    expect(screen.getByRole('heading', { level: 1, name: 'Cars' })).toHaveClass('font-qb', 'qb-desktop:text-qb-h1');
    expect(screen.getByRole('navigation', { name: 'Breadcrumb' })).toHaveClass('hidden', 'qb-tablet:block');
    expect(screen.getByRole('link', { name: 'Vehicles' })).toHaveAttribute('href', '/c/vehicles');
    expect(screen.getByRole('button', { name: 'Save Search' }).parentElement).toHaveClass('hidden', 'qb-desktop:flex');
  });

  it('leaves the breadcrumb out when there is none', () => {
    render(<CatalogHeader title="Search results" />);

    expect(screen.queryByRole('navigation')).toBeNull();
  });
});

describe('CatalogStats', () => {
  it('separates the counters with decorative bullets and greens today', () => {
    const { container } = render(
      <CatalogStats items={[{ value: '8,429', label: 'Ads' }, ...todayStat(2000, 'Today', 'en')]} />,
    );

    expect(container).toHaveTextContent('8,429 Ads•+2,000 Today');
    expect(screen.getByText('•')).toHaveAttribute('aria-hidden', 'true');
    expect(screen.getByText('+2,000')).toHaveClass('text-qb-success');
  });

  it('renders nothing without counters', () => {
    const { container } = render(<CatalogStats items={[]} />);

    expect(container).toBeEmptyDOMElement();
  });
});

describe('todayStat', () => {
  it('is left out on a day without new ads', () => {
    expect(todayStat(0, 'Today', 'en')).toEqual([]);
  });

  it('uses Arabic digits in Arabic', () => {
    expect(todayStat(3840, 'اليوم', 'ar')).toEqual([{ value: '+3,840', label: 'اليوم', positive: true }]);
  });
});
