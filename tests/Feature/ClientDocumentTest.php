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

class ClientDocumentTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        $this->client = Client::factory()->create();
        $this->project = Project::factory()->create(['client_id' => $this->client->id]);
    }

    private function upload(User $user, UploadedFile $file, string $category = 'requirement_brief')
    {
        return $this->actingAs($user)->post(route('clients.documents.store', $this->client), [
            'file' => $file, 'category' => $category, 'description' => 'Signed brief',
        ]);
    }

    public function test_marketing_uploads_to_private_disk_and_project_page_lists_it(): void
    {
        $marketing = User::factory()->marketing()->create();

        $this->upload($marketing, UploadedFile::fake()->create('brief.pdf', 200, 'application/pdf'))->assertSessionHasNoErrors()->assertRedirect();

        $document = ClientDocument::firstOrFail();
        Storage::disk('local')->assertExists($document->stored_path);
        Storage::disk('public')->assertMissing($document->stored_path);
        $this->assertStringStartsWith("clients/{$this->client->id}/", $document->stored_path);
        $this->assertSame($marketing->id, $document->uploaded_by);

        $developer = User::factory()->create();
        $this->project->members()->attach($developer);
        $this->actingAs($developer)->get(route('projects.show', $this->project))->assertOk()
            ->assertSee('Client documents')->assertSee('brief.pdf')->assertSee('Signed brief');
        $this->actingAs($developer)->get(route('client-documents.download', $document))->assertOk();
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function allowedFiles(): array
    {
        return [
            'pdf' => ['a.pdf', 'application/pdf'],
            'docx' => ['a.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'xlsx' => ['a.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'pptx' => ['a.pptx', 'application/vnd.openxmlformats-officedocument.presentationml.presentation'],
            'png' => ['a.png', 'image/png'],
            'txt' => ['a.txt', 'text/plain'],
            'zip' => ['a.zip', 'application/zip'],
        ];
    }

    #[DataProvider('allowedFiles')]
    public function test_allowed_types_are_accepted(string $name, string $mime): void
    {
        $file = $mime === 'image/png' ? UploadedFile::fake()->image($name) : UploadedFile::fake()->create($name, 10, $mime);

        $this->upload(User::factory()->admin()->create(), $file)->assertSessionHasNoErrors();
        $this->assertSame(1, ClientDocument::count());
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function rejectedFiles(): array
    {
        return [
            'exe' => ['tool.exe', 'application/x-msdownload'],
            'html' => ['page.html', 'text/html'],
            'svg' => ['logo.svg', 'image/svg+xml'],
            'php' => ['shell.php', 'application/x-php'],
            'js' => ['app.js', 'application/javascript'],
        ];
    }

    #[DataProvider('rejectedFiles')]
    public function test_dangerous_types_are_rejected(string $name, string $mime): void
    {
        $this->upload(User::factory()->marketing()->create(), UploadedFile::fake()->create($name, 10, $mime))->assertSessionHasErrors('file');
        $this->assertSame(0, ClientDocument::count());
    }

    public function test_size_limit_is_enforced(): void
    {
        $tooBig = (int) config('doc.uploads.max_kb') + 1;

        $this->upload(User::factory()->marketing()->create(), UploadedFile::fake()->create('huge.pdf', $tooBig, 'application/pdf'))->assertSessionHasErrors('file');
    }

    public function test_category_must_be_valid(): void
    {
        $this->upload(User::factory()->marketing()->create(), UploadedFile::fake()->create('a.pdf', 5, 'application/pdf'), 'nonsense')->assertSessionHasErrors('category');
    }

    public function test_developers_qa_and_management_cannot_upload(): void
    {
        foreach ([User::factory()->create(), User::factory()->reviewer()->create(), User::factory()->management()->create()] as $user) {
            $this->project->members()->syncWithoutDetaching([$user->id]);
            $this->upload($user, UploadedFile::fake()->create('a.pdf', 5, 'application/pdf'))->assertForbidden();
        }
        $this->assertSame(0, ClientDocument::count());
    }

    public function test_non_members_cannot_download_or_preview(): void
    {
        $this->upload(User::factory()->marketing()->create(), UploadedFile::fake()->create('contract.pdf', 5, 'application/pdf'), 'contract');
        $document = ClientDocument::firstOrFail();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->get(route('client-documents.download', $document))->assertForbidden();
        $this->actingAs($outsider)->get(route('client-documents.preview', $document))->assertForbidden();
        $this->actingAs($outsider)->get(route('clients.show', $this->client))->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->get(route('client-documents.download', $document))->assertRedirect(route('login'));
    }

    public function test_preview_is_inline_with_nosniff_for_pdf(): void
    {
        $this->upload(User::factory()->marketing()->create(), UploadedFile::fake()->create('brief.pdf', 5, 'application/pdf'));
        $document = ClientDocument::firstOrFail();

        $response = $this->actingAs(User::factory()->management()->create())->get(route('client-documents.preview', $document))->assertOk();
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertStringStartsWith('inline', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_only_uploader_or_client_managers_delete(): void
    {
        $uploader = User::factory()->teamLead()->create();
        $this->upload($uploader, UploadedFile::fake()->create('a.pdf', 5, 'application/pdf'));
        $document = ClientDocument::firstOrFail();

        $this->actingAs(User::factory()->teamLead()->create())->delete(route('client-documents.destroy', $document))->assertForbidden();
        $this->actingAs(User::factory()->management()->create())->delete(route('client-documents.destroy', $document))->assertForbidden();
        $this->actingAs($uploader)->delete(route('client-documents.destroy', $document))->assertRedirect();
        $this->assertSoftDeleted($document);
    }
}
