@props(['title' => null, 'padded' => true, 'icon' => null])
<section {{ $attributes->class('card') }}>
    @if ($title || isset($actions))
        <header class="card-head">
            <h2 class="text-sm">@if ($icon)<span class="card-icon"><x-icon :name="$icon" :size="15" /></span>@endif{{ $title }}</h2>
            @isset($actions)<div class="flex items-center gap-2 text-sm font-normal">{{ $actions }}</div>@endisset
        </header>
    @endif
    <div @class(['card-body' => $padded])>{{ $slot }}</div>
</section>
