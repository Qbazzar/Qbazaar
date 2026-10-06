/**
 * Wallet, commission settlements, withdrawals and payout bank accounts
 * (M1b, BE-14.38 and BE-14.39). Balances are read from the ledger on the
 * server; the client only displays them and sends exact decimal strings.
 */
import { api } from './client';
import type {
  BankAccount,
  BankAccountPayload,
  CursorPage,
  Settlement,
  SettlementPayload,
  Wallet,
  WalletAccount,
  WalletEntry,
  Withdrawal,
  WithdrawalPayload,
} from './commerce-types';
import { idempotent, query, unwrap, unwrapBody } from './request';
import type { SuccessEnvelope } from './types';

const WALLET = '/api/v1/account/wallet';
const BANK_ACCOUNTS = '/api/v1/account/bank-accounts';

export function getWallet(): Promise<Wallet> {
  return unwrap(api.get<SuccessEnvelope<Wallet>>(WALLET));
}

export interface StatementParams {
  account?: WalletAccount;
  cursor?: string | null;
}

export function listWalletEntries({ account, cursor }: StatementParams): Promise<CursorPage<WalletEntry>> {
  return unwrapBody(api.get<CursorPage<WalletEntry>>(`${WALLET}/transactions`, { params: query({ account, cursor }) }));
}

export function listSettlements(cursor?: string | null): Promise<CursorPage<Settlement>> {
  return unwrapBody(api.get<CursorPage<Settlement>>(`${WALLET}/settlements`, { params: query({ cursor }) }));
}

/** A bank transfer goes up as multipart with its receipt; wallet netting is plain JSON. */
export function createSettlement(payload: SettlementPayload, idempotencyKey: string): Promise<Settlement> {
  if (payload.method === 'wallet') {
    return unwrap(
      api.post<SuccessEnvelope<Settlement>>(
        `${WALLET}/settlements`,
        { method: 'wallet', amount: payload.amount },
        idempotent(idempotencyKey),
      ),
    );
  }

  const form = new FormData();
  form.append('method', 'bank_transfer');
  form.append('amount', payload.amount);
  form.append('bank_reference', payload.bankReference);
  form.append('proof', payload.proof);
  const { headers } = idempotent(idempotencyKey);
  return unwrap(
    api.post<SuccessEnvelope<Settlement>>(`${WALLET}/settlements`, form, {
      headers: { ...headers, 'Content-Type': 'multipart/form-data' },
    }),
  );
}

export function listWithdrawals(cursor?: string | null): Promise<CursorPage<Withdrawal>> {
  return unwrapBody(api.get<CursorPage<Withdrawal>>(`${WALLET}/withdrawals`, { params: query({ cursor }) }));
}

export function createWithdrawal(payload: WithdrawalPayload, idempotencyKey: string): Promise<Withdrawal> {
  return unwrap(
    api.post<SuccessEnvelope<Withdrawal>>(`${WALLET}/withdrawals`, payload, idempotent(idempotencyKey)),
  );
}

export function listBankAccounts(): Promise<BankAccount[]> {
  return unwrap(api.get<SuccessEnvelope<BankAccount[]>>(BANK_ACCOUNTS));
}

export function addBankAccount(payload: BankAccountPayload): Promise<BankAccount> {
  return unwrap(api.post<SuccessEnvelope<BankAccount>>(BANK_ACCOUNTS, payload));
}

export async function deleteBankAccount(id: string): Promise<void> {
  await unwrapBody(api.delete(`${BANK_ACCOUNTS}/${encodeURIComponent(id)}`));
}
