@php
    // Collect flash messages into a uniform list of {type, message} so the same
    // markup renders success, error, and validation summaries.
    $toasts = [];
    if (session('status')) {
        $toasts[] = ['type' => 'success', 'message' => session('status')];
    }
    if (session('error')) {
        $toasts[] = ['type' => 'error', 'message' => session('error')];
    }
    if ($errors->any()) {
        $toasts[] = ['type' => 'error', 'message' => $errors->first()];
    }
    $styles = [
        'success' => ['ring' => 'ring-emerald-200', 'bar' => 'bg-emerald-500', 'icon' => 'text-emerald-600', 'glyph' => 'check'],
        'error' => ['ring' => 'ring-red-200', 'bar' => 'bg-red-500', 'icon' => 'text-red-600', 'glyph' => 'x-circle'],
    ];
@endphp

@if ($toasts !== [])
    <div class="pointer-events-none fixed inset-x-0 top-4 z-50 flex flex-col items-center gap-2 px-4">
        @foreach ($toasts as $toast)
            @php($s = $styles[$toast['type']])
            <div
                data-toast
                class="qb-toast pointer-events-auto flex w-full max-w-md items-start gap-3 overflow-hidden rounded-2xl bg-white p-4 shadow-lg ring-1 {{ $s['ring'] }}"
                role="status"
            >
                <span class="mt-0.5 shrink-0 {{ $s['icon'] }}">
                    <x-manage.icon :name="$s['glyph']" class="size-5" />
                </span>
                <p class="flex-1 text-sm font-semibold leading-relaxed text-ink-900">{{ $toast['message'] }}</p>
                <button type="button" class="shrink-0 text-ink-300 transition hover:text-ink-700" onclick="this.closest('[data-toast]').remove()" aria-label="إغلاق">
                    <x-manage.icon name="x-circle" class="size-5" />
                </button>
                <span class="qb-toast__bar absolute bottom-0 right-0 h-0.5 {{ $s['bar'] }}"></span>
            </div>
        @endforeach
    </div>

    <style>
        .qb-toast { position: relative; animation: qb-toast-in .28s cubic-bezier(.16,1,.3,1) both; }
        .qb-toast__bar { animation: qb-toast-bar 5s linear forwards; }
        @keyframes qb-toast-in { from { opacity: 0; transform: translateY(-12px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes qb-toast-bar { from { width: 100%; } to { width: 0%; } }
        @media (prefers-reduced-motion: reduce) {
            .qb-toast, .qb-toast__bar { animation: none; }
        }
    </style>
    <script>
        // Auto-dismiss each toast after its progress bar completes (5s). No deps.
        document.querySelectorAll('[data-toast]').forEach(function (el) {
            setTimeout(function () {
                el.style.transition = 'opacity .3s, transform .3s';
                el.style.opacity = '0';
                el.style.transform = 'translateY(-12px)';
                setTimeout(function () { el.remove(); }, 300);
            }, 5000);
        });
    </script>
@endif
