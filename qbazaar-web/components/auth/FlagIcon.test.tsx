import { render } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { FlagIcon } from './FlagIcon';

describe('FlagIcon', () => {
  it('draws each flag as a decorative svg, so no emoji is needed', () => {
    const { container } = render(
      <>
        <FlagIcon locale="en" />
        <FlagIcon locale="ar" />
      </>,
    );

    const flags = container.querySelectorAll('svg');
    expect(flags).toHaveLength(2);
    flags.forEach((flag) => expect(flag).toHaveAttribute('aria-hidden', 'true'));
    expect(container.textContent).toBe('');
  });
});
