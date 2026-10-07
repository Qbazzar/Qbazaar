import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import { RadioCard } from './RadioCard';

describe('RadioCard', () => {
  it('is a labelled native radio that reports changes', async () => {
    const onChange = vi.fn();
    render(
      <fieldset>
        <legend>Delivery method</legend>
        <RadioCard name="fulfillment" value="pickup" label="Pickup" description="Meet the seller" onChange={onChange} />
        <RadioCard name="fulfillment" value="delivery" label="Delivery" defaultChecked onChange={onChange} />
      </fieldset>,
    );

    const pickup = screen.getByRole('radio', { name: /Pickup/ });
    expect(screen.getByRole('radio', { name: /Delivery/ })).toBeChecked();

    await userEvent.click(screen.getByText('Meet the seller'));

    expect(pickup).toBeChecked();
    expect(onChange).toHaveBeenCalledTimes(1);
  });

  it('can be disabled', () => {
    render(<RadioCard name="method" value="card" label="Card" disabled />);

    expect(screen.getByRole('radio', { name: 'Card' })).toBeDisabled();
  });
});
