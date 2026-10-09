'use client';

import { useState } from 'react';
import { Menu } from '@base-ui/react/menu';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { Ban, CircleAlert } from 'lucide-react';

import { focusRing } from '@/components/design-system/focus-ring';
import { ReportDialog } from '@/components/reports/ReportDialog';
import { apiErrorMessage } from '@/components/account/api-error-message';
import { blockUser } from '@/lib/api/users';
import { messagingKeys } from '@/lib/queries/messaging';
import { t } from '@/lib/i18n/messages';
import { cn } from '@/lib/utils';

import { ChatConfirmDialog } from './ChatConfirmDialog';

/** Below the reference's 601 px tablet breakpoint the report form is a bottom sheet. */
const PHONE_QUERY = '(max-width: 600px)';

interface ChatSettingsMenuProps {
  peer: { id: string; full_name: string };
}

const itemClass =
  'flex w-full cursor-pointer items-center justify-between gap-2.5 px-[18px] py-[13px] text-start text-qb-body-sm outline-none data-highlighted:bg-qb-hover [&_svg]:size-[18px] [&_svg]:shrink-0';

/**
 * The "⋯" of the chat header (chat.js, 604:33852): a "Setting" menu with
 * "Report" (the report form) and "Block" (the "Block User?" confirmation).
 * Arrow keys move through it; Escape or a click outside closes it.
 */
export function ChatSettingsMenu({ peer }: ChatSettingsMenuProps) {
  const queryClient = useQueryClient();
  const [reportOpen, setReportOpen] = useState(false);
  const [asSheet, setAsSheet] = useState(false);
  const [blockOpen, setBlockOpen] = useState(false);

  const block = useMutation({
    mutationFn: () => blockUser(peer.id),
    onSuccess: () => {
      toast.success(t('messaging.menu.blocked', { name: peer.full_name.split(' ')[0] }));
      queryClient.invalidateQueries({ queryKey: ['account', 'blocked-users'] });
      queryClient.invalidateQueries({ queryKey: messagingKeys.lists() });
      setBlockOpen(false);
    },
    onError: (err) => toast.error(apiErrorMessage(err)),
  });

  return (
    <>
      <Menu.Root>
        <Menu.Trigger
          aria-label={t('messaging.menu.label')}
          className={cn(
            'flex size-9 shrink-0 cursor-pointer items-center justify-center rounded-full border border-qb-line text-qb-ink-subtle hover:text-qb-ink',
            focusRing,
          )}
        >
          <span aria-hidden="true" className="leading-none">
            ⋯
          </span>
        </Menu.Trigger>
        <Menu.Portal>
          <Menu.Positioner side="bottom" align="end" sideOffset={6} className="z-50">
            <Menu.Popup className="w-[190px] overflow-hidden rounded-[14px] bg-qb-surface py-1.5 font-qb shadow-qb-chat-menu outline-none">
              <Menu.Group>
                <Menu.GroupLabel className="border-b border-qb-acct-hub-divider px-[18px] py-3 text-qb-body font-semibold text-qb-ink">
                  {t('messaging.menu.title')}
                </Menu.GroupLabel>
                <Menu.Item
                  className={cn(itemClass, 'text-qb-ink')}
                  onClick={() => {
                    setAsSheet(window.matchMedia(PHONE_QUERY).matches);
                    setReportOpen(true);
                  }}
                >
                  {t('messaging.menu.report')}
                  <CircleAlert aria-hidden="true" className="text-qb-brand" strokeWidth={1.8} />
                </Menu.Item>
                <Menu.Item className={cn(itemClass, 'text-qb-chat-block')} onClick={() => setBlockOpen(true)}>
                  {t('messaging.menu.block')}
                  <Ban aria-hidden="true" strokeWidth={1.8} />
                </Menu.Item>
              </Menu.Group>
            </Menu.Popup>
          </Menu.Positioner>
        </Menu.Portal>
      </Menu.Root>

      <ReportDialog
        open={reportOpen}
        onOpenChange={setReportOpen}
        asSheet={asSheet}
        target_type="user"
        target_id={peer.id}
        onReported={() => setReportOpen(false)}
      />
      <ChatConfirmDialog
        variant="block"
        open={blockOpen}
        onOpenChange={setBlockOpen}
        title={t('messaging.menu.block_title')}
        body={t('messaging.menu.block_body')}
        confirmLabel={t('messaging.menu.block')}
        onConfirm={() => block.mutate()}
        pending={block.isPending}
      />
    </>
  );
}
