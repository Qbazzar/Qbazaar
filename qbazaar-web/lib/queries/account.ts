/**
 * TanStack Query hooks for the signed-in account.
 *
 * The summary key is the one the account hub reads, so both share a cache entry.
 */
import { useQuery, type UseQueryResult } from '@tanstack/react-query';

import type { ApiClientError } from '@/lib/api/auth';
import { getAccountSummary } from '@/lib/api/account';
import type { AccountSummary } from '@/lib/api/types';

export const accountKeys = {
  summary: () => ['account', 'summary'] as const,
};

/** The account's counters (`GET /account/summary`): ads by status, unread messages... */
export function useAccountSummaryQuery(): UseQueryResult<AccountSummary, ApiClientError> {
  return useQuery({
    queryKey: accountKeys.summary(),
    queryFn: getAccountSummary,
  });
}
