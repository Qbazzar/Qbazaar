import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { t } from '@/lib/i18n/messages';
import type { ConversationListItem } from '@/lib/api/types';

import { ConversationRow } from './ConversationRow';

const conversation = {
  id: 'c1',
  ad: { id: 'a1', title: 'Canon EOS R6 body', primary_image: null, price: 5550, price_type: 'fixed', currency: 'QAR' },
  other_participant: { id: 'u2', full_name: 'Noora Al Sulaiti', avatar_thumb_url: null },
  last_message_preview: 'Offer accepted',
  last_message_at: new Date().toISOString(),
  unread_count: 3,
} satisfies ConversationListItem;

describe('ConversationRow', () => {
  it('shows who, which ad, the last message and the unread count', () => {
    render(<ConversationRow conversation={conversation} active={false} onSelect={vi.fn()} />);
    const row = screen.getByRole('button');

    expect(row).toHaveTextContent('Noora Al Sulaiti');
    expect(row).toHaveTextContent('Canon EOS R6 body');
    expect(row).toHaveTextContent('Offer accepted');
    expect(row).toHaveTextContent(`3 ${t('messaging.unread_label')}`);
    expect(row).not.toHaveAttribute('aria-current');
  });

  it('selects the conversation and marks the open one as current', () => {
    const onSelect = vi.fn();
    render(<ConversationRow conversation={{ ...conversation, unread_count: 0 }} active onSelect={onSelect} />);

    const row = screen.getByRole('button');
    expect(row).toHaveAttribute('aria-current', 'true');
    expect(row).not.toHaveTextContent(t('messaging.unread_label'));

    fireEvent.click(row);
    expect(onSelect).toHaveBeenCalledWith('c1');
  });
});
