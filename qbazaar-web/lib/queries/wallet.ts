/**
 * TanStack Query hooks for the wallet, commission settlements, withdrawals
 * and payout bank accounts. Money moves change the summary and the
 * statement, so those are invalidated with the list that changed.
 */
import { useInfiniteQuery, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import type { ApiClientError } from '@/lib/api/auth';
import type {
  BankAccount,
  BankAccountPayload,
  Settlement,
  SettlementPayload,
  Wallet,
  WalletAccount,
  Withdrawal,
  WithdrawalPayload,
} from '@/lib/api/commerce-types';
import {
  addBankAccount,
  createSettlement,
  createWithdrawal,
  deleteBankAccount,
  getWallet,
  listBankAccounts,
  listSettlements,
  listWalletEntries,
  listWithdrawals,
} from '@/lib/api/wallet';
import { useIsAuthenticated } from '@/hooks/useIsAuthenticated';

const SECOND = 1000;

export const walletKeys = {
  all: ['wallet'] as const,
  summary: () => [...walletKeys.all, 'summary'] as const,
  entries: (account?: WalletAccount) => [...walletKeys.all, 'entries', account ?? 'all'] as const,
  settlements: () => [...walletKeys.all, 'settlements'] as const,
  withdrawals: () => [...walletKeys.all, 'withdrawals'] as const,
  bankAccounts: () => [...walletKeys.all, 'bank-accounts'] as const,
};

export function useWalletQuery() {
  const enabled = useIsAuthenticated();
  return useQuery<Wallet, ApiClientError>({
    queryKey: walletKeys.summary(),
    queryFn: () => getWallet(),
    enabled,
    staleTime: 15 * SECOND,
  });
}

export function useWalletEntriesQuery(account?: WalletAccount) {
  const enabled = useIsAuthenticated();
  return useInfiniteQuery({
    queryKey: walletKeys.entries(account),
    queryFn: ({ pageParam }) => listWalletEntries({ account, cursor: pageParam }),
    initialPageParam: null as string | null,
    getNextPageParam: (last) => (last.meta.has_more ? last.meta.next_cursor : undefined),
    enabled,
    staleTime: 15 * SECOND,
  });
}

export function useSettlementsQuery() {
  const enabled = useIsAuthenticated();
  return useInfiniteQuery({
    queryKey: walletKeys.settlements(),
    queryFn: ({ pageParam }) => listSettlements(pageParam),
    initialPageParam: null as string | null,
    getNextPageParam: (last) => (last.meta.has_more ? last.meta.next_cursor : undefined),
    enabled,
    staleTime: 15 * SECOND,
  });
}

export function useWithdrawalsQuery() {
  const enabled = useIsAuthenticated();
  return useInfiniteQuery({
    queryKey: walletKeys.withdrawals(),
    queryFn: ({ pageParam }) => listWithdrawals(pageParam),
    initialPageParam: null as string | null,
    getNextPageParam: (last) => (last.meta.has_more ? last.meta.next_cursor : undefined),
    enabled,
    staleTime: 15 * SECOND,
  });
}

export function useBankAccountsQuery() {
  const enabled = useIsAuthenticated();
  return useQuery<BankAccount[], ApiClientError>({
    queryKey: walletKeys.bankAccounts(),
    queryFn: () => listBankAccounts(),
    enabled,
    staleTime: 60 * SECOND,
  });
}

export function useCreateSettlementMutation() {
  const qc = useQueryClient();
  return useMutation<Settlement, ApiClientError, { payload: SettlementPayload; idempotencyKey: string }>({
    mutationFn: ({ payload, idempotencyKey }) => createSettlement(payload, idempotencyKey),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: walletKeys.summary() });
      qc.invalidateQueries({ queryKey: [...walletKeys.all, 'entries'] });
      qc.invalidateQueries({ queryKey: walletKeys.settlements() });
    },
  });
}

export function useCreateWithdrawalMutation() {
  const qc = useQueryClient();
  return useMutation<Withdrawal, ApiClientError, { payload: WithdrawalPayload; idempotencyKey: string }>({
    mutationFn: ({ payload, idempotencyKey }) => createWithdrawal(payload, idempotencyKey),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: walletKeys.summary() });
      qc.invalidateQueries({ queryKey: [...walletKeys.all, 'entries'] });
      qc.invalidateQueries({ queryKey: walletKeys.withdrawals() });
    },
  });
}

export function useAddBankAccountMutation() {
  const qc = useQueryClient();
  return useMutation<BankAccount, ApiClientError, BankAccountPayload>({
    mutationFn: (payload) => addBankAccount(payload),
    onSuccess: () => qc.invalidateQueries({ queryKey: walletKeys.bankAccounts() }),
  });
}

export function useDeleteBankAccountMutation() {
  const qc = useQueryClient();
  return useMutation<void, ApiClientError, string>({
    mutationFn: (id) => deleteBankAccount(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: walletKeys.bankAccounts() }),
  });
}
