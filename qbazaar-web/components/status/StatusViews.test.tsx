import { fireEvent, render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { setClientLocale } from '@/lib/i18n/locale';

import { ErrorView } from './ErrorView';
import { NotFoundView } from './NotFoundView';

describe('NotFoundView', () => {
  beforeEach(() => setClientLocale('en'));

  it('explains the missing page and links home and to the help center', () => {
    render(<NotFoundView />);

    expect(screen.getByRole('heading', { level: 1, name: 'Page not found' })).toBeInTheDocument();
    expect(screen.getByRole('heading', { level: 2, name: "We couldn't find this page" })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Back to home' })).toHaveAttribute('href', '/');
    expect(screen.getByRole('link', { name: 'Help center' })).toHaveAttribute('href', '/help');
  });

  it('takes a page-specific message, trail and actions', () => {
    render(
      <NotFoundView
        trail={[{ label: 'Help center', href: '/help' }]}
        heading="We couldn't find this topic"
        actions={<a href="/help">Browse help topics</a>}
      />,
    );
    const crumbs = screen.getByRole('navigation', { name: 'Breadcrumb' });

    expect(crumbs).toHaveTextContent('HomeHelp centerPage not found');
    expect(screen.getByRole('heading', { level: 2, name: "We couldn't find this topic" })).toBeInTheDocument();
    expect(screen.queryByRole('link', { name: 'Back to home' })).toBeNull();
  });

  it('speaks Arabic in an Arabic session', () => {
    setClientLocale('ar');
    render(<NotFoundView />);

    expect(screen.getByRole('heading', { level: 1, name: 'الصفحة غير موجودة' })).toBeInTheDocument();
  });
});

describe('ErrorView', () => {
  beforeEach(() => setClientLocale('en'));

  it('retries on demand', () => {
    const onRetry = vi.fn();
    render(<ErrorView onRetry={onRetry} />);

    expect(screen.getByRole('heading', { level: 1, name: 'Something went wrong' })).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Try again' }));
    expect(onRetry).toHaveBeenCalledTimes(1);
  });

  it('blocks a second retry while one is running', () => {
    render(<ErrorView onRetry={vi.fn()} retrying />);

    expect(screen.getByRole('button', { name: 'Try again' })).toBeDisabled();
  });
});
