@props(['tone' => 'neutral', 'enum' => null, 'dot' => true])
{{-- Pass an enum (uses its label/tone) or a tone with slot text. A small status dot is shown unless dot="false". --}}
<span {{ $attributes->class(['badge', 'tone-'.($enum?->tone() ?? $tone)]) }}>@if ($dot)<span class="badge-dot" aria-hidden="true"></span>@endif{{ $enum?->label() ?? $slot }}</span>
