{{-- Shared requirement inputs. Expects $requirement (may be new), $categories, $priorities, $users. --}}
<x-field name="title" label="Title" :value="$requirement->title" required class="sm:col-span-2" />
<x-field name="category" type="select" label="Category" :options="$categories" :value="$requirement->category" required />
<x-field name="priority" type="select" label="Priority" :options="$priorities" :value="$requirement->priority ?? App\Enums\Priority::Medium" required />
<x-field name="description" type="textarea" label="Description" :value="$requirement->description" class="sm:col-span-2" />
<x-field name="acceptance_criteria" type="textarea" label="Acceptance criteria" :value="$requirement->acceptance_criteria" hint="How the client and QA will know it is done." class="sm:col-span-2" />
<x-field name="module" label="Module / page" :value="$requirement->module" />
<x-field name="estimated_hours" type="number" label="Estimated hours" :value="$requirement->estimated_hours" min="0" step="0.5" />
<x-field name="requested_by" label="Requested by" :value="$requirement->requested_by" />
<x-field name="source" label="Source" :value="$requirement->source" placeholder="Kickoff call, email…" />
<x-field name="owner_id" type="select" label="Internal owner" :options="$users" :value="$requirement->owner_id" blank="Unassigned" />
<x-field name="notes" type="textarea" label="Notes" :value="$requirement->notes" class="sm:col-span-2" />
