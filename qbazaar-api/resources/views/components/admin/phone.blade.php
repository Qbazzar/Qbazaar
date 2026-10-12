@props(['phone' => null])

{{-- Isolated left-to-right so the leading "+" stays in front inside RTL text. --}}
<span dir="ltr" {{ $attributes->class('inline-block [unicode-bidi:isolate]') }}>{{ \App\Support\PhoneFormat::display($phone) }}</span>
