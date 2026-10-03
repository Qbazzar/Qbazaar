@extends('admin.layout')

@section('title', __('admin.promotion_transfers.title'))
@section('heading', __('admin.promotion_transfers.title'))

@section('content')
    <p class="mb-6 text-sm text-ink-500">{{ __('admin.promotion_transfers.intro') }}</p>

    <div class="overflow-hidden rounded-2xl border border-ink-200 bg-cream-100">
        <div class="overflow-x-auto">
            <table class="w-full text-right text-sm">
                <thead class="border-b border-ink-200 bg-cream-50 text-xs font-semibold text-ink-500">
                    <tr>
                        <th class="px-4 py-3 font-semibold">{{ __('admin.promotion_transfers.ad') }}</th>
                        <th class="px-4 py-3 font-semibold">{{ __('admin.promotion_transfers.seller') }}</th>
                        <th class="px-4 py-3 font-semibold">{{ __('admin.promotion_transfers.type') }}</th>
                        <th class="px-4 py-3 font-semibold">{{ __('admin.promotion_transfers.amount') }}</th>
                        <th class="px-4 py-3 font-semibold">{{ __('admin.promotion_transfers.reference') }}</th>
                        <th class="px-4 py-3 font-semibold">{{ __('admin.promotion_transfers.requested_at') }}</th>
                        <th class="px-4 py-3 font-semibold">{{ __('admin.promotion_transfers.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-200">
                    @forelse ($promotions as $promotion)
                        <tr>
                            <td class="px-4 py-3">{{ \Illuminate\Support\Str::limit($promotion->ad?->title ?? '—', 40) }}</td>
                            <td class="px-4 py-3">{{ $promotion->user?->full_name ?? '—' }}</td>
                            <td class="px-4 py-3">{{ __("admin.promotion_transfers.types.{$promotion->type->value}") }} ({{ $promotion->duration_days }} {{ __('admin.promotion_transfers.days') }})</td>
                            <td class="px-4 py-3 font-semibold" dir="ltr">{{ $promotion->price }} {{ $promotion->currency }}</td>
                            <td class="px-4 py-3" dir="ltr">{{ $promotion->transfer_reference }}</td>
                            <td class="px-4 py-3 text-ink-500">{{ $promotion->created_at->format('Y-m-d H:i') }}</td>
                            <td class="px-4 py-3">
                                @can('finance.manage')
                                    <form method="POST" action="{{ route('admin.finance.promotions.confirm', $promotion) }}" class="mb-2">
                                        @csrf
                                        <button type="submit" class="rounded-xl bg-coral px-3 py-1.5 text-xs font-bold text-white">{{ __('admin.promotion_transfers.confirm') }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.finance.promotions.reject', $promotion) }}" class="flex items-center gap-2">
                                        @csrf
                                        <input type="text" name="reason" maxlength="500" placeholder="{{ __('admin.promotion_transfers.reason') }}"
                                               class="w-40 rounded-xl border border-ink-200 bg-cream-50 px-3 py-1.5 text-xs">
                                        <button type="submit" class="text-xs font-semibold text-red-600">{{ __('admin.promotion_transfers.reject') }}</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-16 text-center text-ink-500">{{ __('admin.promotion_transfers.empty') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6 flex justify-between text-sm font-semibold">
        @if ($promotions->previousPageUrl())
            <a href="{{ $promotions->previousPageUrl() }}" class="text-coral">{{ __('admin.promotion_transfers.previous') }}</a>
        @endif
        @if ($promotions->nextPageUrl())
            <a href="{{ $promotions->nextPageUrl() }}" class="text-coral">{{ __('admin.promotion_transfers.next') }}</a>
        @endif
    </div>
@endsection
