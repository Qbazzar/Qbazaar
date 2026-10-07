import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { Switch } from './Switch';

describe('Switch', () => {
  it('is a named switch that reports its new state', () => {
    const onCheckedChange = vi.fn();
    render(<Switch aria-label="Show phone number" checked={false} onCheckedChange={onCheckedChange} />);

    const toggle = screen.getByRole('switch', { name: 'Show phone number' });
    expect(toggle).toHaveAttribute('aria-checked', 'false');

    fireEvent.click(toggle);
    expect(onCheckedChange).toHaveBeenCalledWith(true, expect.anything());
  });

  it('turns green when on and does nothing while disabled', () => {
    const onCheckedChange = vi.fn();
    render(<Switch aria-label="Allow chat" checked disabled onCheckedChange={onCheckedChange} />);

    const toggle = screen.getByRole('switch', { name: 'Allow chat' });
    expect(toggle).toHaveAttribute('aria-checked', 'true');
    expect(toggle).toHaveClass('data-checked:bg-qb-success');

    fireEvent.click(toggle);
    expect(onCheckedChange).not.toHaveBeenCalled();
  });
});
