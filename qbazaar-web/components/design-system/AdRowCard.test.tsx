import { render, screen, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { AdRowCard } from './AdRowCard';

const ad = {
  href: '/ads/7',
  title: 'BMW 320d Touring M-Sport',
  media: <img src="/car.jpg" alt="" />,
  price: 'QAR 287,000',
  description: 'Luxury diesel touring car',
  tags: ['2019', '68,000 km'],
  location: 'Doha',
  postedAt: '30 min ago',
};

describe('AdRowCard', () => {
  it('is one link named by the title, stretched over the row', () => {
    render(<AdRowCard {...ad} />);
    const link = screen.getByRole('link', { name: ad.title });

    expect(screen.getAllByRole('link')).toHaveLength(1);
    expect(link).toHaveAttribute('href', '/ads/7');
    expect(link).toHaveClass('after:absolute', 'after:inset-0');
    expect(screen.getByRole('heading', { level: 3, name: ad.title })).toBeInTheDocument();
  });

  it('shows the price on the photo on phones and beside the title from 601 px', () => {
    render(<AdRowCard {...ad} />);
    const [onPhoto, beside] = screen.getAllByText(ad.price);

    expect(onPhoto).toHaveClass('qb-tablet:hidden');
    expect(beside).toHaveClass('hidden', 'qb-tablet:block');
  });

  it('keeps the favourite button outside the link', () => {
    render(<AdRowCard {...ad} favorite={<button type="button">Save</button>} />);
    const favorite = screen.getByRole('button', { name: 'Save' });

    expect(favorite.closest('a')).toBeNull();
    expect(favorite.parentElement).toHaveClass('z-10');
  });

  it('lists the description, chips and the location line', () => {
    render(<AdRowCard {...ad} headingLevel="h2" />);

    expect(screen.getByText(ad.description)).toBeInTheDocument();
    expect(within(screen.getByRole('list')).getAllByRole('listitem').map((item) => item.textContent)).toEqual(ad.tags);
    expect(screen.getByText('Doha • 30 min ago')).toBeInTheDocument();
    expect(screen.getByRole('heading', { level: 2 })).toBeInTheDocument();
  });

  it('leaves out what it was not given', () => {
    render(<AdRowCard href="/ads/8" title="Bike" media={null} />);

    expect(screen.queryByRole('list')).toBeNull();
    expect(screen.queryByText('•')).toBeNull();
  });
});
