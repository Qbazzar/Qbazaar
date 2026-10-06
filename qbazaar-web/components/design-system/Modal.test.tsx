import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { setClientLocale } from '@/lib/i18n/locale';

import { Button } from './Button';
import { Modal, Sheet } from './Modal';

describe('Modal', () => {
  beforeEach(() => setClientLocale('en'));

  it('opens from its trigger as a named, described dialog', async () => {
    const user = userEvent.setup();
    render(
      <Modal
        title="Delete Chat?"
        description="This action cannot be undone."
        trigger={<Button variant="danger">Delete</Button>}
      >
        <Button>Delete Chat</Button>
      </Modal>,
    );

    await user.click(screen.getByRole('button', { name: 'Delete' }));
    const dialog = await screen.findByRole('dialog', { name: 'Delete Chat?' });

    expect(dialog).toHaveAccessibleDescription('This action cannot be undone.');
    expect(dialog).toHaveClass('rounded-qb-2xl', 'max-w-[538px]');
    expect(screen.queryByRole('button', { name: 'Close' })).toBeNull();
  });

  it('closes on Escape and reports it', async () => {
    const user = userEvent.setup();
    const onOpenChange = vi.fn();
    render(<Modal title="Report this ad" defaultOpen onOpenChange={onOpenChange} />);

    await screen.findByRole('dialog');
    await user.keyboard('{Escape}');

    expect(onOpenChange).toHaveBeenCalledWith(false, expect.anything());
    await waitFor(() => expect(screen.queryByRole('dialog')).toBeNull());
  });
});

describe('Sheet', () => {
  beforeEach(() => setClientLocale('en'));

  it('has a labelled close button that closes it', async () => {
    const user = userEvent.setup();
    render(<Sheet title="Advanced Filters" defaultOpen />);

    const dialog = await screen.findByRole('dialog', { name: 'Advanced Filters' });
    expect(dialog).toHaveClass('bottom-0', 'rounded-t-qb-2xl');

    await user.click(screen.getByRole('button', { name: 'Close' }));
    await waitFor(() => expect(screen.queryByRole('dialog')).toBeNull());
  });
});
