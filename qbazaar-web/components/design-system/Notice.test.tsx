import { render, screen } from '@testing-library/react';
import { ShieldCheck } from 'lucide-react';
import { describe, expect, it } from 'vitest';

import { Notice } from './Notice';

describe('Notice', () => {
  it('renders the title and body in the chosen tone', () => {
    render(
      <Notice tone="danger" icon={<ShieldCheck aria-hidden="true" />} title="Limit reached" role="alert">
        Pay your commission to accept new orders.
      </Notice>,
    );

    const notice = screen.getByRole('alert');
    expect(notice).toHaveClass('border-qb-danger', 'bg-qb-danger-soft');
    expect(screen.getByText('Limit reached')).toHaveClass('font-semibold');
    expect(screen.getByText('Pay your commission to accept new orders.')).toBeInTheDocument();
  });

  it('defaults to the brand tone without a title', () => {
    render(<Notice>With Safe Pay you never share your bank details.</Notice>);

    expect(screen.getByText('With Safe Pay you never share your bank details.').parentElement?.parentElement).toHaveClass(
      'bg-qb-brand-soft',
    );
  });
});
