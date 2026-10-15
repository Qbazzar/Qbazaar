import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { WalletIcon } from '@/components/design-system/icons';

import { StatTile } from './StatTile';

describe('StatTile', () => {
  it('shows the label, value and hint and hides the icon from assistive tech', () => {
    const { container } = render(
      <StatTile label="Wallet balance" value="QAR 450.00" icon={<WalletIcon />} tone="info" hint="Updated now" />,
    );

    expect(screen.getByText('Wallet balance')).toHaveClass('text-qb-breadcrumb', 'qb-tablet:text-qb-ink-meta');
    expect(screen.getByText('QAR 450.00')).toHaveClass('font-semibold');
    expect(screen.getByText('Updated now')).toBeInTheDocument();
    const iconTile = container.querySelector('[aria-hidden="true"]');
    expect(iconTile).toHaveClass('bg-qb-info-soft', 'text-qb-info');
  });
});
