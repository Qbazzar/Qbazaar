import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { Card } from './Card';

describe('Card', () => {
  it('is a bordered white panel with the default padding', () => {
    render(<Card data-testid="card">Filter</Card>);

    expect(screen.getByTestId('card')).toHaveClass('rounded-qb-xl', 'border-qb-line', 'bg-qb-surface', 'p-6');
  });

  it('supports the elevated, large and unpadded variants', () => {
    render(
      <Card data-testid="card" elevated large padding="none">
        Empty
      </Card>,
    );
    const card = screen.getByTestId('card');

    expect(card).toHaveClass('shadow-qb-card', 'rounded-qb-2xl');
    expect(card).not.toHaveClass('p-6', 'rounded-qb-xl');
  });
});
