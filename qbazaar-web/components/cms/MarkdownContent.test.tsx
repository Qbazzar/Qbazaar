import { render } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { MarkdownContent } from './MarkdownContent';

describe('MarkdownContent', () => {
  it('drops scripts and event handlers but keeps formatting', () => {
    const { container } = render(
      <MarkdownContent html={'<p onclick="steal()"><strong>Hi</strong></p><script>steal()</script><a href="javascript:steal()">x</a>'} />,
    );

    expect(container.innerHTML).not.toContain('steal');
    expect(container.querySelector('.cms-prose strong')?.textContent).toBe('Hi');
  });
});
