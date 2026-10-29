<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Models\Client;
use App\Models\ClientAction;
use App\Models\Preview;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\SubmitsForms;
use Tests\TestCase;

/**
 * One regression test per bug fixed in the "status / active projects" round.
 */
class BugRegressionTest extends TestCase
{
    use RefreshDatabase;
    use SubmitsForms;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_client_duplicate_warning_renders_after_redirect(): void
    {
        Client::factory()->create(['name' => 'Asha Verma', 'email' => 'asha@example.test']);

        $this->actingAs($this->admin)->from(route('clients.create'))
            ->post(route('clients.store'), ['name' => 'Asha Verma', 'email' => 'asha@example.test', 'status' => 'active'])
            ->assertRedirect(route('clients.create'));

        // Sessions use JSON serialization, so whatever is flashed must survive a JSON round trip (models did not).
        $flashed = session('duplicates');
        $this->assertIsArray($flashed);
        $this->assertSame($flashed, json_decode(json_encode($flashed), true));

        $this->get(route('clients.create'))->assertOk()->assertSee('asha@example.test');
        $this->assertSame(1, Client::count());
    }

    public function test_project_create_keeps_the_chosen_active_status(): void
    {
        $client = Client::factory()->create();

        $this->actingAs($this->admin)->post(route('projects.store'), [
            'name' => 'Shop', 'client_id' => $client->id, 'type' => 'ecommerce', 'priority' => 'medium',
            'manager_id' => $this->admin->id, 'status' => 'in_progress',
        ])->assertSessionHasNoErrors();

        $project = Project::where('name', 'Shop')->sole();
        $this->assertSame(ProjectStatus::InProgress, $project->status);
        $this->assertDatabaseHas('project_status_histories', ['project_id' => $project->id, 'to_status' => 'in_progress', 'is_override' => true]);
    }

    public function test_project_create_without_status_starts_as_lead_and_rejects_non_initial_statuses(): void
    {
        $client = Client::factory()->create();
        $base = ['client_id' => $client->id, 'type' => 'ecommerce', 'priority' => 'medium', 'manager_id' => $this->admin->id];

        $this->actingAs($this->admin)->post(route('projects.store'), $base + ['name' => 'No status'])->assertSessionHasNoErrors();
        $this->assertSame(ProjectStatus::Lead, Project::where('name', 'No status')->sole()->status);

        $this->post(route('projects.store'), $base + ['name' => 'Closed?', 'status' => 'closed'])->assertSessionHasErrors('status');
        $this->assertDatabaseMissing('projects', ['name' => 'Closed?']);
    }

    public function test_project_update_cannot_change_status_directly(): void
    {
        $project = Project::factory()->status(ProjectStatus::Lead)->create();

        $this->actingAs($this->admin)->put(route('projects.update', $project), [
            'name' => $project->name, 'client_id' => $project->client_id, 'type' => $project->type->value,
            'priority' => 'medium', 'manager_id' => $this->admin->id, 'status' => 'in_progress',
        ])->assertSessionHasErrors('status');

        $this->assertSame(ProjectStatus::Lead, $project->fresh()->status);
    }

    public function test_dashboard_active_kpi_counts_delivery_projects_and_pipeline_separately(): void
    {
        Project::factory()->count(2)->status(ProjectStatus::InProgress)->create();
        Project::factory()->status(ProjectStatus::Lead)->create();
        Project::factory()->status(ProjectStatus::Planning)->create();
        Project::factory()->status(ProjectStatus::Closed)->create();

        $kpis = $this->actingAs($this->admin)->get(route('dashboard'))->assertOk()->viewData('kpis');

        $this->assertSame(2, $kpis['active']['value']);
        $this->assertSame(2, $kpis['pipeline']['value']);
        $this->assertStringContainsString('status=active', $kpis['active']['url']);
    }

    public function test_active_filter_and_client_counts_use_the_same_definition(): void
    {
        $client = Client::factory()->create();
        $active = Project::factory()->for($client)->status(ProjectStatus::InProgress)->create();
        $lead = Project::factory()->for($client)->status(ProjectStatus::Lead)->create();

        $this->actingAs($this->admin)->get(route('projects.index', ['status' => 'active']))
            ->assertOk()->assertSee($active->name)->assertDontSee($lead->name);
        $this->get(route('projects.index', ['status' => 'pipeline']))
            ->assertOk()->assertSee($lead->name)->assertDontSee($active->name);

        $listed = $this->get(route('clients.index'))->assertOk()->viewData('clients')->firstWhere('id', $client->id);
        $this->assertSame(1, (int) $listed->active_projects_count);
    }

    public function test_admin_can_move_an_existing_lead_project_to_in_progress_with_an_override(): void
    {
        $project = Project::factory()->status(ProjectStatus::Lead)->create();

        $this->actingAs($this->admin)->post(route('projects.status', $project), ['status' => 'in_progress', 'override' => '1'])
            ->assertSessionHasErrors('workflow');
        $this->assertSame(ProjectStatus::Lead, $project->fresh()->status);

        $this->post(route('projects.status', $project), ['status' => 'in_progress', 'override' => '1', 'reason' => 'Work already started; created with the wrong status.'])
            ->assertSessionHasNoErrors();
        $this->assertSame(ProjectStatus::InProgress, $project->fresh()->status);
        $this->assertDatabaseHas('project_status_histories', ['project_id' => $project->id, 'from_status' => 'lead', 'to_status' => 'in_progress', 'is_override' => true]);

        $this->get(route('projects.show', $project))->assertOk()->assertSee('(admin override)');
    }

