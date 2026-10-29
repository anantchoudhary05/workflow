{{-- Expects $milestone (may be new), $priorities, $users, $others (id => name of other milestones). --}}
<x-field name="name" label="Name" :value="$milestone->name" required class="sm:col-span-2" />
<x-field name="owner_id" type="select" label="Owner" :options="$users" :value="$milestone->owner_id" blank="Unassigned" />
<x-field name="priority" type="select" label="Priority" :options="$priorities" :value="$milestone->priority ?? App\Enums\Priority::Medium" required />
<x-field name="start_date" type="date" label="Start" :value="$milestone->start_date" />
<x-field name="due_date" type="date" label="Due" :value="$milestone->due_date" />
<x-field name="weight" type="number" label="Weight (progress share)" :value="$milestone->weight ?? 1" min="1" max="100" required />
<x-field name="sort_order" type="number" label="Order" :value="$milestone->sort_order" min="0" />
<x-field name="description" type="textarea" label="Description" :value="$milestone->description" class="sm:col-span-2" />
<x-field name="deliverables" type="textarea" label="Deliverables" :value="$milestone->deliverables" class="sm:col-span-2" />
<x-field name="acceptance_criteria" type="textarea" label="Acceptance criteria" :value="$milestone->acceptance_criteria" class="sm:col-span-2" />
@if ($others)
    <fieldset class="sm:col-span-2">
        <legend class="label">Depends on</legend>
        @php($ownDependencies = $milestone->exists ? $milestone->dependencies->pluck('id')->all() : [])
        @php($selected = collect(old('_dialog') === ($dialog ?? null) ? old('depends_on', $ownDependencies) : $ownDependencies)->map(fn ($id) => (int) $id))
        <div class="grid gap-1 sm:grid-cols-2">
            @foreach ($others as $id => $name)
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="depends_on[]" value="{{ $id }}" @checked($selected->contains($id))> {{ $name }}</label>
            @endforeach
        </div>
        @if (old('_dialog') === ($dialog ?? null))
            @error('depends_on')<p class="error">{{ $message }}</p>@enderror
        @endif
    </fieldset>
@endif
<x-field name="requires_approval" type="checkbox" label="Requires client approval before completion" :value="$milestone->requires_approval" />
<x-field name="criteria_met" type="checkbox" label="Acceptance criteria confirmed met" :value="$milestone->criteria_met" />
<x-field name="notes" type="textarea" label="Notes" :value="$milestone->notes" class="sm:col-span-2" />
