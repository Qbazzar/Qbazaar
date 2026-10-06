import { render, screen } from '@testing-library/react';
import { ArrowRight, Heart } from 'lucide-react';
import { describe, expect, it } from 'vitest';

import { Icon } from './Icon';

describe('Icon', () => {
  it('is decorative unless it has a label', () => {
    const { container } = render(<Icon icon={Heart} />);
    const svg = container.querySelector('svg');

    expect(svg).toHaveAttribute('aria-hidden', 'true');
    expect(svg).toHaveAttribute('width', '20');
  });

  it('becomes an image with an accessible name when labelled', () => {
    render(<Icon icon={Heart} size="lg" label="Saved" />);

    expect(screen.getByRole('img', { name: 'Saved' })).toHaveAttribute('width', '24');
  });

  it('mirrors directional icons in RTL', () => {
    const { container } = render(<Icon icon={ArrowRight} flipInRtl />);

    expect(container.querySelector('svg')).toHaveClass('rtl:-scale-x-100');
  });
});
