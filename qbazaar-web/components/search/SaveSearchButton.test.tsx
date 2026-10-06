import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('sonner', () => ({ toast: { error: vi.fn(), success: vi.fn() } }));
vi.mock('@/hooks/useAuth', () => ({ useAuth: vi.fn() }));
vi.mock('@/lib/queries/search', () => ({ useSaveSearchMutation: vi.fn() }));

import { toast } from 'sonner';
import { useAuth } from '@/hooks/useAuth';
import { setClientLocale } from '@/lib/i18n/locale';
import { useSaveSearchMutation } from '@/lib/queries/search';

import { SaveSearchButton } from './SaveSearchButton';

const mutate = vi.fn();

function signIn(isAuthenticated: boolean) {
  vi.mocked(useAuth).mockReturnValue({ isAuthenticated, isHydrated: true } as never);
  vi.mocked(useSaveSearchMutation).mockReturnValue({ mutate, isPending: false } as never);
}

describe('SaveSearchButton', () => {
  beforeEach(() => {
    setClientLocale('en');
    vi.clearAllMocks();
  });

  it('sends signed-out visitors to the login page', () => {
    signIn(false);
    render(<SaveSearchButton params={{ q: 'car' }} />);

    expect(screen.getByRole('link', { name: 'Save search' })).toHaveAttribute('href', '/login');
  });

  it('keeps the label for screen readers in the icon-only phone button', () => {
    signIn(true);
    render(<SaveSearchButton params={{ q: 'car' }} variant="toolbar" />);

    expect(screen.getByRole('button', { name: 'Save search' })).toHaveClass('w-11', 'qb-tablet:w-auto');
    expect(screen.getByText('Save search')).toHaveClass('sr-only', 'qb-tablet:not-sr-only');
  });

  it('asks for a name, then saves the current search under it', async () => {
    signIn(true);
    mutate.mockImplementation((_payload, options) => options.onSuccess());
    const user = userEvent.setup();
    render(<SaveSearchButton params={{ q: 'car', category_slug: 'vehicles' }} />);

    await user.click(screen.getByRole('button', { name: 'Save search' }));
    const name = await screen.findByLabelText(/Search name/);
    await user.click(screen.getByRole('button', { name: 'Save' }));

    expect(await screen.findByText('Give your search a name')).toBeInTheDocument();
    expect(name).toHaveAttribute('aria-invalid', 'true');
    expect(mutate).not.toHaveBeenCalled();

    await user.type(name, '  Family cars ');
    await user.click(screen.getByRole('button', { name: 'Save' }));

    await waitFor(() =>
      expect(mutate).toHaveBeenCalledWith(
        { name: 'Family cars', query_params: { q: 'car', category_slug: 'vehicles' } },
        expect.any(Object),
      ),
    );
    expect(toast.success).toHaveBeenCalledWith('Search saved');
  });
});
