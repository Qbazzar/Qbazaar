# QBazaar ledger: where every riyal goes

> **Status:** stage A of M1b (BE-14.33, BE-14.35, BE-14.36, BE-14.38). The code lives in `qbazaar-api/app/Services/Ledger/`. The flows marked *later* are already implemented and tested as `LedgerRecipes` methods; stage B (settlements, withdrawals, promotions) and M7 (electronic payment) only call them.

## 1. The idea in one paragraph

QBazaar keeps **double-entry books**, like an accountant. Money never appears or disappears: every movement is one **transaction** made of two or more **entries**, and in every transaction the debits equal the credits. Each user and the platform have **accounts**; an account's balance is the sum of its entries. Nothing in the books is ever edited or deleted: a mistake is fixed by a new transaction that reverses it, so the full history is always there.

## 2. Chart of accounts

Seen from QBazaar's books. *Debit-normal* accounts grow with debits (what we hold or are owed); *credit-normal* accounts grow with credits (what we owe or have earned).

| Code | Account | Normal side | Can go below zero? | Meaning |
|---|---|---|---|---|
| `platform:bank` | Bank | debit | yes | QBazaar's bank account |
| `platform:gateway_clearing` | Gateway clearing | debit | yes | Card money a payment gateway collected and has not paid to the bank yet (M7) |
| `platform:escrow` | Escrow | credit | **no** | Buyer money held until the handover (bank transfer / gateway only, never cash) |
| `platform:payouts_payable` | Payouts payable | credit | **no** | Withdrawals approved but not yet transferred |
| `platform:revenue` | Revenue | credit | yes | Commission and promotion income |
| `platform:adjustments` | Adjustments | debit | yes | The other side of manual admin corrections |
| `user:{id}:wallet` | User wallet | credit | **no** | What QBazaar owes the user: money they can withdraw or spend |
| `user:{id}:commission_receivable` | Commission owed | debit | **no** | What the user owes QBazaar: commission on cash sales |

Accounts open by themselves the first time they are used. A posting that would push a "no" account below zero is refused: a user cannot spend more than their wallet (`WALLET_001`) or pay more commission than they owe (`WALLET_002`).

## 3. Every money flow

Amounts in the examples: an item sold for **200.00 QAR**, shipping **10.00**, commission **5% = 10.00** (on the item price only, never on shipping).

### Now: cash orders (no money passes through QBazaar)

| # | When | Transaction type | Debit | Credit | Example |
|---|---|---|---|---|---|
| 1 | The seller confirms the handover of a cash order | `commission_charged` | `user:seller:commission_receivable` | `platform:revenue` | 10.00 |
| 2 | The seller pays the commission by bank transfer (admin approves, stage B) | `commission_settled` | `platform:bank` | `user:seller:commission_receivable` | 10.00 |
| 3 | Netting: the debt is paid out of the seller's own wallet | `commission_settled` | `user:seller:wallet` | `user:seller:commission_receivable` | 10.00 |
| 3a | An admin cancels a completed cash sale after a dispute | `commission_refunded` | `platform:revenue` 10.00 | `user:seller:commission_receivable` (what is still owed) **and** `user:seller:wallet` (any part already paid) | 10.00 |

After 1 the seller owes 10.00; after 2 or 3 they owe nothing. 2 is posted when finance staff approve the seller's transfer (`POST /account/wallet/settlements`, `method=bank_transfer`, key `settlement:{id}:commission_settled`); 3 is posted at once (`method=wallet`). 3a runs once per order (`order:{id}:commission_refunded`) and only when 1 was posted. While what they owe is at or above the **debt ceiling** (admin setting), they cannot take new orders (`ORDER_004`).

### Later: escrow orders (bank transfer or card, M7)

