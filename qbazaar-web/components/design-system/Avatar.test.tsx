import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { Avatar, initialsOf } from './Avatar';

describe('initialsOf', () => {
  it.each([
    ['Farah Alzinati', 'FA'],
    ['Farah Mohammad Alzinati', 'FA'],
    ['  Lina ', 'L'],
    ['فرح الزيناتي', 'فز'],
    ['لؤلؤة العقارية', 'لع'],
    ['الدوحة', 'د'],
  ])('%s -> %s', (name, initials) => {
    expect(initialsOf(name)).toBe(initials);
  });
});

describe('Avatar', () => {
  it('shows initials named by the full name', () => {
    render(<Avatar name="Farah Alzinati" tone="brand" size="lg" />);
    const avatar = screen.getByRole('img', { name: 'Farah Alzinati' });

    expect(avatar).toHaveTextContent('FA');
    expect(avatar).toHaveClass('bg-qb-brand-soft', 'text-qb-brand-on-soft', 'size-[66px]');
  });

  it('stays out of the accessibility tree when decorative', () => {
    const { container } = render(<Avatar name="Farah Alzinati" decorative />);

    expect(screen.queryByRole('img')).toBeNull();
    expect(container.firstChild).toHaveAttribute('aria-hidden', 'true');
    expect(container.firstChild).toHaveTextContent('FA');
  });

  it('shows the photo when there is one', () => {
    render(<Avatar name="Farah Alzinati" src="https://example.com/a.jpg" />);

    expect(screen.getByRole('img', { name: 'Farah Alzinati' })).toHaveAttribute('src', 'https://example.com/a.jpg');
  });
});
