import { useState } from 'react';
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import { AutocompleteInput, matchSuggestions } from './AutocompleteInput';

const PLACES = [
  { value: 'doha', label: 'Doha' },
  { value: 'west-bay', label: 'West Bay' },
  { value: 'al-wakrah', label: 'Al Wakrah' },
];

function Harness({ onSubmit = vi.fn() }: { onSubmit?: () => void }) {
  const [text, setText] = useState('');
  return (
    <form
      onSubmit={(event) => {
        event.preventDefault();
        onSubmit();
      }}
    >
      <AutocompleteInput aria-label="Location" value={text} onValueChange={setText} suggestions={PLACES} />
    </form>
  );
}

describe('matchSuggestions', () => {
  it('keeps the labels containing the text, ignoring case and outer spaces', () => {
    expect(matchSuggestions(PLACES, ' WA ').map(({ value }) => value)).toEqual(['al-wakrah']);
    expect(matchSuggestions(PLACES, 'a').map(({ value }) => value)).toEqual(['doha', 'west-bay', 'al-wakrah']);
    expect(matchSuggestions(PLACES, '')).toEqual(PLACES);
  });
});

describe('AutocompleteInput', () => {
  it('suggests the matching places in a listbox as the visitor types', async () => {
    const user = userEvent.setup();
    render(<Harness />);
    const input = screen.getByRole('combobox', { name: 'Location' });
    expect(input).toHaveAttribute('aria-expanded', 'false');

    await user.type(input, 'ba');

    const listbox = screen.getByRole('listbox', { name: 'Location' });
    expect(input).toHaveAttribute('aria-expanded', 'true');
    expect(within(listbox).getAllByRole('option').map((option) => option.textContent)).toEqual(['West Bay']);
  });

  it('picks a suggestion with a click', async () => {
    const user = userEvent.setup();
    render(<Harness />);
    const input = screen.getByRole('combobox', { name: 'Location' });

    await user.type(input, 'do');
    await user.click(screen.getByRole('option', { name: 'Doha' }));

    expect(input).toHaveValue('Doha');
    expect(screen.queryByRole('listbox')).toBeNull();
    expect(input).toHaveFocus();
  });

  it('moves through the suggestions with the arrow keys and picks with Enter instead of submitting', async () => {
    const user = userEvent.setup();
    const onSubmit = vi.fn();
    render(<Harness onSubmit={onSubmit} />);
    const input = screen.getByRole('combobox', { name: 'Location' });
    input.focus();

    await user.keyboard('{ArrowDown}');
    expect(input).toHaveAttribute('aria-activedescendant', screen.getByRole('option', { name: 'Doha' }).id);
    await user.keyboard('{ArrowDown}{Enter}');

    expect(input).toHaveValue('West Bay');
    expect(onSubmit).not.toHaveBeenCalled();
    await user.keyboard('{Enter}');
    expect(onSubmit).toHaveBeenCalledTimes(1);
  });

  it('closes the list on Escape and when the focus leaves', async () => {
    const user = userEvent.setup();
    render(<Harness />);
    const input = screen.getByRole('combobox', { name: 'Location' });

    await user.type(input, 'a');
    await user.keyboard('{Escape}');
    expect(screen.queryByRole('listbox')).toBeNull();
    expect(input).toHaveValue('a');

    await user.click(input);
    expect(screen.getByRole('listbox')).toBeInTheDocument();
    await user.tab();
    expect(screen.queryByRole('listbox')).toBeNull();
  });
});
