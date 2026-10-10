import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { setClientLocale } from '@/lib/i18n/locale';

import { SortMenu } from './SortMenu';

function setup() {
  const onChange = vi.fn();
  render(
    <div>
      <SortMenu value="latest" onChange={onChange} />
      <p>Outside</p>
    </div>,
  );
  return { onChange, user: userEvent.setup(), trigger: screen.getByRole('combobox') };
}

describe('SortMenu', () => {
  beforeEach(() => setClientLocale('en'));

  it('closes on Escape and returns the focus to the trigger', async () => {
    const { user, trigger } = setup();

    await user.click(trigger);
    expect(await screen.findByRole('listbox')).toBeInTheDocument();
    await user.keyboard('{Escape}');

    expect(screen.queryByRole('listbox')).not.toBeInTheDocument();
    expect(trigger).toHaveFocus();
  });

  it('selects with ArrowDown and Enter', async () => {
    const { user, trigger, onChange } = setup();

    trigger.focus();
    await user.keyboard('{Enter}');
    await screen.findByRole('listbox');
    await user.keyboard('{ArrowDown}{Enter}');

    expect(onChange).toHaveBeenCalledTimes(1);
    expect(onChange).not.toHaveBeenCalledWith('latest');
  });

  it('closes on an outside click without choosing', async () => {
    const { user, trigger, onChange } = setup();

    await user.click(trigger);
    await screen.findByRole('listbox');
    await user.click(screen.getByText('Outside'));

    expect(screen.queryByRole('listbox')).not.toBeInTheDocument();
    expect(onChange).not.toHaveBeenCalled();
  });
});