    public function test_non_admin_cannot_override_a_project_status(): void
    {
        $manager = User::factory()->management()->create();
        $project = Project::factory()->status(ProjectStatus::Lead)->create(['manager_id' => $manager->id]);
        $project->members()->attach($manager->id);

        $this->actingAs($manager)->post(route('projects.status', $project), ['status' => 'in_progress', 'override' => '1', 'reason' => 'Skip it'])
            ->assertForbidden();
        $this->assertSame(ProjectStatus::Lead, $project->fresh()->status);
    }

    public function test_failed_dialog_input_only_reappears_in_the_dialog_that_was_submitted(): void
    {
        $project = Project::factory()->create();
        $this->actingAs($this->admin);
        $page = route('projects.milestones', $project);
        $this->submitForm($page, route('projects.milestones.store', $project), ['name' => 'Existing milestone'])->assertSessionHasNoErrors();

        $this->from($page)->submitForm($page, route('projects.milestones.store', $project), ['name' => '', 'description' => 'BLEED-CHECK-TEXT'])
            ->assertSessionHasErrors('name');

        $html = $this->get($page)->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'BLEED-CHECK-TEXT'), 'Old input leaked into another dialog.');
        $this->assertSame(1, substr_count($html, 'data-open'), 'More than one dialog re-opened after the failed submit.');
    }

    public function test_add_preview_dialog_is_not_prefilled_from_the_last_preview(): void
    {
        $project = Project::factory()->create();
        $this->actingAs($this->admin);
        $this->submitForm(route('projects.previews', $project), route('projects.previews.store', $project), [
            'title' => 'Old preview title', 'url' => 'https://old.example.test', 'version_label' => 'v9',
        ])->assertSessionHasNoErrors();

        $fields = $this->formFieldsFor(route('projects.previews', $project), route('projects.previews.store', $project));

        $this->assertSame('', $fields['title']);
        $this->assertSame('', $fields['url']);
    }

    public function test_task_create_keeps_the_chosen_initial_status_and_rejects_done(): void
    {
        $project = Project::factory()->create();
        $base = ['project_id' => $project->id, 'title' => 'Task A', 'priority' => 'medium'];

        $this->actingAs($this->admin)->post(route('tasks.store'), $base + ['status' => 'in_progress'])->assertSessionHasNoErrors();
        $this->assertSame('in_progress', Task::where('title', 'Task A')->sole()->status->value);

        $this->post(route('tasks.store'), ['title' => 'Task B'] + $base + ['status' => 'done'])->assertSessionHasErrors('status');
        $this->assertDatabaseMissing('tasks', ['title' => 'Task B']);
    }

    public function test_meetings_calendar_ignores_a_malformed_month(): void
    {
        $this->actingAs($this->admin);

        foreach (['garbage', '2026-13', '2026-1', "2026-01'--"] as $month) {
            $this->get(route('meetings.index', ['month' => $month]))->assertOk();
        }
        $this->get(route('meetings.index', ['month' => '2026-02']))->assertOk()->assertSee('February 2026');
    }

    public function test_generated_client_action_titles_fit_the_column(): void
    {
        $project = Project::factory()->create();
        $this->actingAs($this->admin);
        $preview = new Preview(['title' => str_repeat('Long preview title ', 13), 'url' => 'https://x.example.test', 'version_label' => str_repeat('v', 40)]);
        $preview->project_id = $project->id;
        $preview->status = 'draft';
        $preview->save();

        $this->put(route('previews.update', $preview), [
            'title' => $preview->title, 'url' => $preview->url, 'version_label' => $preview->version_label, 'status' => 'shared',
        ])->assertSessionHasNoErrors();

        $action = ClientAction::where('related_id', $preview->id)->sole();
        $this->assertLessThanOrEqual(255, Str::length($action->title));
    }

    public function test_sign_in_lockout_returns_to_the_form_with_a_message(): void
    {
        $user = User::factory()->create(['email' => 'dev@example.test']);

        foreach (range(1, 5) as $attempt) {
            $this->post(route('login.store'), ['email' => 'dev@example.test', 'password' => 'wrong-'.$attempt])->assertSessionHasErrors('email');
        }

        $this->from(route('login'))->post(route('login.store'), ['email' => 'dev@example.test', 'password' => 'wrong-6'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => 'Too many sign-in attempts. Please wait a minute and try again.']);
        $this->assertGuest();
        $this->assertNotNull($user->fresh());
    }

    public function test_login_page_has_brand_and_password_toggle(): void
    {
        $this->get(route('login'))->assertOk()
            ->assertSee('brand-word', false)->assertSee('auth-card', false)
            ->assertSee('data-password-toggle', false)
            ->assertSee('favicon.svg', false)
            ->assertDontSee('href="'.url('/register').'"', false);
    }
}
