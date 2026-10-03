<?php

declare(strict_types=1);

namespace App\Notifications\Ledger;

use App\Notifications\Concerns\DeliversOnNotificationsQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to finance staff when the daily reconciliation finds the ledger out
 * of balance. The details are in the application log, not in the message.
 */
class LedgerReconciliationFailedNotification extends Notification implements ShouldQueue
{
    use DeliversOnNotificationsQueue, Queueable;

    public function __construct(
        public readonly int $accountMismatches,
        public readonly int $unbalancedTransactions,
        public readonly bool $trialBalanceHolds,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $locale = $this->adminLocale();

        return (new MailMessage)
            ->error()
            ->subject(__('admin.ledger_reconciliation.title', [], $locale))
            ->line($this->body($locale));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        $locale = $this->adminLocale();

        return [
            'category' => 'ledger.reconciliation_failed',
            'title' => __('admin.ledger_reconciliation.title', [], $locale),
            'body' => $this->body($locale),
            'cta_url' => null,
            'icon' => 'alert',
        ];
    }

    private function body(string $locale): string
    {
        return __('admin.ledger_reconciliation.body', [
            'accounts' => $this->accountMismatches,
            'transactions' => $this->unbalancedTransactions,
            'trial_balance' => __($this->trialBalanceHolds ? 'admin.ledger_reconciliation.balanced' : 'admin.ledger_reconciliation.unbalanced', [], $locale),
        ], $locale);
    }

    private function adminLocale(): string
    {
        return (string) config('app.admin_locale', 'ar');
    }
}
