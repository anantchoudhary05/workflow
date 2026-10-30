{{--
    Administrator-only "Delete" button + confirm dialog with a required reason (soft delete; restorable from Trash).
    Params: $id (dialog id), $action (DELETE url), $label (what is deleted), $note (optional extra line), $small (bool).
--}}
@can('delete-records')
    <button type="button" class="btn btn-danger {{ ($small ?? false) ? 'btn-sm' : '' }}" data-dialog-open="{{ $id }}" data-delete-open>
        <x-icon name="trash" :size="16" /> Delete
    </button>
    <x-dialog :id="$id" :title="'Delete '.$label.'?'" icon="trash">
        <form method="POST" action="{{ $action }}" class="grid gap-4 text-left" data-delete-form>
            @csrf
            @method('DELETE')
            <p class="text-sm text-muted">
                It will be hidden everywhere (lists, dashboards, reports, search). Nothing is erased: an administrator can restore it from
                <strong class="text-ink">Trash</strong>. The reason is kept in the record's history.
            </p>
            @if (! empty($note))<div class="alert tone-warning text-sm">{{ $note }}</div>@endif
            <x-field name="reason" type="textarea" label="Why is it being deleted?" rows="3" required minlength="5" maxlength="1000"
                placeholder="e.g. Duplicate of another record, created by mistake, client withdrew…" hint="Required, at least 5 characters." />
            <div class="flex justify-end gap-2">
                <button type="button" class="btn" data-dialog-close>Cancel</button>
                <button class="btn btn-danger"><x-icon name="trash" :size="16" /> Delete</button>
            </div>
        </form>
    </x-dialog>
@endcan
