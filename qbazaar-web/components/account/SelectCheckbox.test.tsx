import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { SelectCheckbox } from './SelectCheckbox';

describe('SelectCheckbox', () => {
  it('is a named checkbox that reports the next state and draws the tick only when checked', () => {
    const onCheckedChange = vi.fn();
    const { rerender } = render(<SelectCheckbox checked={false} onCheckedChange={onCheckedChange} aria-label="Select All" />);
    const box = screen.getByRole('checkbox', { name: 'Select All' });

    expect(box).toHaveAttribute('aria-checked', 'false');
    expect(box.querySelector('svg')).toBeNull();

    fireEvent.click(box);
    expect(onCheckedChange).toHaveBeenCalledWith(true);

    rerender(<SelectCheckbox checked onCheckedChange={onCheckedChange} aria-label="Select All" />);
    expect(box).toHaveAttribute('aria-checked', 'true');
    expect(box.querySelector('svg')).toHaveAttribute('width', '13');

    fireEvent.click(box);
    expect(onCheckedChange).toHaveBeenLastCalledWith(false);
  });

  it('does nothing while disabled', () => {
    const onCheckedChange = vi.fn();
    render(<SelectCheckbox checked={false} disabled onCheckedChange={onCheckedChange} aria-label="Select All" />);

    fireEvent.click(screen.getByRole('checkbox', { name: 'Select All' }));
    expect(onCheckedChange).not.toHaveBeenCalled();
  });
});
