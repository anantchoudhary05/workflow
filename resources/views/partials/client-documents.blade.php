{{--
    Client documents list with optional upload dialog.
    @param Client $client
    @param Collection $documents
    @param bool $canUpload
--}}
<x-card title="Client documents" icon="paperclip" :padded="false" id="client-documents">
    <x-slot:actions>
        <span class="text-xs text-muted">{{ $client->name }}</span>
        @if ($canUpload)
            <button type="button" class="btn btn-sm" data-dialog-open="upload-client-document"><x-icon name="upload" :size="14" /> Add document</button>
        @endif
    </x-slot:actions>
    <ul class="divide-y divide-line">
        @forelse ($documents as $document)
            <li class="flex items-start justify-between gap-3 px-4 py-2.5" @if ($document->isLink()) data-doc-link @endif>
                <div class="min-w-0">
                    @if ($document->isLink())
                        <p class="flex flex-wrap items-center gap-2">
                            <x-icon name="link" :size="14" class="text-muted" />
                            <a class="link truncate font-medium" href="{{ $document->safeUrl() }}" target="_blank" rel="noopener noreferrer">{{ $document->original_name }}</a>
                            <x-badge :enum="$document->category" />
                        </p>
                        @if ($document->description)<p class="mt-0.5 text-xs">{{ $document->description }}</p>@endif
                        <p class="text-xs text-muted">Link · {{ $document->host() ?? 'external' }} · {{ $document->uploader?->name ?? 'Unknown' }} · {{ $document->created_at?->format('d M Y') }}</p>
                    @else
                        <p class="flex flex-wrap items-center gap-2">
                            <x-icon name="file-text" :size="14" class="text-muted" />
                            <a class="link truncate font-medium" href="{{ route($document->isPreviewable() ? 'client-documents.preview' : 'client-documents.download', $document) }}" @if ($document->isPreviewable()) target="_blank" rel="noopener" @endif>{{ $document->original_name }}</a>
                            <x-badge :enum="$document->category" />
                        </p>
                        @if ($document->description)<p class="mt-0.5 text-xs">{{ $document->description }}</p>@endif
                        <p class="text-xs text-muted">{{ strtoupper($document->extension()) }} · {{ $document->humanSize() }} · {{ $document->uploader?->name ?? 'Unknown' }} · {{ $document->created_at?->format('d M Y') }}</p>
                    @endif
                </div>
                <div class="flex shrink-0 items-center gap-1">
                    @if ($document->isLink())
                        <a class="btn btn-ghost btn-sm" href="{{ $document->safeUrl() }}" target="_blank" rel="noopener noreferrer" aria-label="Open {{ $document->original_name }} in a new tab"><x-icon name="external" :size="14" /></a>
                    @else
                        <a class="btn btn-ghost btn-sm" href="{{ route('client-documents.download', $document) }}" aria-label="Download {{ $document->original_name }}"><x-icon name="download" :size="14" /></a>
                    @endif
                    @can('delete', $document)
                        <form method="POST" action="{{ route('client-documents.destroy', $document) }}" data-confirm="Remove {{ $document->original_name }}?">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm" aria-label="Remove {{ $document->original_name }}"><x-icon name="trash" :size="14" /></button></form>
                    @endcan
                </div>
            </li>
        @empty
            <li><x-empty-state title="No client documents yet" icon="paperclip">Requirement briefs, contracts, client details and design references from Marketing appear here, as uploaded files or shared cloud links.</x-empty-state></li>
        @endforelse
    </ul>
</x-card>

@if ($canUpload)
    @php
        $linkMode = old('_dialog') === 'upload-client-document' && old('source') === 'link';
    @endphp
    <x-dialog id="upload-client-document" title="Add client document" icon="upload">
        <form method="POST" action="{{ route('clients.documents.store', $client) }}" enctype="multipart/form-data" class="flex flex-col gap-4" data-doc-source-form>
            @csrf
            <fieldset>
                <legend class="label">Source</legend>
                <div class="flex flex-wrap gap-2" role="radiogroup">
                    <label class="btn btn-sm has-[:checked]:border-brand has-[:checked]:bg-brand-soft has-[:checked]:text-brand"><input type="radio" name="source" value="file" class="size-3.5" @checked(! $linkMode) data-doc-source> <x-icon name="upload" :size="14" /> Upload file</label>
                    <label class="btn btn-sm has-[:checked]:border-brand has-[:checked]:bg-brand-soft has-[:checked]:text-brand"><input type="radio" name="source" value="link" class="size-3.5" @checked($linkMode) data-doc-source> <x-icon name="link" :size="14" /> Add link</label>
                </div>
            </fieldset>
            <div data-source-panel="file" @if ($linkMode) hidden @endif>
                <x-field name="file" type="file" label="File" required hint="PDF, Word, Excel, PowerPoint, PNG/JPG/GIF/WebP, TXT or ZIP · max {{ (int) (config('doc.uploads.max_kb') / 1024) }} MB. Stored privately." accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.png,.jpg,.jpeg,.gif,.webp,.txt,.zip" :disabled="$linkMode" />
            </div>
            <div class="flex flex-col gap-4" data-source-panel="link" @if (! $linkMode) hidden @endif>
                <x-field name="url" type="url" label="Link" required placeholder="https://drive.google.com/..." hint="A shared link from Google Drive, OneDrive, Dropbox or similar (http/https). Check the link's sharing settings so the team can open it." maxlength="2048" :disabled="! $linkMode" />
                <x-field name="title" label="Title" required placeholder="e.g. Signed requirement brief" maxlength="255" :disabled="! $linkMode" />
            </div>
            <x-field name="category" type="select" label="Category" :options="App\Enums\ClientDocumentCategory::options()" required />
            <x-field name="description" type="textarea" label="Description" placeholder="What is in this document and what should the team use it for?" />
            <div class="flex justify-end gap-2"><button type="button" class="btn" data-dialog-close>Cancel</button><button class="btn btn-primary">Save</button></div>
        </form>
    </x-dialog>
@endif
