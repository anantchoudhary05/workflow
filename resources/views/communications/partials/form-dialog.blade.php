{{-- Expects $clients (id => name), $projects (id => label), $types; optional $clientId / $projectId preselects. --}}
<x-dialog id="log-communication" title="Log communication">
    <form method="POST" action="{{ route('communications.store') }}" class="grid gap-4 sm:grid-cols-2">
        @csrf
        <x-field name="client_id" type="select" label="Client" :options="$clients" :value="$clientId ?? null" blank="Select client" required />
        <x-field name="project_id" type="select" label="Project" :options="$projects" :value="$projectId ?? null" blank="Not project specific" hint="Must belong to the selected client." />
        <x-field name="type" type="select" label="Type" :options="$types" required />
        <x-field name="direction" type="select" label="Direction" :options="['outbound' => 'Outbound (we contacted them)', 'inbound' => 'Inbound (they contacted us)']" required />
        <x-field name="subject" label="Subject" required class="sm:col-span-2" />
        <x-field name="occurred_at" type="datetime-local" label="When" :value="now()" required />
        <x-field name="outcome" label="Outcome" />
        <x-field name="summary" type="textarea" label="Summary" rows="4" class="sm:col-span-2" />
        <x-field name="next_action" label="Next action" class="sm:col-span-2" />
        <div class="flex justify-end gap-2 sm:col-span-2"><button type="button" class="btn" data-dialog-close>Cancel</button><button class="btn btn-primary">Save</button></div>
    </form>
</x-dialog>