| # | When | Transaction type | Debit | Credit | Example |
|---|---|---|---|---|---|
| 4 | The buyer's payment arrives | `order_payment_received` | `platform:gateway_clearing` (card) or `platform:bank` (transfer) | `platform:escrow` | 210.00 |
| 5 | The handover is confirmed | `escrow_released` | `platform:escrow` 210.00 | `user:seller:wallet` 200.00 **and** `platform:revenue` 10.00 | 210.00 |
| 6 | The order is cancelled or the buyer wins a dispute | `refund` | `platform:escrow` | the account the payment came in by (`platform:gateway_clearing` or `platform:bank`) | 210.00 |

5 and 6 run only after 4, and an escrow is either released or refunded, never both: they share one idempotency key (`order:{id}:outcome`), so the database accepts only the first.

### Stage B: withdrawals

| # | When | Transaction type | Debit | Credit |
|---|---|---|---|---|
| 6a | A seller who owes commission requests a withdrawal: the debt is netted first, in the same transaction (key `withdrawal:{id}:commission_settled`) | `commission_settled` | `user:seller:wallet` | `user:seller:commission_receivable` |
| 7 | The seller requests a withdrawal (money leaves the wallet at once, so it cannot be spent twice) | `withdrawal_requested` | `user:seller:wallet` | `platform:payouts_payable` |
| 8 | The admin pays it (always the amount requested in 7) | `withdrawal_paid` | `platform:payouts_payable` | `platform:bank` |
| 9 | The admin rejects it | `withdrawal_rejected` (reversal of 7) | `platform:payouts_payable` | `user:seller:wallet` |

A withdrawal is either paid or rejected, never both: 8 and 9 share one idempotency key (`withdrawal:{id}:outcome`). The seller requests 7 with `POST /account/wallet/withdrawals`; finance staff (`finance.manage`) post 8 or 9 from `/admin/finance/withdrawals`. The withdrawal row is locked before the ledger accounts and keeps its own encrypted copy of the IBAN, so the destination cannot change after the request.

The **withdrawable amount** is the wallet balance minus the commission receivable, never below zero (`withdrawable_balance` in the wallet summary). A request above it is refused with `WALLET_003`, so a debt cannot be withdrawn away. 6a nets the whole debt, except the part a bank transfer awaiting review covers (that transfer must stay approvable), then 7 posts the requested amount. The seller's accounts are locked in id order before the check, so concurrent requests are checked one after the other.

### Stage B: paid promotion

| # | When | Transaction type | Debit | Credit |
|---|---|---|---|---|
| 10 | Paid from the wallet | `promotion_purchased` | `user:wallet` | `platform:revenue` |
| 11 | Paid by bank transfer (admin confirms) | `promotion_purchased` | `platform:bank` | `platform:revenue` |

### Corrections

| # | When | Transaction type | Debit | Credit |
|---|---|---|---|---|
| 12 | Admin credits a wallet | `adjustment` | `platform:adjustments` | `user:wallet` |
| 13 | Admin debits a wallet | `adjustment` | `user:wallet` | `platform:adjustments` |
| 14 | Any transaction posted by mistake | `reversal` | the original's credits | the original's debits |

A transaction can be reversed only once, and a reversal is never reversed (post a new transaction instead).

## 4. The full cycle, account to account

```
Cash (now)                                  Escrow (M7)
──────────                                  ───────────
buyer ──cash──► seller                      buyer ──card──► gateway_clearing ──► escrow
                  │                                                              │ handover
   handover ──► seller owes commission                         seller wallet ◄──┤ 200.00
                (commission_receivable)                        revenue      ◄──┘  10.00
                  │                                                  │
   settlement ──► bank ◄── seller pays                  withdrawal ──► payouts_payable ──► bank ──► seller's IBAN
```

## 5. The guarantees

