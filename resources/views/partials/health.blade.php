{{-- Expects $health = ['label', 'tone', 'reasons'] from ProjectHealth::evaluate(). --}}
<span class="badge tone-{{ $health['tone'] }}" title="{{ implode('; ', $health['reasons']) }}">{{ $health['label'] }}</span>
