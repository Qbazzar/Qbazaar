<section aria-labelledby="user-wallet-title" class="grid gap-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 id="user-wallet-title" class="text-[15px] font-bold text-ink-900">أرصدة المحفظة</h2>
        <x-admin.button variant="secondary" size="sm" icon="banknotes" :href="route('admin.finance.statements.show', $user)">كشف الحساب الكامل</x-admin.button>
    </div>

    @include('admin.finance.partials.wallet-summary', ['summary' => $summary])
</section>
