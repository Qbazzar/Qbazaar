import { describe, expect, it } from 'vitest';

import { focusRing } from './focus-ring';

describe('focusRing', () => {
  it('draws a solid outline on keyboard focus despite outline-none', () => {
    expect(focusRing.split(' ')).toEqual(
      expect.arrayContaining(['outline-none', 'focus-visible:outline-solid', 'focus-visible:outline-2', 'focus-visible:outline-qb-brand-active']),
    );
  });

  it('draws no outline at rest, so forced-colors mode shows only the focused element', () => {
    expect(focusRing.split(' ')).not.toContain('outline-hidden');
  });
});
