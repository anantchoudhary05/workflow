<?php

namespace App\Http\Controllers;

use App\Enums\FileCategory;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Support\Activity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Project files live on a private disk and are only served through an authorized download route. */
class FileController extends Controller
{
    public function index(Request $request, Project $project): View
    {
        $this->authorize('view', $project);
        $user = $request->user();

        return view('projects.files', [
            'project' => $project,
            'files' => $project->files()->with('uploader:id,name')
                ->when(! $user->can('view-financials'), fn ($q) => $q->where('category', '!=', FileCategory::Quotation))
                ->when($request->query('category'), fn ($q, $c) => $q->where('category', $c))
                ->latest()->get(),
            'categories' => FileCategory::options(),
            'canUpload' => $user->can('project-ability', [$project, 'upload-files']),
            'maxMb' => (int) (config('doc.uploads.max_kb') / 1024),
        ]);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('project-ability', [$project, 'upload-files']);
        $data = $request->validate([
            'file' => ['required', 'file', 'max:'.config('doc.uploads.max_kb'), 'mimes:'.config('doc.uploads.mimes')],
            'category' => ['required', Rule::enum(FileCategory::class)],
            'visibility' => ['required', Rule::in(['internal', 'client'])],
            'description' => ['nullable', 'string', 'max:255'],
        ]);
        if ($data['category'] === FileCategory::Quotation->value) {
            $this->authorize('view-financials');
        }
        $upload = $request->file('file');
        $file = new ProjectFile(collect($data)->except('file')->all());
        $file->project_id = $project->id;
        $file->uploaded_by = $request->user()->id;
        $file->original_name = mb_substr($upload->getClientOriginalName(), 0, 255);
        $file->mime_type = $upload->getMimeType() ?? 'application/octet-stream';
        $file->size = $upload->getSize();
        $file->stored_path = $upload->store("projects/{$project->id}", config('doc.uploads.disk'));
        $file->save();
        Activity::log('file.uploaded', "File \"{$file->original_name}\" uploaded ({$file->category->label()})", $file);

        return back()->with('status', 'File uploaded.');
    }

    public function download(ProjectFile $file): StreamedResponse
    {
        $this->authorize('view', $file);
        $disk = Storage::disk(config('doc.uploads.disk'));
        abort_unless($disk->exists($file->stored_path), 404);

        return $disk->download($file->stored_path, $file->original_name);
    }

    public function destroy(ProjectFile $file): RedirectResponse
    {
        $this->authorize('delete', $file);
        $file->delete(); // soft delete; the stored object is kept for audit purposes
        Activity::log('file.deleted', "File \"{$file->original_name}\" removed", $file, [], true);

        return back()->with('status', 'File removed.');
    }
}
