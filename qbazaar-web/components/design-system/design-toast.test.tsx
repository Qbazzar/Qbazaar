import { render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('sonner', () => ({ toast: { custom: vi.fn() } }));

import type { ReactElement } from 'react';
import { toast } from 'sonner';

import { showDesignToast } from './design-toast';

beforeEach(() => vi.clearAllMocks());

describe('showDesignToast', () => {
  it('shows one borderless mint toast at a time, without a close button', () => {
    showDesignToast('Ad reserved');

    const [renderToast, options] = vi.mocked(toast.custom).mock.calls[0];
    expect(options).toMatchObject({ id: 'qb-design-toast', unstyled: true, closeButton: false, duration: 3200 });

    render(renderToast('qb-design-toast') as ReactElement);
    const message = screen.getByText('Ad reserved');
    expect(message.parentElement).toHaveClass('bg-qb-toast-mint', 'text-qb-toast-mint-ink', 'rounded-[14px]');
    expect(message.parentElement).not.toHaveClass('border');
  });
});
