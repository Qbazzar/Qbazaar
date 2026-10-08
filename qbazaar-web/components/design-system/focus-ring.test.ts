import { describe, expect, it } from 'vitest';

import { focusRing } from './focus-ring';

describe('focusRing', () => {
  it('draws a solid outline on keyboard focus despite outline-hidden', () => {
    expect(focusRing.split(' ')).toEqual(
      expect.arrayContaining(['outline-hidden', 'focus-visible:outline-solid', 'focus-visible:outline-2', 'focus-visible:outline-qb-brand-active']),
    );
  });
});
