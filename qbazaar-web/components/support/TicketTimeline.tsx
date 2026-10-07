/**
 * A support ticket's thread in the chat layout of 365:14788: the opening
 * message and the user's replies on the end side, the support team's on the
 * start side with an avatar and the "Support team" chip.
 */
import { Badge } from '@/components/design-system/Badge';
import { NamedAvatar } from '@/components/account/NamedAvatar';
import { ChatBubble } from '@/components/messaging/ChatBubble';
import { intlLocale } from '@/lib/i18n/format';
import { getLocale } from '@/lib/i18n/locale';
import { t } from '@/lib/i18n/messages';
import type { SupportTicket } from '@/lib/api/types';

function formatTime(iso: string): string {
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return '';
  return new Intl.DateTimeFormat(intlLocale(getLocale()), { dateStyle: 'medium', timeStyle: 'short' }).format(date);
}

export function TicketTimeline({ ticket }: { ticket: SupportTicket }) {
  // The opener: the first reply by a non-staff author names them; anonymous
  // tickets fall back to the email's local part.
  const openerName =
    ticket.replies.find((reply) => !reply.author.is_staff)?.author.name ??
    (ticket.email ? ticket.email.split('@')[0] : t('support.you', 'أنت'));

  return (
    <div className="flex flex-col gap-6 px-[21px] py-6 qb-tablet:px-6 qb-desktop:px-10">
      <ThreadMessage author={openerName} isStaff={false} body={ticket.body} createdAt={ticket.created_at} />
      {ticket.replies.map((reply) => (
        <ThreadMessage
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

interface ThreadMessageProps {
  author: string;
  isStaff: boolean;
  body: string;
  createdAt: string;
}

function ThreadMessage({ author, isStaff, body, createdAt }: ThreadMessageProps) {
  return (
    <ChatBubble
      mine={!isStaff}
      avatar={isStaff ? <NamedAvatar name={author} className="mt-3 size-[53px]" /> : null}
      meta={
        <>
          <bdi className="font-medium text-qb-ink-secondary">{author}</bdi>
          {isStaff ? (
            <Badge tone="info" size="sm" className="border border-current px-1.5 py-0 text-qb-tiny">
              {t('support.staff_badge', 'فريق الدعم')}
            </Badge>
          ) : null}
          <span aria-hidden="true">·</span>
          <time dateTime={createdAt}>{formatTime(createdAt)}</time>
        </>
      }
    >
      {body}
    </ChatBubble>
  );
}
