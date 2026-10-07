import { describe, expect, it } from 'vitest';

import { lucideIconName } from './icon-name';

describe('lucideIconName', () => {
  it('turns the API kebab-case names into lucide component names', () => {
    expect(lucideIconName('shield-alert')).toBe('ShieldAlert');
    expect(lucideIconName('message-circle-2')).toBe('MessageCircle2');
  });

  it('keeps names that are already PascalCase and passes empty values through', () => {
    expect(lucideIconName('Car')).toBe('Car');
    expect(lucideIconName(null)).toBeNull();
    expect(lucideIconName('')).toBeNull();
  });
});
