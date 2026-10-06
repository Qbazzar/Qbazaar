import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { SectionHeader } from './SectionHeader';

describe('SectionHeader', () => {
  it('renders the title as a section heading with its subtitle', () => {
    render(<SectionHeader title="Browse Categories" subtitle="Explore top items in your area" id="browse" />);
    const heading = screen.getByRole('heading', { level: 2, name: 'Browse Categories' });

    expect(heading).toHaveAttribute('id', 'browse');
    expect(heading).toHaveClass('text-qb-h4', 'qb-desktop:text-qb-h2', 'tracking-normal');
    expect(screen.getByText('Explore top items in your area')).toBeInTheDocument();
  });

  it('adds the view-all link with an arrow that flips in RTL', () => {
    render(<SectionHeader title="Best Selling" headingLevel="h3" action={{ href: '/ads', label: 'View All' }} />);
    const link = screen.getByRole('link', { name: 'View All' });

    expect(screen.getByRole('heading', { level: 3 })).toBeInTheDocument();
    expect(link).toHaveAttribute('href', '/ads');
    expect(link.querySelector('svg')).toHaveClass('rtl:-scale-x-100');
  });
});
