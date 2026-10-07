import { render, screen } from '@testing-library/react';
import { SearchX } from 'lucide-react';
import { describe, expect, it } from 'vitest';

import { Button } from './Button';
import { Icon } from './Icon';
import { StateIcon, StatePanel } from './StatePanel';

describe('StatePanel', () => {
  it('shows the empty state inside the large elevated panel', () => {
    const { container } = render(
      <StatePanel
        icon={<Icon icon={SearchX} size="lg" />}
        title="Search Not Found"
        description="We couldn't find anything matching your search."
        action={<Button size="sm">Browse</Button>}
      />,
    );

    expect(container.firstElementChild).toHaveClass('rounded-qb-2xl', 'shadow-qb-card', 'qb-desktop:min-h-[415px]');
    expect(screen.getByRole('heading', { level: 2, name: 'Search Not Found' })).toBeInTheDocument();
    expect(screen.getByText("We couldn't find anything matching your search.")).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Browse' })).toBeInTheDocument();
  });

  it('sets the heading in the design face rather than the base heading font', () => {
    render(<StatePanel icon={<Icon icon={SearchX} />} title="Nothing here" headingLevel="h3" />);

    expect(screen.getByRole('heading', { level: 3 }).parentElement).toHaveClass('[&_:is(h2,h3)]:font-qb');
  });
});

describe('StateIcon', () => {
  it('draws the 36 / 40 px glyph in the requested tone, hidden from assistive tech', () => {
    const { container, rerender } = render(<StateIcon icon={SearchX} tone="muted" />);
    const svg = container.querySelector('svg');

    expect(svg).toHaveClass('size-9', 'qb-tablet:size-10', 'text-qb-ink-muted');
    expect(svg).toHaveAttribute('aria-hidden', 'true');

    rerender(<StateIcon icon={SearchX} />);
    expect(container.querySelector('svg')).toHaveClass('text-qb-brand');
  });
});
