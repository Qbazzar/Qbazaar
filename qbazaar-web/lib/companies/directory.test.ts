import { describe, expect, it } from 'vitest';

import { companiesApiPath, companiesHref, readCompaniesParams } from './directory';

describe('readCompaniesParams', () => {
  it('reads the query and the page', () => {
    expect(readCompaniesParams({ q: '  Auto  ', page: '3' })).toEqual({ query: 'Auto', page: 3 });
  });

  it('falls back to page 1 for a missing or malformed page', () => {
    expect(readCompaniesParams({})).toEqual({ query: '', page: 1 });
    expect(readCompaniesParams({ page: '0' }).page).toBe(1);
    expect(readCompaniesParams({ page: '-2' }).page).toBe(1);
    expect(readCompaniesParams({ page: '2.5' }).page).toBe(1);
    expect(readCompaniesParams({ page: 'two' }).page).toBe(1);
  });

  it('takes the first of repeated params and caps the query at 100 characters', () => {
    expect(readCompaniesParams({ q: ['first', 'second'], page: ['2', '9'] })).toEqual({ query: 'first', page: 2 });
    expect(readCompaniesParams({ q: 'x'.repeat(150) }).query).toHaveLength(100);
  });
});

describe('companies URLs', () => {
  it('leaves page 1 and an empty query out of the URL', () => {
    expect(companiesHref({ query: '', page: 1 })).toBe('/companies');
    expect(companiesApiPath({ query: '', page: 1 })).toBe('/api/v1/companies');
  });

  it('encodes the query', () => {
    expect(companiesHref({ query: 'Doha & Co', page: 2 })).toBe('/companies?q=Doha+%26+Co&page=2');
    expect(companiesApiPath({ query: 'لؤلؤة', page: 1 })).toBe(`/api/v1/companies?q=${encodeURIComponent('لؤلؤة')}`);
  });
});
