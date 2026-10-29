{{-- Expects $milestones; optional $preview. --}}
@php($preview ??= null)
<x-field name="title" label="Title" :value="$preview?->title" required class="sm:col-span-2" />
<x-field name="url" type="url" label="Preview URL" :value="$preview?->url" placeholder="https://staging.example.com" class="sm:col-span-2" />
<x-field name="version_label" label="Version" :value="$preview?->version_label" placeholder="v1, Round 2…" required />
<x-field name="milestone_id" type="select" label="Milestone" :options="$milestones" :value="$preview?->milestone_id" blank="None" />
<x-field name="review_due_at" type="date" label="Feedback due" :value="$preview?->review_due_at" />
<x-field name="feedback_summary" type="textarea" label="Feedback summary" :value="$preview?->feedback_summary" class="sm:col-span-2" />
<x-field name="internal_notes" type="textarea" label="Internal notes" :value="$preview?->internal_notes" hint="Not shared with the client." class="sm:col-span-2" />
