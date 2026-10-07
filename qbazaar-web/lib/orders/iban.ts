/**
 * Client-side IBAN check (shape, registered length, ISO 13616 mod-97), so a
 * typo is caught before the request. The server runs the same check and
 * stays the authority; spaces and dashes are ignored as on the API.
 */

/** Registered IBAN lengths for the countries Qatar residents bank with most. Others pass on shape + checksum. */
const COUNTRY_LENGTHS: Record<string, number> = {
  QA: 29,
  AE: 23,
  SA: 24,
  KW: 30,
  BH: 22,
  OM: 23,
  JO: 30,
  EG: 29,
  LB: 28,
  GB: 22,
  DE: 22,
  FR: 27,
  TR: 26,
};

export function normalizeIban(input: string): string {
  return input.replace(/[\s-]/g, '').toUpperCase();
}

function mod97(digits: string): number {
  let remainder = 0;
  for (const char of digits) {
    remainder = (remainder * 10 + Number(char)) % 97;
  }
  return remainder;
}

export function isValidIban(input: string): boolean {
  const iban = normalizeIban(input);
  if (!/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/.test(iban)) return false;

  const expectedLength = COUNTRY_LENGTHS[iban.slice(0, 2)];
  if (expectedLength !== undefined && iban.length !== expectedLength) return false;

  const rearranged = iban.slice(4) + iban.slice(0, 4);
  const digits = Array.from(rearranged)
    .map((char) => (/[A-Z]/.test(char) ? String(char.charCodeAt(0) - 55) : char))
    .join('');
  return mod97(digits) === 1;
}

/** Groups of four for display while typing: "QA58 DOHB 0000 …". */
export function formatIbanForDisplay(input: string): string {
  return normalizeIban(input).replace(/(.{4})/g, '$1 ').trim();
}
