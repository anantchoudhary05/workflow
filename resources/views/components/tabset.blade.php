@props(['name', 'tabs', 'selected' => null, 'title' => null, 'icon' => null, 'label' => null])
{{--
    Card with accessible tabs (resources/js/modules/tabs.js). $tabs = ['key' => ['label' => …, 'count' => ?int, 'icon' => ?string]].
    Put one <x-tab-panel tabset="{{ name }}" name="key" :active="…"> per tab in the slot. Every panel is rendered on the
    server, so nothing depends on JavaScript to exist; tabs only choose which one is visible.
--}}
@php($selected = $selected !== null && array_key_exists($selected, $tabs) ? $selected : array_key_first($tabs))
<section {{ $attributes->class('card tabset') }} data-tabs="{{ $name }}">
    @if ($title || isset($actions))
        <header class="card-head">
            <h2 class="text-sm">@if ($icon)<span class="card-icon"><x-icon :name="$icon" :size="15" /></span>@endif{{ $title }}</h2>
            @isset($actions)<div class="flex items-center gap-2 text-sm font-normal">{{ $actions }}</div>@endisset
        </header>
    @endif
    <div role="tablist" aria-label="{{ $label ?? $title }}" class="tabset-list">
        @foreach ($tabs as $key => $tab)
            <button type="button" role="tab" id="{{ $name }}-tab-{{ $key }}" class="tab" aria-controls="{{ $name }}-panel-{{ $key }}"
                aria-selected="{{ $key === $selected ? 'true' : 'false' }}" tabindex="{{ $key === $selected ? 0 : -1 }}" data-tab="{{ $key }}">
                @if (! empty($tab['icon']))<x-icon :name="$tab['icon']" :size="14" />@endif
                {{ $tab['label'] }}
                @if (isset($tab['count']))<span @class(['tab-count', 'tab-count-alert' => ! empty($tab['alert']) && $tab['count'] > 0])>{{ $tab['count'] }}</span>@endif
            </button>
        @endforeach
    </div>
    {{ $slot }}
</section>
