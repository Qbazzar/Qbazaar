import { render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it } from 'vitest';

import { setClientLocale } from '@/lib/i18n/locale';

import { Pagination, paginationRange } from './Pagination';

const getHref = (page: number) => `/ads?page=${page}`;

describe('paginationRange', () => {
  it.each([
    [1, 10, [1, 2, 3, 4, 5]],
    [5, 10, [3, 4, 5, 6, 7]],
    [10, 10, [6, 7, 8, 9, 10]],
    [2, 3, [1, 2, 3]],
  ])('page %i of %i', (page, total, expected) => {
    expect(paginationRange(page, total)).toEqual(expected);
  });
});

describe('Pagination', () => {
  beforeEach(() => setClientLocale('en'));

  it('renders nothing for a single page', () => {
    const { container } = render(<Pagination page={1} totalPages={1} getHref={getHref} />);

    expect(container).toBeEmptyDOMElement();
  });

  it('links every page and marks the current one', () => {
    render(<Pagination page={3} totalPages={8} getHref={getHref} />);
    const current = screen.getByRole('link', { name: 'Page 3' });

    expect(screen.getByRole('navigation', { name: 'Pagination' })).toBeInTheDocument();
    expect(current).toHaveAttribute('aria-current', 'page');
    expect(current).toHaveClass('bg-qb-brand');
    expect(screen.getByRole('link', { name: 'Next page' })).toHaveAttribute('href', '/ads?page=4');
    expect(screen.getByRole('link', { name: 'Last page' })).toHaveAttribute('href', '/ads?page=8');
  });

  it('hides the steps that lead nowhere on the first page', () => {
    render(<Pagination page={1} totalPages={4} getHref={getHref} />);

    expect(screen.queryByRole('link', { name: 'First page' })).toBeNull();
    expect(screen.queryByRole('link', { name: 'Previous page' })).toBeNull();
    expect(screen.getByRole('link', { name: 'Next page' })).toBeInTheDocument();
  });

  it('clamps an out-of-range page', () => {
    render(<Pagination page={99} totalPages={4} getHref={getHref} />);

    expect(screen.getByRole('link', { name: 'Page 4' })).toHaveAttribute('aria-current', 'page');
  });
});
