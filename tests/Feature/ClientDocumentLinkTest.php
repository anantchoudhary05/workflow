<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientDocument;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ClientDocumentLinkTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->client = Client::factory()->create();
        $this->project = Project::factory()->create(['client_id' => $this->client->id]);
    }

    /** @param array<string, mixed> $data */
    private function store(User $user, array $data)
    {
        return $this->actingAs($user)->post(route('clients.documents.store', $this->client), $data + [
            'category' => 'requirement_brief', 'description' => 'Shared by the client',
        ]);
    }

    public function test_marketing_adds_a_link_and_project_page_lists_it(): void
    {
        $marketing = User::factory()->marketing()->create();
        $url = 'https://drive.google.com/file/d/abc123/view?usp=sharing';

        $this->store($marketing, ['url' => $url, 'title' => 'Signed brief (Drive)'])
            ->assertSessionHasNoErrors()->assertRedirect()->assertSessionHas('status', 'Link added.');

        $document = ClientDocument::firstOrFail();
        $this->assertTrue($document->isLink());
        $this->assertSame($url, $document->url);
        $this->assertSame('Signed brief (Drive)', $document->original_name);
        $this->assertNull($document->stored_path);
        $this->assertNull($document->size);
        $this->assertSame('drive.google.com', $document->host());
        $this->assertSame([], Storage::disk('local')->allFiles());

        $developer = User::factory()->create();
        $this->project->members()->attach($developer);
        $this->actingAs($developer)->get(route('projects.show', $this->project))->assertOk()
            ->assertSee('Signed brief (Drive)')->assertSee('drive.google.com')
            ->assertSee('href="'.e($url).'" target="_blank" rel="noopener noreferrer"', false)
            ->assertSee('data-doc-link', false);
        $this->actingAs($marketing)->get(route('clients.show', $this->client))->assertOk()->assertSee('Signed brief (Drive)');

        // The authorized routes redirect a link document to its URL instead of streaming a file.
        $this->actingAs($developer)->get(route('client-documents.download', $document))->assertRedirect($url);
        $this->actingAs($developer)->get(route('client-documents.preview', $document))->assertRedirect($url);
    }

    public function test_title_is_required_for_links(): void
    {
        $this->store(User::factory()->marketing()->create(), ['url' => 'https://www.dropbox.com/s/x/file.pdf'])->assertSessionHasErrors('title');
        $this->assertSame(0, ClientDocument::count());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidUrls(): array
    {
        return [
            'javascript' => ['javascript:alert(1)'],
            'javascript with slashes' => ['javascript://drive.google.com/%0Aalert(1)'],
            'data' => ['data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg=='],
            'ftp' => ['ftp://files.example.com/brief.pdf'],
            'file' => ['file:///etc/passwd'],
            'no scheme' => ['drive.google.com/file/d/abc'],
            'not a url' => ['just some text'],
            'too long' => ['https://example.com/'.str_repeat('a', 2050)],
        ];
    }

    #[DataProvider('invalidUrls')]
    public function test_invalid_urls_are_rejected(string $url): void
    {
        $this->store(User::factory()->marketing()->create(), ['url' => $url, 'title' => 'Bad'])->assertSessionHasErrors('url');
        $this->assertSame(0, ClientDocument::count());
    }

    public function test_http_and_onedrive_links_are_accepted(): void
    {
        $user = User::factory()->teamLead()->create();
        $this->store($user, ['url' => 'http://intranet.example.com/brief', 'title' => 'Intranet brief'])->assertSessionHasNoErrors();
        $this->store($user, ['url' => 'https://onedrive.live.com/?id=ABC%21123&cid=XYZ', 'title' => 'OneDrive folder'])->assertSessionHasNoErrors();
        $this->assertSame(2, ClientDocument::count());
    }

    public function test_file_and_url_together_are_rejected(): void
    {
        $this->store(User::factory()->marketing()->create(), [
            'file' => UploadedFile::fake()->create('brief.pdf', 5, 'application/pdf'),
            'url' => 'https://drive.google.com/file/d/abc/view', 'title' => 'Both',
        ])->assertSessionHasErrors('file');
        $this->assertSame(0, ClientDocument::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_neither_file_nor_url_is_rejected(): void
    {
        $this->store(User::factory()->marketing()->create(), ['title' => 'Nothing'])->assertSessionHasErrors(['file', 'url']);
        $this->assertSame(0, ClientDocument::count());
    }

    public function test_file_upload_still_defaults_to_the_file_name(): void
    {
        $this->store(User::factory()->marketing()->create(), ['file' => UploadedFile::fake()->create('contract.pdf', 5, 'application/pdf')])
            ->assertSessionHasNoErrors()->assertSessionHas('status', 'Document uploaded.');
        $document = ClientDocument::firstOrFail();
        $this->assertFalse($document->isLink());
        $this->assertSame('contract.pdf', $document->original_name);
    }

    public function test_developers_qa_and_management_cannot_add_links(): void
    {
        foreach ([User::factory()->create(), User::factory()->reviewer()->create(), User::factory()->management()->create()] as $user) {
            $this->project->members()->syncWithoutDetaching([$user->id]);
            $this->store($user, ['url' => 'https://drive.google.com/x', 'title' => 'Nope'])->assertForbidden();
        }
        $this->assertSame(0, ClientDocument::count());
    }

    public function test_outsiders_cannot_open_a_link_document(): void
    {
        $this->store(User::factory()->marketing()->create(), ['url' => 'https://drive.google.com/secret', 'title' => 'Contract']);
        $document = ClientDocument::firstOrFail();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->get(route('client-documents.download', $document))->assertForbidden();
        $this->actingAs($outsider)->get(route('client-documents.preview', $document))->assertForbidden();
        $this->actingAs($outsider)->get(route('projects.show', $this->project))->assertForbidden();
    }

    public function test_link_documents_can_be_deleted(): void
    {
        $uploader = User::factory()->marketing()->create();
        $this->store($uploader, ['url' => 'https://www.dropbox.com/s/abc/logo.zip', 'title' => 'Logo pack']);
        $document = ClientDocument::firstOrFail();

        $this->actingAs(User::factory()->teamLead()->create())->delete(route('client-documents.destroy', $document))->assertForbidden();
        $this->actingAs($uploader)->delete(route('client-documents.destroy', $document))->assertRedirect();
        $this->assertSoftDeleted($document);
        $this->actingAs($uploader)->get(route('clients.show', $this->client))->assertOk()->assertDontSee('Logo pack');
    }

    public function test_upload_dialog_offers_file_and_link_modes(): void
    {
        $this->actingAs(User::factory()->marketing()->create())->get(route('clients.show', $this->client))->assertOk()
            ->assertSee('Upload file')->assertSee('Add link')
            ->assertSee('data-source-panel="link"', false)->assertSee('name="url"', false);
    }
}
