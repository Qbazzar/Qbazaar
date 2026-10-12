import { render, screen } from '@testing-library/react';
import { Heart as LucideHeart } from 'lucide-react';
import { describe, expect, it } from 'vitest';

import { Icon } from './Icon';
import { ArrowRightIcon, HeartIcon, StarFilledIcon } from './icons';

describe('Icon', () => {
  it('is decorative unless it has a label', () => {
    const { container } = render(<Icon icon={HeartIcon} />);
    const svg = container.querySelector('svg');

    expect(svg).toHaveAttribute('aria-hidden', 'true');
    expect(svg).toHaveAttribute('width', '20');
  });

  it('becomes an image with an accessible name when labelled', () => {
    render(<Icon icon={HeartIcon} size="lg" label="Saved" />);

    expect(screen.getByRole('img', { name: 'Saved' })).toHaveAttribute('width', '24');
  });

  it('mirrors directional icons in RTL', () => {
    const { container } = render(<Icon icon={ArrowRightIcon} flipInRtl />);

    expect(container.querySelector('svg')).toHaveClass('rtl:-scale-x-100');
  });

  it('draws a reference glyph with its own stroke, not a house 1.5', () => {
    const { container } = render(<Icon icon={HeartIcon} />);
    const svg = container.querySelector('svg');

    expect(svg).toHaveAttribute('stroke-width', '1.6');
    expect(svg).toHaveAttribute('viewBox', '0 0 24 24');
  });

  it('takes the exact px size of the usage', () => {
    const { container } = render(<Icon icon={HeartIcon} size={23} />);
    const svg = container.querySelector('svg');

    expect(svg).toHaveAttribute('width', '23');
    expect(svg).toHaveAttribute('height', '23');
  });

  it('lets a usage pick another stroke the design uses for that glyph', () => {
    const { container } = render(<Icon icon={HeartIcon} strokeWidth={1.5} />);

    expect(container.querySelector('svg')).toHaveAttribute('stroke-width', '1.5');
  });

  it('keeps the old 1.5 stroke on lucide icons until the codemod moves them', () => {
    const { container } = render(<Icon icon={LucideHeart} />);

    expect(container.querySelector('svg')).toHaveAttribute('stroke-width', '1.5');
  });

  it('adds no stroke to a filled glyph', () => {
    const { container } = render(<Icon icon={StarFilledIcon} />);
    const svg = container.querySelector('svg');

    expect(svg).toHaveAttribute('fill', 'currentColor');
    expect(svg).not.toHaveAttribute('stroke-width');
  });
});
