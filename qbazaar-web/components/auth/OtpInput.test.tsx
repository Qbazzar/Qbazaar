import { useState } from 'react';
import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { OtpInput } from './OtpInput';

function ControlledOtp() {
  const [value, setValue] = useState('');
  return <OtpInput value={value} onChange={setValue} />;
}

describe('OtpInput', () => {
  it('shows the design zero in empty boxes without giving the inputs a placeholder', () => {
    const { container } = render(<ControlledOtp />);

    const boxes = screen.getAllByRole('textbox');
    expect(boxes).toHaveLength(6);
    boxes.forEach((box) => expect(box).not.toHaveAttribute('placeholder'));

    const zeros = container.querySelectorAll('[aria-hidden="true"]');
    expect(zeros).toHaveLength(6);
    zeros.forEach((zero) => expect(zero).toHaveTextContent('0'));
  });

  it('drops the zero from a box once it holds a digit', () => {
    const { container } = render(<ControlledOtp />);

    fireEvent.change(screen.getAllByRole('textbox')[0], { target: { value: '7' } });

    expect(screen.getAllByRole('textbox')[0]).toHaveValue('7');
    expect(container.querySelectorAll('[aria-hidden="true"]')).toHaveLength(5);
  });
});
