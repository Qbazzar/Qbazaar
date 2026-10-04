{{-- Any form with data-confirm="question" asks here before submitting; data-confirm-tone="primary" marks a non-destructive action. --}}
<dialog id="qb-confirm" aria-labelledby="qb-confirm-title" aria-describedby="qb-confirm-message"
        class="m-auto w-[calc(100%-2rem)] max-w-sm rounded-2xl bg-cream-100 p-6 text-ink-900 shadow-xl backdrop:bg-black/40">
    <form method="dialog">
        <h2 id="qb-confirm-title" class="text-base font-bold">تأكيد الإجراء</h2>
        <p id="qb-confirm-message" class="mt-2 text-sm leading-relaxed text-ink-700"></p>
        <div class="mt-6 flex flex-wrap justify-end gap-2">
            <x-admin.button variant="secondary" value="cancel" autofocus>إلغاء</x-admin.button>
            <x-admin.button variant="danger" value="confirm" data-confirm-accept="danger">تأكيد</x-admin.button>
            <x-admin.button variant="primary" value="confirm" data-confirm-accept="primary">تأكيد</x-admin.button>
        </div>
    </form>
</dialog>

<script>
    (function () {
        var dialog = document.getElementById('qb-confirm');
        var message = document.getElementById('qb-confirm-message');
        var pending = null;

        document.addEventListener('submit', function (event) {
            var form = event.target;
            if (!(form instanceof HTMLFormElement) || !form.dataset.confirm) return;

            if (form.dataset.confirmed === '1') {
                delete form.dataset.confirmed;
                return;
            }

            event.preventDefault();

            if (typeof dialog.showModal !== 'function') {
                if (window.confirm(form.dataset.confirm)) submit(form, event.submitter);
                return;
            }

            var tone = form.dataset.confirmTone === 'primary' ? 'primary' : 'danger';
            dialog.querySelectorAll('[data-confirm-accept]').forEach(function (button) {
                button.hidden = button.dataset.confirmAccept !== tone;
            });
            message.textContent = form.dataset.confirm;
            pending = { form: form, submitter: event.submitter, trigger: event.submitter || document.activeElement };
            dialog.returnValue = '';
            dialog.showModal();
        });

        dialog.addEventListener('close', function () {
            var request = pending;
            pending = null;
            if (!request) return;
            if (request.trigger && typeof request.trigger.focus === 'function') request.trigger.focus();
            if (dialog.returnValue === 'confirm') submit(request.form, request.submitter);
        });

        function submit(form, submitter) {
            form.dataset.confirmed = '1';
            if (submitter) form.requestSubmit(submitter); else form.requestSubmit();
        }
    })();
</script>
