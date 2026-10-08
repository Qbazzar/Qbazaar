import { createRef } from 'react';
import { render, screen } from '@testing-library/react';
import { Search } from 'lucide-react';
import { describe, expect, it } from 'vitest';

import { Field } from './Field';
import { Input, Select, Textarea } from './Input';

describe('Field', () => {
  it('labels the control and marks it required', () => {
    render(<Field label="Email" required>{(control) => <Input type="email" {...control} />}</Field>);
    const input = screen.getByLabelText(/Email/);

    expect(input).toBeRequired();
    expect(input).toHaveAttribute('type', 'email');
    expect(input).not.toHaveAttribute('aria-invalid');
  });

  it('describes the control with its hint and error', () => {
    render(
      <Field label="Password" hint="8 characters or more" error="Too short">
        {(control) => <Input type="password" {...control} />}
      </Field>,
    );
    const input = screen.getByLabelText('Password');

    expect(input).toHaveAttribute('aria-invalid', 'true');
    expect(input).toHaveAccessibleDescription('8 characters or more Too short');
  });

  it('announces the error when it appears', () => {
    const { rerender } = render(<Field label="Email">{(control) => <Input {...control} />}</Field>);
    expect(screen.queryByRole('alert')).toBeNull();

    rerender(<Field label="Email" error="Enter a valid email">{(control) => <Input {...control} />}</Field>);
    expect(screen.getByRole('alert')).toHaveTextContent('Enter a valid email');
  });

  it('can hide the label visually and still name the control', () => {
    render(<Field label="Message" hideLabel>{(control) => <Textarea {...control} />}</Field>);

    expect(screen.getByRole('textbox', { name: 'Message' })).toBeInTheDocument();
    expect(screen.getByText('Message')).toHaveClass('sr-only');
  });
});

describe('Input, Select, Textarea', () => {
  it('hides the decorative start icon and pads the text on the logical start side', () => {
    const { container } = render(<Input aria-label="Search" startIcon={<Search />} />);

    expect(screen.getByRole('textbox', { name: 'Search' })).toHaveClass('ps-11');
    expect(container.querySelector('[aria-hidden="true"] svg')).not.toBeNull();
  });

  it('renders a native select with a hidden chevron', () => {
    render(
      <Select aria-label="City" defaultValue="doha">
        <option value="doha">Doha</option>
        <option value="wakrah">Al Wakrah</option>
      </Select>,
    );

    expect(screen.getByRole('combobox', { name: 'City' })).toHaveValue('doha');
    expect(screen.getByRole('combobox')).toHaveClass('appearance-none', 'pe-11');
  });

  it('shares the 3:1 border and the focus and invalid styles', () => {
    render(<Textarea aria-label="Notes" aria-invalid />);

    expect(screen.getByRole('textbox', { name: 'Notes' })).toHaveClass(
      'border-qb-field-border',
      'focus-visible:border-qb-brand',
      'aria-invalid:border-qb-danger',
    );
  });

  it('passes a ref to the native control', () => {
    const ref = createRef<HTMLInputElement>();
    render(<Input aria-label="Phone" ref={ref} startIcon={<Search />} />);

    expect(ref.current).toBe(screen.getByRole('textbox', { name: 'Phone' }));
  });
});
