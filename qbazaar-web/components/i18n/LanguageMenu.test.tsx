import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { setClientLocale } from '@/lib/i18n/locale';

const switchLocale = vi.hoisted(() => vi.fn());
vi.mock('./switch-locale', () => ({ switchLocale }));

import { LanguageMenu } from './LanguageMenu';

function renderMenu() {
  return render(
    <>
      <LanguageMenu className="globe">globe</LanguageMenu>
      <a href="/next">Next link</a>
    </>,
  );
}

describe('LanguageMenu', () => {
  beforeEach(() => {
    setClientLocale('en');
    switchLocale.mockClear();
  });

  it('opens a named panel with both languages, the current one marked', async () => {
    const user = userEvent.setup();
    renderMenu();
    const trigger = screen.getByRole('button', { name: 'Language' });
    expect(trigger).toHaveAttribute('aria-expanded', 'false');

    await user.click(trigger);

    expect(trigger).toHaveAttribute('aria-expanded', 'true');
    expect(screen.getByRole('group', { name: 'Choose your language' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'English' })).toHaveAttribute('aria-current', 'true');
    expect(screen.getByRole('button', { name: 'العربية' })).not.toHaveAttribute('aria-current');
  });

  it('switches to the language picked', async () => {
    const user = userEvent.setup();
    renderMenu();

    await user.click(screen.getByRole('button', { name: 'Language' }));
    await user.tab();
    await user.tab();
    expect(screen.getByRole('button', { name: 'العربية' })).toHaveFocus();
    await user.keyboard('{Enter}');

    expect(switchLocale).toHaveBeenCalledWith('ar');
  });

  it('closes on Escape and gives the focus back to the globe', async () => {
    const user = userEvent.setup();
    renderMenu();
    const trigger = screen.getByRole('button', { name: 'Language' });

    await user.click(trigger);
    await user.tab();
    expect(screen.getByRole('button', { name: 'English' })).toHaveFocus();
    await user.keyboard('{Escape}');

    expect(screen.queryByRole('group')).toBeNull();
    expect(trigger).toHaveFocus();
  });

  it('opens the desktop panel while the mouse rests on the globe and keeps it open on a click', async () => {
    const user = userEvent.setup();
    renderMenu();
    const trigger = screen.getByRole('button', { name: 'Language' });

    await user.hover(trigger);
    expect(screen.getByRole('group', { name: 'Choose your language' })).toBeInTheDocument();
    await user.click(trigger);
    expect(trigger).toHaveAttribute('aria-expanded', 'true');

    await user.unhover(trigger);
    expect(screen.queryByRole('group')).toBeNull();
  });

  it('leaves the phone popup to clicks', async () => {
    const user = userEvent.setup();
    render(
      <LanguageMenu className="globe" variant="phone">
        globe
      </LanguageMenu>,
    );
    const trigger = screen.getByRole('button', { name: 'Language' });

    await user.hover(trigger);
    expect(screen.queryByRole('group')).toBeNull();
    await user.click(trigger);
    expect(screen.getByRole('group', { name: 'Choose your language' })).toBeInTheDocument();
  });

  it('closes when the focus or a click goes elsewhere', async () => {
    const user = userEvent.setup();
    renderMenu();

    await user.click(screen.getByRole('button', { name: 'Language' }));
    await user.tab();
    await user.tab();
    await user.tab();
    expect(screen.getByRole('link', { name: 'Next link' })).toHaveFocus();
    expect(screen.queryByRole('group')).toBeNull();

    await user.click(screen.getByRole('button', { name: 'Language' }));
    await user.click(document.body);
    expect(screen.queryByRole('group')).toBeNull();
    expect(switchLocale).not.toHaveBeenCalled();
  });
});
