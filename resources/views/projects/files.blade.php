@extends('layouts.app')
@section('title', 'Files · '.$project->name)
@section('content')
    @include('projects.partials.tabs', ['active' => 'files'])
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <form method="GET">
            <select name="category" class="input w-48" data-autosubmit aria-label="Category"><option value="">All categories</option>@foreach ($categories as $value => $label)<option value="{{ $value }}" @selected(request('category') === $value)>{{ $label }}</option>@endforeach</select>
        </form>
        @if ($canUpload)<button type="button" class="btn btn-primary" data-dialog-open="upload-file"><x-icon name="upload" :size="16" /> Upload</button>@endif
    </div>
    <x-card :padded="false">
        @if ($files->isEmpty())
            <x-empty-state title="No files" icon="paperclip">Files are stored privately and only downloadable by project members.</x-empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>File</th><th>Category</th><th>Visibility</th><th>Size</th><th>Uploaded</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($files as $file)
                            <tr>
                                <td><a class="link inline-flex items-center gap-1" href="{{ route('files.download', $file) }}"><x-icon name="download" :size="14" /> {{ $file->original_name }}</a>@if ($file->description)<p class="text-xs text-muted">{{ $file->description }}</p>@endif</td>
                                <td><x-badge :enum="$file->category" /></td>
                                <td>{{ ucfirst($file->visibility) }}</td>
                                <td class="whitespace-nowrap text-muted">{{ $file->size >= 1048576 ? round($file->size / 1048576, 1).' MB' : max(1, round($file->size / 1024)).' KB' }}</td>
                                <td class="text-xs text-muted">{{ $file->uploader?->name }} · {{ $file->created_at->format('d M Y') }}</td>
                                <td class="text-right">
                                    @can('delete', $file)
                                        <form method="POST" action="{{ route('files.destroy', $file) }}" data-confirm="Remove {{ $file->original_name }}?">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm" aria-label="Remove"><x-icon name="trash" :size="14" /></button></form>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
    @if ($canUpload)
        <x-dialog id="upload-file" title="Upload file">
            <form method="POST" action="{{ route('projects.files.store', $project) }}" enctype="multipart/form-data" class="flex flex-col gap-4">
                @csrf
                <x-field name="file" type="file" label="File" :hint="'Up to '.$maxMb.' MB. '.strtoupper(str_replace(',', ', ', config('doc.uploads.mimes')))" required />
                <x-field name="category" type="select" label="Category" :options="auth()->user()->can('view-financials') ? $categories : collect($categories)->except(App\Enums\FileCategory::Quotation->value)->all()" required />
                <x-field name="visibility" type="select" label="Visibility" :options="['internal' => 'Internal only', 'client' => 'Shareable with client']" value="internal" required />
                <x-field name="description" label="Description" />
                <div class="flex justify-end gap-2"><button type="button" class="btn" data-dialog-close>Cancel</button><button class="btn btn-primary">Upload</button></div>
            </form>
        </x-dialog>
    @endif
@endsection
