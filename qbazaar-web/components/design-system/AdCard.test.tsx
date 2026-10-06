import { render, screen, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { AdCard } from './AdCard';

const ad = {
  href: '/ads/42',
  title: 'BMW M3 Competition xDrive',
  media: <img src="/car.jpg" alt="" />,
  price: 'QAR 285,000',
  tags: ['2022', '48,000 km', 'Benzin'],
  location: 'Doha',
  postedAt: '30 min ago',
};

describe('AdCard', () => {
  it('is one link named by the title, stretched over the card', () => {
    render(<AdCard {...ad} />);
    const link = screen.getByRole('link', { name: ad.title });

    expect(screen.getAllByRole('link')).toHaveLength(1);
    expect(link).toHaveAttribute('href', '/ads/42');
    expect(link).toHaveClass('after:absolute', 'after:inset-0');
    expect(screen.getByRole('heading', { level: 3, name: ad.title })).toBeInTheDocument();
  });

  it('keeps the favourite button outside the link', () => {
    render(<AdCard {...ad} favorite={<button type="button">Save</button>} />);
    const favorite = screen.getByRole('button', { name: 'Save' });

    expect(favorite.closest('a')).toBeNull();
    expect(favorite.parentElement).toHaveClass('z-10', 'end-3');
  });

  it('puts the price on the photo in the grid layout', () => {
    render(<AdCard {...ad} badge="Top Ad" />);

    expect(screen.getByText(ad.price)).toHaveClass('bg-qb-brand', 'start-3');
    expect(screen.queryByText('Top Ad')).toBeNull();
  });

  it('lists the spec chips and the location line', () => {
    render(<AdCard {...ad} />);

    const chips = within(screen.getByRole('list')).getAllByRole('listitem');
    expect(chips.map((chip) => chip.textContent)).toEqual(ad.tags);
    expect(screen.getByText('Doha • 30 min ago')).toBeInTheDocument();
  });

  it('shows the badge on the photo and the price and description in the list layout', () => {
    render(<AdCard {...ad} layout="list" badge="Top Ad" description="Full service history" />);

    expect(screen.getByText('Top Ad')).toHaveClass('bg-qb-brand');
    expect(screen.getByText(ad.price)).toHaveClass('text-qb-h4');
    expect(screen.getByText('Full service history')).toBeInTheDocument();
  });
});
