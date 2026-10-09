import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { t } from '@/lib/i18n/messages';
import type { AdStatus } from '@/lib/api/types';

import { AdStatusBadge } from './AdStatusBadge';

describe('AdStatusBadge', () => {
  it.each<[AdStatus, string]>([
    ['draft', 'text-qb-danger'],
    ['active', 'text-qb-success'],
    ['pending', 'text-qb-info'],
    ['sold', 'text-qb-ink-secondary'],
    ['rejected', 'text-qb-danger'],
  ])('labels a %s ad in its status colour on the soft chip of the design', (status, colour) => {
    render(<AdStatusBadge status={status} />);
    const badge = screen.getByText(t(`ads.status.${status}`));

    expect(badge).toHaveClass(colour, 'font-qb-label', 'text-qb-label');
    expect(badge).not.toHaveClass('border');
  });
});
