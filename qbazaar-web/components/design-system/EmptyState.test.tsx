import { render, screen } from '@testing-library/react';
import { Heart } from 'lucide-react';
import { describe, expect, it } from 'vitest';

import { Button } from './Button';
import { EmptyState } from './EmptyState';
import { Icon } from './Icon';

describe('EmptyState', () => {
  it('shows the heading, description and call to action', () => {
    render(
      <EmptyState
        icon={<Icon icon={Heart} size="lg" />}
        title="Found something interesting?"
        description="Click the heart on a listing to save it here."
        action={<Button size="sm">Browse Ads</Button>}
      />,
    );

    expect(screen.getByRole('heading', { level: 2, name: 'Found something interesting?' })).toHaveClass('font-qb');
    expect(screen.getByText('Click the heart on a listing to save it here.')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Browse Ads' })).toBeInTheDocument();
  });

  it('keeps the icon tile out of the accessibility tree', () => {
    const { container } = render(<EmptyState icon={<Icon icon={Heart} />} title="No saved searches" headingLevel="h3" />);
    const tile = container.querySelector('svg')?.parentElement;

    expect(tile).toHaveAttribute('aria-hidden', 'true');
    expect(tile).toHaveClass('size-[92px]', 'rounded-qb-2xl');
    expect(screen.getByRole('heading', { level: 3 })).toBeInTheDocument();
  });
});
