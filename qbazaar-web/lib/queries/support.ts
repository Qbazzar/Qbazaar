/**
 * TanStack Query hooks for the support tickets domain (Sprint 12).
 *
 * Lists + detail use a short 30s staleTime so the conversation thread feels
 * fresh without hammering the backend. The reply mutation invalidates both
 * the detail (to pull in the new reply) and the list (to bump `last_replied_at`).
 *
 * `useCreateTicketMutation` surfaces a success toast and routes auth users
 * onto the new ticket; anonymous flows handle navigation locally.
 */
import { useMutation, useQuery, useQueryClient, type UseMutationResult, type UseQueryResult } from '@tanstack/react-query';
import { useRouter } from 'next/navigation';
import { toast } from 'sonner';
import {
  createTicket,
  getTicket,
  listMyTickets,
  replyToTicket,
  type ListMyTicketsParams,
} from '@/lib/api/support';
import type { ApiClientError } from '@/lib/api/auth';
import { t } from '@/lib/i18n/messages';
import { useAuthStore } from '@/store/auth';
import type {
  MakeSupportTicketRequest,
  PaginatedResponse,
  SupportReply,
  SupportTicket,
  SupportTicketListItem,
} from '@/lib/api/types';

const SECOND = 1000;

export const supportKeys = {
  all: ['support'] as const,
  lists: () => [...supportKeys.all, 'list'] as const,
  list: (params: ListMyTicketsParams) =>
    [...supportKeys.lists(), params] as const,
  detail: (id: string) => [...supportKeys.all, 'detail', id] as const,
};

export function useMyTicketsQuery(
  params: ListMyTicketsParams = {},
): UseQueryResult<PaginatedResponse<SupportTicketListItem>, ApiClientError> {
  const isAuthenticated = useAuthStore((s) => Boolean(s.user && s.accessToken));
  return useQuery<PaginatedResponse<SupportTicketListItem>, ApiClientError>({
    queryKey: supportKeys.list(params),
    queryFn: () => listMyTickets(params),
    enabled: isAuthenticated,
    staleTime: 30 * SECOND,
    placeholderData: (prev) => prev,
  });
}

export function useTicketQuery(
  id: string,
): UseQueryResult<SupportTicket, ApiClientError> {
  const isAuthenticated = useAuthStore((s) => Boolean(s.user && s.accessToken));
  return useQuery<SupportTicket, ApiClientError>({
    queryKey: supportKeys.detail(id),
    queryFn: () => getTicket(id),
    enabled: Boolean(id) && isAuthenticated,
    staleTime: 30 * SECOND,
  });
}

export function useCreateTicketMutation(): UseMutationResult<
  SupportTicket,
  ApiClientError,
  MakeSupportTicketRequest
> {
  const qc = useQueryClient();
  const router = useRouter();
  const isAuthenticated = useAuthStore((s) => Boolean(s.user && s.accessToken));

  return useMutation<SupportTicket, ApiClientError, MakeSupportTicketRequest>({
    mutationFn: (payload) => createTicket(payload),
    onSuccess: (ticket) => {
      toast.success(
        t('support.submit_success_toast', 'تم استلام طلبك، سنرد قريباً'),
      );
      // Invalidate the authenticated list so the new row appears.
      qc.invalidateQueries({ queryKey: supportKeys.lists() });
      if (isAuthenticated) {
        router.push(`/account/support/${ticket.id}`);
      }
    },
    onError: (err) => {
      // Validation errors are surfaced inline by the form; bubble everything
      // else as a generic toast so the user has feedback.
      if (err.code !== 'VALIDATION_FAILED') {
        toast.error(err.message || t('common.error', 'حدث خطأ، حاول مرة أخرى'));
      }
    },
  });
}

export interface ReplyToTicketVariables {
  ticketId: string;
  body: string;
}

export function useReplyToTicketMutation(): UseMutationResult<
  SupportReply,
  ApiClientError,
  ReplyToTicketVariables
> {
  const qc = useQueryClient();
  return useMutation<SupportReply, ApiClientError, ReplyToTicketVariables>({
    mutationFn: ({ ticketId, body }) => replyToTicket(ticketId, body),
    onSuccess: (_reply, { ticketId }) => {
      qc.invalidateQueries({ queryKey: supportKeys.detail(ticketId) });
      qc.invalidateQueries({ queryKey: supportKeys.lists() });
    },
    onError: (err) => {
      if (err.code === 'TICKET_INVALID_TRANSITION') {
        toast.warning(
          t(
            'support.ticket_closed_notice',
            'لا يمكن الرد على تذكرة مغلقة. افتح تذكرة جديدة.',
          ),
        );
        return;
      }
      if (err.code !== 'VALIDATION_FAILED') {
        toast.error(err.message || t('common.error', 'حدث خطأ، حاول مرة أخرى'));
      }
    },
  });
}
