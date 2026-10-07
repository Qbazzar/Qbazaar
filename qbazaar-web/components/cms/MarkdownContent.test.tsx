import { render } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { MarkdownContent } from './MarkdownContent';

describe('MarkdownContent', () => {
  it('drops scripts and event handlers but keeps formatting', () => {
    const { container } = render(
      <MarkdownContent html={'<p onclick="steal()"><strong>Hi</strong></p><script>steal()</script><a href="javascript:steal()">x</a>'} />,
    );

    expect(container.innerHTML).not.toContain('steal');
    expect(container.querySelector('strong')?.textContent).toBe('Hi');
  });

  it('sets the body in the design typography without the old prose class', () => {
    const { container } = render(<MarkdownContent html="<h2>Fees</h2><p>Posting is free.</p>" className="mt-4" />);
    const body = container.firstElementChild;

    expect(body).toHaveClass('font-qb', 'text-qb-body', 'text-qb-ink-body', 'mt-4');
    expect(body).not.toHaveClass('cms-prose');
    expect(body?.querySelector('h2')?.textContent).toBe('Fees');
  });
});