| Guarantee | How |
|---|---|
| No money is created or lost | Every transaction is checked to balance (debits = credits, all amounts > 0) before anything is written |
| No rounding errors | No floats anywhere: amounts are exact decimal strings (`brick/math`), stored as `decimal(14,2)` (orders: `decimal(12,2)`). The one rounding rule, half-up to 0.01, is applied only to the commission |
| All or nothing | The journal row, its entries and the account balances are written in one database transaction, together with the order status change that caused them |
| Never twice | Every flow has an idempotency key built from its business record (`order:{id}:commission_charged`); a retried request or job gets the first transaction back. The key is unique in the database, and a retry that names other accounts or amounts under the same key is refused |
| No race between two requests | The accounts a transaction touches are locked (`SELECT … FOR UPDATE`) in a fixed order (by id), so two postings never deadlock and never read a stale balance. Orders lock the ad first, then the order, then the accounts: the same ad-first order the offer flow uses. CI proves these locks on a real MySQL on every pull request |
| History cannot be rewritten | Transactions and entries cannot be updated or deleted through the application, neither one row at a time nor in bulk; corrections are reversals |
| Balances can be proved | Each entry stores the balance right after it, and a daily job (02:30 Qatar time) recomputes every balance from its entries, checks every transaction balances, checks every entry has exactly one side, and checks that all debits ever posted equal all credits. Any difference is logged and sent to every admin with `finance.view` |

## 6. Commission

- **General rate:** `/admin/settings` → Commission rate (percent, default 5.00).
- **Per category (optional):** on the same page. A category without its own rate uses its parent's, then the general rate.
- **Base:** item price × quantity; shipping is not commissioned.
- **Rounding:** half-up to 0.01 QAR (2.5% of 10.10 = 0.2525 → 0.25; of 10.30 = 0.2575 → 0.26).
- **Frozen:** the rate and amount are stored on the order when it is placed. Changing the rate later only affects new orders. Every change is in the activity log with the admin who made it.

## 7. Order states

```
created ──► awaiting_handover ──► completed
   │               │    │              │ (cash, within the report window)
   │               │    └──► disputed ◄┘──► completed | cancelled   (admin ruling)
   └───────────────┴──► cancelled
```

- **created:** an offer (or, in stage B, a purchase request) was accepted; the ad is reserved. One open order per ad, enforced by a unique database index.
- **awaiting_handover:** checkout is done (stage B, BE-14.37).
- **completed:** the seller confirmed the handover (`POST /orders/{id}/confirm-handover`); the ledger posting runs in the same transaction and the ad is marked sold.
- **cancelled:** either side cancelled before the handover (`POST /orders/{id}/cancel`); the ad's reservation is released.
- **disputed:** the buyer reported a problem (`POST /orders/{id}/report-problem`, with a reason) within the window after the handover; only an admin with `finance.manage` rules on it, from `/admin/finance/disputes`, with a written reason kept on the order and in the activity log.

## 8. Disputes and timers (owner decisions)

| Rule | Setting (`/admin/settings`, group Orders) | Default |
|---|---|---|
| A cash order is completed by the **seller's** confirmation only; the buyer can then report a problem for this many hours | `order_dispute_window_hours` | 48 |
| An escrow order (M7) is released to the seller this many days after the handover when the buyer neither confirms (`POST /orders/{id}/confirm-receipt`) nor disputes | `escrow_auto_release_days` | 3 |

- **Ruling for the seller:** the order is completed through the gateway's `settleHandover` (cash: the commission, already charged, is not charged again thanks to its key; escrow: flow 5).
- **Ruling for the buyer:** the order is cancelled through the gateway's `settleCancellation` (cash: flow 3a gives the commission back; escrow: flow 6 refunds the buyer). A sold ad stays sold.
- **Auto-release:** `ReleaseDueEscrowOrdersJob` runs hourly on the `low` queue (unique, never overlapping), reads due order ids in chunks and queues `ReleaseEscrowOrdersBatchJob` batches, each completing its orders through `OrderTransitionService::complete()` with the same locks and keys as a manual completion. A disputed order is skipped. With no escrow gateway configured (before M7) it does nothing; cash orders never wait for it.
- The admin never releases or completes individual sales outside a dispute; staff only rule on disputes and approve settlements and withdrawals.
