import { fireEvent, render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it } from 'vitest';

import { setClientLocale } from '@/lib/i18n/locale';

import { HelpFeedback } from './HelpFeedback';

describe('HelpFeedback', () => {
  beforeEach(() => setClientLocale('en'));

  it('groups the answers under the question', () => {
    render(<HelpFeedback />);

    expect(screen.getByRole('group', { name: 'Was this article helpful?' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Yes' })).toHaveAttribute('aria-pressed', 'false');
    expect(screen.getByRole('status')).toBeEmptyDOMElement();
  });

  it('marks the vote, thanks the reader and lets them change it', () => {
    render(<HelpFeedback />);

    fireEvent.click(screen.getByRole('button', { name: 'Yes' }));
    expect(screen.getByRole('button', { name: 'Yes' })).toHaveAttribute('aria-pressed', 'true');
    expect(screen.getByRole('status')).toHaveTextContent('Thanks! Glad it helped.');

    fireEvent.click(screen.getByRole('button', { name: 'No' }));
    expect(screen.getByRole('button', { name: 'Yes' })).toHaveAttribute('aria-pressed', 'false');
    expect(screen.getByRole('button', { name: 'No' })).toHaveAttribute('aria-pressed', 'true');
    expect(screen.getByRole('status')).toHaveTextContent("Thanks — we'll improve this article.");
  });
});
