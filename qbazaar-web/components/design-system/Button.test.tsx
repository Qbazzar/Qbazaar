import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { Button, buttonVariants } from './Button';

describe('Button', () => {
  it('is a non-submitting primary button by default', () => {
    render(<Button>Search</Button>);
    const button = screen.getByRole('button', { name: 'Search' });

    expect(button).toHaveAttribute('type', 'button');
    expect(button).toHaveClass('bg-qb-brand', 'h-12', 'focus-visible:outline-2');
  });

  it('applies the variant, size and full width', () => {
    render(
      <Button variant="muted" size="lg" fullWidth type="submit">
        Cancel
      </Button>,
    );
    const button = screen.getByRole('button', { name: 'Cancel' });

    expect(button).toHaveAttribute('type', 'submit');
    expect(button).toHaveClass('bg-qb-fill', 'font-normal', 'h-14', 'w-full');
    expect(button).not.toHaveClass('font-semibold');
  });

  it('lets a caller class win over the variant', () => {
    render(<Button className="h-9">Login</Button>);

    expect(screen.getByRole('button')).toHaveClass('h-9');
    expect(screen.getByRole('button')).not.toHaveClass('h-12');
  });

  it('keeps an icon-only button named through aria-label', () => {
    render(<Button size="icon" aria-label="Delete" />);

    expect(screen.getByRole('button', { name: 'Delete' })).toHaveClass('size-10');
  });

  it('exposes the classes for links styled as buttons', () => {
    expect(buttonVariants({ variant: 'secondary', size: 'sm' })).toContain('border-qb-brand');
  });
});
