import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { CompanyCard, logoToneFor } from './CompanyCard';

const company = {
  href: '/u/01abc',
  name: 'Doha Auto Gallery',
  toneKey: '01abc',
  meta: '29 followers',
  count: '8 Ads',
};

describe('CompanyCard', () => {
  it('is one link named by the company, stretched over the card', () => {
    render(<CompanyCard {...company} />);
    const link = screen.getByRole('link', { name: company.name });

    expect(screen.getAllByRole('link')).toHaveLength(1);
    expect(link).toHaveAttribute('href', '/u/01abc');
    expect(link).toHaveClass('after:absolute', 'after:inset-0');
    expect(screen.getByRole('heading', { level: 3, name: company.name })).toBeInTheDocument();
  });

  it('shows the meta line and the ads count', () => {
    render(<CompanyCard {...company} />);

    expect(screen.getByText('29 followers')).toBeInTheDocument();
    expect(screen.getByText('8 Ads')).toBeInTheDocument();
  });

  it('puts the initials on a tinted tile when there is no logo', () => {
    render(<CompanyCard {...company} />);
    const tile = screen.getByText('DG');

    expect(tile).toHaveAttribute('aria-hidden', 'true');
    expect(tile.className).toContain(logoToneFor(company.toneKey));
  });

  it('shows the logo instead of the initials when there is one', () => {
    const { container } = render(<CompanyCard {...company} logoUrl="https://cdn.example.qa/logo.jpg" />);

    expect(container.querySelector('img')).toHaveAttribute('alt', '');
    expect(screen.queryByText('DG')).toBeNull();
  });
});

describe('logoToneFor', () => {
  it('gives the same company the same colour every time', () => {
    expect(logoToneFor('01m48b0qws8c98c9zc7xxnsszm')).toBe(logoToneFor('01m48b0qws8c98c9zc7xxnsszm'));
  });

  it('spreads companies over the tones', () => {
    const tones = new Set(['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h'].map(logoToneFor));
    expect(tones.size).toBeGreaterThan(1);
  });
});
