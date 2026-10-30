@props(['tabset', 'name', 'active' => false])
<div role="tabpanel" id="{{ $tabset }}-panel-{{ $name }}" aria-labelledby="{{ $tabset }}-tab-{{ $name }}" tabindex="0" data-tab-panel="{{ $name }}" @unless ($active) hidden @endunless {{ $attributes->class('tab-panel') }}>
    {{ $slot }}
</div>
