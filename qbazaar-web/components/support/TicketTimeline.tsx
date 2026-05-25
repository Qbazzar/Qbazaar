/**
 * Chronological thread for a support ticket.
 *
 * Renders the original ticket body as the first bubble (authored by the
 * ticket creator), then the replies in `created_at` order. Staff replies get
 * a sage-accented border; user replies get the coral accent. Each bubble
 * shows author + relative timestamp + an `is_staff` badge where appropriate.
 */
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';
import type { SupportTicket } from '@/lib/api/types';

interface Props {
  ticket: SupportTicket;
}

function initials(name: string): string {
  const trimmed = name.trim();
  if (!trimmed) return '?';
  const parts = trimmed.split(/\s+/);
  if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
  return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
}

function formatTime(iso: string): string {
  try {
    return new Date(iso).toLocaleString(undefined, {
      dateStyle: 'medium',
      timeStyle: 'short',
    });
  } catch {
    return iso;
  }
}

export function TicketTimeline({ ticket }: Props) {
  // The original opener — when the backend returns a logged-in author it's
  // surfaced on the first reply; when it's anonymous we use the email prefix
  // as a friendly fallback.
  const openerName =
    ticket.replies.find((r) => !r.author.is_staff)?.author.name ??
    (ticket.email ? ticket.email.split('@')[0] : t('support.you', 'أنت'));

  return (
    <div className="ticket-thread">
      <Bubble
        author={openerName}
        isStaff={false}
        body={ticket.body}
        createdAt={ticket.created_at}
      />
      {ticket.replies.map((reply) => (
        <Bubble
          key={reply.id}
          author={reply.author.name}
          isStaff={reply.author.is_staff}
          body={reply.body}
          createdAt={reply.created_at}
        />
      ))}
    </div>
  );
}

interface BubbleProps {
  author: string;
  isStaff: boolean;
  body: string;
  createdAt: string;
}

function Bubble({ author, isStaff, body, createdAt }: BubbleProps) {
  return (
    <article
      className={cn(
        'ticket-bubble',
        isStaff ? 'ticket-bubble--staff' : 'ticket-bubble--user',
      )}
    >
      <span aria-hidden className="ticket-bubble__avatar">
        {initials(author)}
      </span>
      <div className="min-w-0">
        <header className="ticket-bubble__head">
          <span className="ticket-bubble__author">{author}</span>
          {isStaff ? (
            <span className="ticket-bubble__staff-badge">
              {t('support.staff_badge', 'فريق الدعم')}
            </span>
          ) : null}
          <time dateTime={createdAt}>{formatTime(createdAt)}</time>
        </header>
        <div className="ticket-bubble__body">{body}</div>
      </div>
    </article>
  );
}
