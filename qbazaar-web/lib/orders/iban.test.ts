import { describe, expect, it } from 'vitest';

import { formatIbanForDisplay, isValidIban, normalizeIban } from './iban';

describe('IBAN helpers', () => {
  it('accepts valid IBANs with or without spacing', () => {
    expect(isValidIban('QA58 DOHB 0000 1234 5678 90AB CDEF G')).toBe(true);
    expect(isValidIban('qa58dohb00001234567890abcdefg')).toBe(true);
    expect(isValidIban('GB82-WEST-1234-5698-7654-32')).toBe(true);
    expect(isValidIban('DE89370400440532013000')).toBe(true);
  });

  it('rejects a wrong checksum, a wrong country length or a bad shape', () => {
    expect(isValidIban('QA59 DOHB 0000 1234 5678 90AB CDEF G')).toBe(false);
    expect(isValidIban('QA58 DOHB 0000 1234 5678 90AB CDEF')).toBe(false);
    expect(isValidIban('12QA DOHB')).toBe(false);
    expect(isValidIban('')).toBe(false);
  });

  it('normalises and groups for display', () => {
    expect(normalizeIban(' qa58-dohb 0000 ')).toBe('QA58DOHB0000');
    expect(formatIbanForDisplay('qa58dohb00001234567890abcdefg')).toBe('QA58 DOHB 0000 1234 5678 90AB CDEF G');
  });
});
