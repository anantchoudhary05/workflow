<?php

namespace App\Http\Controllers;

use App\Enums\ClientDocumentCategory;
use App\Models\Client;
use App\Models\ClientDocument;
use App\Support\Activity;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Client documents live on the private disk (never under public/) and are only served through
 * these authorized routes. Anyone who can open the client, or any project of the client, may read them.
 */
class ClientDocumentController extends Controller
{
    /** Stores either an uploaded file or a shared cloud link: exactly one of the two is accepted. */
    public function store(Request $request, Client $client): RedirectResponse
    {
        $this->authorize('create', [ClientDocument::class, $client]);
        $data = $request->validate([
            'file' => ['nullable', 'required_without:url', 'prohibits:url', 'file', 'max:'.config('doc.uploads.max_kb'), 'mimes:'.config('doc.uploads.client_document_mimes')],
            'url' => ['nullable', 'required_without:file', 'string', 'max:2048', 'url:http,https', 'regex:#^https?://[^\s/?\#]+#i'],
            'title' => ['nullable', 'required_with:url', 'string', 'max:255'],
            'category' => ['required', Rule::enum(ClientDocumentCategory::class)],
            'description' => ['nullable', 'string', 'max:500'],
        ], [
            'file.mimes' => 'Allowed types: PDF, Word, Excel, PowerPoint, images (PNG, JPG, GIF, WebP), text and ZIP.',
            'file.required_without' => 'Choose a file to upload or add a link.',
            'url.required_without' => 'Add a link or choose a file to upload.',
            'file.prohibits' => 'Upload a file or add a link, not both.',
            'url.url' => 'Enter a full link starting with http:// or https://.',
            'url.regex' => 'Enter a full link starting with http:// or https://.',
            'title.required_with' => 'Give the link a title so the team knows what it is.',
        ]);
        $document = new ClientDocument(collect($data)->only(['category', 'description'])->all());
        $document->client_id = $client->id;
        $document->uploaded_by = $request->user()->id;

        if ($request->hasFile('file')) {
            $upload = $request->file('file');
            $document->original_name = mb_substr($upload->getClientOriginalName(), 0, 255);
            $document->mime_type = $upload->getMimeType() ?? 'application/octet-stream';
            $document->size = $upload->getSize();
            $document->stored_path = $upload->store("clients/{$client->id}", config('doc.uploads.disk'));
            $message = 'Document uploaded.';
        } else {
            $document->original_name = trim($data['title']);
            $document->url = trim($data['url']);
            $message = 'Link added.';
        }
        $document->save();
        Activity::log('client.document', sprintf('Client document %s "%s" added (%s)', $document->isLink() ? 'link' : 'file', $document->original_name, $document->category->label()), $document);

        return back()->with('status', $message);
    }

    /** Link documents redirect to their (http/https) URL after the same authorization check as files. */
    public function download(ClientDocument $document): StreamedResponse|RedirectResponse
    {
        $this->authorize('view', $document);
        if ($document->isLink()) {
            return $this->openLink($document);
        }

        return $this->disk($document)->download($document->stored_path, $document->original_name);
    }

    /** Inline view for PDFs, images and plain text only; any other type is downloaded instead. */
    public function preview(ClientDocument $document): StreamedResponse|RedirectResponse
    {
        $this->authorize('view', $document);
        if ($document->isLink()) {
            return $this->openLink($document);
        }
        if (! $document->isPreviewable()) {
            return $this->download($document);
        }

        return $this->disk($document)->response($document->stored_path, $document->original_name, [
            'Content-Type' => $document->mime_type,
            'X-Content-Type-Options' => 'nosniff', // never let the browser reinterpret the stored type as HTML
        ], 'inline');
    }

    public function destroy(ClientDocument $document): RedirectResponse
    {
        $this->authorize('delete', $document);
        $document->delete(); // soft delete; the stored object is kept for audit purposes
        Activity::log('client.document.deleted', "Client document \"{$document->original_name}\" removed", $document, [], true);

        return back()->with('status', 'Document removed.');
    }

    private function openLink(ClientDocument $document): RedirectResponse
    {
        $url = $document->safeUrl();
        abort_if($url === null, 404);

        return redirect()->away($url);
    }

    private function disk(ClientDocument $document): FilesystemAdapter
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk(config('doc.uploads.disk'));
        abort_unless($document->stored_path && $disk->exists($document->stored_path), 404);

        return $disk;
    }
}
