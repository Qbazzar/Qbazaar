import { render, screen } from '@testing-library/react';
import { LifeBuoy } from 'lucide-react';
import { describe, expect, it } from 'vitest';

import { Icon } from './Icon';
import { LinkTile } from './LinkTile';

describe('LinkTile', () => {
  it('is one link named by its title and line', () => {
    render(<LinkTile href="/help/c/buying" icon={<Icon icon={LifeBuoy} />} title="Buying" description="3 articles" />);
    const link = screen.getByRole('link', { name: 'Buying 3 articles' });

    expect(link).toHaveAttribute('href', '/help/c/buying');
    expect(link).toHaveClass('rounded-qb-xl', 'shadow-qb-card', 'hover:shadow-qb-hover', 'qb-tablet:rounded-qb-2xl');
  });

  it('keeps the icon square decorative', () => {
    const { container } = render(<LinkTile href="/support/new" icon={<Icon icon={LifeBuoy} />} title="New ticket" />);
    const square = container.querySelector('svg')?.parentElement;

    expect(square).toHaveAttribute('aria-hidden', 'true');
    expect(square).toHaveClass('bg-qb-brand-soft', 'text-qb-brand');
    expect(screen.getByRole('link', { name: 'New ticket' })).toBeInTheDocument();
  });
});
