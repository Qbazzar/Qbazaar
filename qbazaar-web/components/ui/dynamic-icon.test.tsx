import { act, render } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { DynamicIcon } from './dynamic-icon';

const svgOf = (container: HTMLElement) => container.querySelector('svg');

/** Lets the on-demand icon set finish loading inside act, as the browser would before painting it. */
const loadFullSet = () => act(async () => {
  await import('lucide-react');
});

describe('DynamicIcon', () => {
  it('draws the icons the API sends at once, aliases and kebab-case names included', () => {
    const { container, rerender } = render(<DynamicIcon name="Car" className="size-6" />);
    expect(svgOf(container)).toHaveClass('lucide-car', 'size-6');

    rerender(<DynamicIcon name="Home" />);
    expect(svgOf(container)).toHaveClass('lucide-house');

    rerender(<DynamicIcon name="shield-alert" />);
    expect(svgOf(container)).toHaveClass('lucide-shield-alert');
  });

  it('loads any other lucide icon on demand, showing the stand-in meanwhile', async () => {
    const { container } = render(<DynamicIcon name="zap" className="size-6" />);
    expect(svgOf(container)).toHaveClass('lucide-layers', 'size-6');

    await loadFullSet();
    expect(svgOf(container)).toHaveClass('lucide-zap', 'size-6');
  });

  it('keeps the stand-in for a missing or unknown name', async () => {
    const { container } = render(
      <>
        <DynamicIcon name={null} />
        <DynamicIcon name="NotAnIcon" />
      </>,
    );

    await loadFullSet();
    const [missing, unknown] = container.querySelectorAll('svg');
    expect(missing).toHaveClass('lucide-layers');
    expect(unknown).toHaveClass('lucide-layers');
  });
});
