import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { Badge, Chip } from './Badge';

describe('Badge', () => {
  it('defaults to the soft brand tone', () => {
    render(<Badge>Private Seller</Badge>);

    expect(screen.getByText('Private Seller')).toHaveClass('bg-qb-brand-soft', 'text-qb-brand-on-soft', 'rounded-qb-sm', 'font-qb');
  });

  it.each([
    ['success', 'text-qb-success'],
    ['danger', 'text-qb-danger'],
    ['info', 'text-qb-info'],
    ['solid', 'bg-qb-brand'],
  ] as const)('renders the %s tone', (tone, expected) => {
    render(<Badge tone={tone}>Status</Badge>);

    expect(screen.getByText('Status')).toHaveClass(expected);
  });

  it('sets status labels in the label face', () => {
    render(<Badge tone="success" font="label">Published</Badge>);

    expect(screen.getByText('Published')).toHaveClass('font-qb-label');
    expect(screen.getByText('Published')).not.toHaveClass('font-qb');
  });
});

describe('Chip', () => {
  it('renders a spec chip in both sizes', () => {
    render(
      <>
        <Chip>2019</Chip>
        <Chip size="sm">Diesel</Chip>
      </>,
    );

    expect(screen.getByText('2019')).toHaveClass('text-qb-label', 'bg-qb-fill');
    expect(screen.getByText('Diesel')).toHaveClass('text-qb-micro');
  });
});
