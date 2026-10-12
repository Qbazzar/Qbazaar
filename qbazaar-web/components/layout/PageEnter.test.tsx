import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

const pathname = vi.hoisted(() => ({ current: '/' }));
vi.mock('next/navigation', () => ({ usePathname: () => pathname.current }));

import { PageEnter } from './PageEnter';

describe('PageEnter', () => {
  it('fades a screen in as the reference qbFade does, unless the visitor asks for less motion', () => {
    pathname.current = '/categories';
    render(
      <PageEnter>
        <p>Categories</p>
      </PageEnter>,
    );

    expect(screen.getByText('Categories').parentElement).toHaveClass('animate-qb-page-enter', 'motion-reduce:animate-none');
  });

  it('leaves the auth pages static, as the reference auth flow is', () => {
    pathname.current = '/login';
    const { container } = render(
      <PageEnter>
        <p>Login</p>
      </PageEnter>,
    );

    expect(container.firstElementChild).toBe(screen.getByText('Login'));
  });
});
