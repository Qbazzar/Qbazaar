import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('sonner', () => ({ toast: { error: vi.fn(), success: vi.fn() } }));
vi.mock('@/hooks/useAuth', () => ({ useAuth: vi.fn() }));
vi.mock('@/lib/queries/search', () => ({ useSaveSearchMutation: vi.fn() }));

import { toast } from 'sonner';
import { useAuth } from '@/hooks/useAuth';
import { ApiClientError } from '@/lib/api/auth';
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

    expect(screen.getByRole('link', { name: 'Save Search' })).toHaveAttribute('href', '/login');
  });

  it('keeps the label for screen readers in the icon-only phone button', () => {
    signIn(true);
    render(<SaveSearchButton params={{ q: 'car' }} variant="toolbar" />);

    expect(screen.getByRole('button', { name: 'Save Search' })).toHaveClass('w-11', 'qb-tablet:w-auto');
    expect(screen.getByText('Save Search')).toHaveClass('sr-only', 'qb-tablet:not-sr-only');
  });

  it('asks for a name, then saves the current search under it', async () => {
    signIn(true);
    mutate.mockImplementation((_payload, options) => options.onSuccess());
    const user = userEvent.setup();
    render(<SaveSearchButton params={{ q: 'car', category_slug: 'vehicles' }} />);

    await user.click(screen.getByRole('button', { name: 'Save Search' }));
    const name = await screen.findByLabelText(/Search name/);
    await user.click(screen.getByRole('button', { name: 'Save' }));

    expect(await screen.findByRole('alert')).toHaveTextContent('Give your search a name');
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

  it('turns into the "Saved" pill linking to the saved searches, until the filters change', async () => {
    signIn(true);
    mutate.mockImplementation((_payload, options) => options.onSuccess());
    const user = userEvent.setup();
    const { rerender } = render(<SaveSearchButton params={{ q: 'car' }} />);

    await user.click(screen.getByRole('button', { name: 'Save Search' }));
    await user.type(await screen.findByLabelText(/Search name/), 'Cars');
    await user.click(screen.getByRole('button', { name: 'Save' }));

    const saved = await screen.findByRole('link', { name: 'Saved, open your saved searches' });
    expect(saved).toHaveAttribute('href', '/account/saved-searches');
    expect(saved).toHaveTextContent('Saved');

    rerender(<SaveSearchButton params={{ q: 'van' }} />);
    expect(screen.getByRole('button', { name: 'Save Search' })).toBeInTheDocument();
  });

  it('caps the name at the contract length', async () => {
    signIn(true);
    const user = userEvent.setup();
    render(<SaveSearchButton params={{ q: 'car' }} />);

    await user.click(screen.getByRole('button', { name: 'Save Search' }));

    expect(await screen.findByLabelText(/Search name/)).toHaveAttribute('maxLength', '60');
  });

  it('explains the saved-search limit from its contract code', async () => {
    signIn(true);
    const limit = new ApiClientError({ status: 422, code: 'SEARCH_004', messageKey: 'search.limit', message: 'Limit reached' });
    mutate.mockImplementation((_payload, options) => options.onError(limit));
    const user = userEvent.setup();
    render(<SaveSearchButton params={{ q: 'car' }} />);

    await user.click(screen.getByRole('button', { name: 'Save Search' }));
    await user.type(await screen.findByLabelText(/Search name/), 'Cars');
    await user.click(screen.getByRole('button', { name: 'Save' }));

    await waitFor(() => expect(toast.error).toHaveBeenCalledWith("You've reached the saved-search limit"));
  });
});
