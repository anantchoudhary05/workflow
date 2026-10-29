<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Renders every navigable page for an administrator against a small, real data set,
 * so Blade or query errors on any screen fail the suite.
 */
class PageRenderingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string}>
     */
    public static function globalPages(): array
    {
        return array_combine($names = [
            'dashboard', 'my-work', 'search', 'notifications.index', 'clients.index', 'clients.create',
            'projects.index', 'projects.create', 'tasks.index', 'tasks.board', 'tasks.create',
            'change-requests.index', 'client-actions.index', 'communications.index', 'meetings.index',
            'meetings.create', 'approvals.index', 'quotations.index', 'qa.index', 'reports.index',
            'team.index', 'activity.index', 'users.index', 'users.create',
        ], array_map(fn (string $name): array => [$name], $names));
    }

    /**
     * @return array<string, array{0: string, 1: array<string, string>}>
     */
    public static function projectPages(): array
    {
        return [
            'overview' => ['projects.show', []],
            'edit' => ['projects.edit', []],
            'activity' => ['projects.activity', []],
            'requirements' => ['projects.requirements', []],
            'scope' => ['projects.scope', []],
            'quotations' => ['projects.quotations', []],
            'new quotation' => ['projects.quotations.create', []],
            'milestones' => ['projects.milestones', []],
            'tasks' => ['projects.tasks', []],
            'changes' => ['projects.changes', []],
            'previews' => ['projects.previews', []],
            'qa' => ['projects.qa', []],
            'files' => ['projects.files', []],
            'release checklist' => ['projects.checklist', ['type' => 'release']],
            'handover checklist' => ['projects.checklist', ['type' => 'handover']],
        ];
    }

    #[DataProvider('globalPages')]
    public function test_global_page_renders_for_admin(string $route): void
    {
        $admin = User::factory()->admin()->create();
        Task::factory()->overdue()->create(['assignee_id' => $admin->id]);

        $this->actingAs($admin)->get(route($route))->assertOk();
    }

    /**
     * @param  array<string, string>  $parameters
     */
    #[DataProvider('projectPages')]
    public function test_project_page_renders_for_admin(string $route, array $parameters): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create(['manager_id' => $admin->id]);
        Task::factory()->for($project)->create(['assignee_id' => $admin->id]);

        $this->actingAs($admin)->get(route($route, ['project' => $project] + $parameters))->assertOk();
    }

    public function test_client_and_task_detail_pages_render(): void
    {
        $admin = User::factory()->admin()->create();
        $task = Task::factory()->create(['assignee_id' => $admin->id]);

        $this->actingAs($admin);
        $this->get(route('clients.show', $task->project->client))->assertOk();
        $this->get(route('clients.edit', $task->project->client))->assertOk();
        $this->get(route('tasks.show', $task))->assertOk();
        $this->get(route('tasks.edit', $task))->assertOk();
        $this->get(route('users.edit', $admin))->assertOk();
    }

    public function test_pages_render_with_records_created_through_the_app(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create(['manager_id' => $admin->id]);
        $task = Task::factory()->for($project)->create(['assignee_id' => $admin->id]);
        $today = today()->toDateString();
        $this->actingAs($admin);

        $posts = [
            [route('projects.requirements.store', $project), ['title' => 'Booking form', 'category' => 'functional', 'priority' => 'high']],
            [route('projects.milestones.store', $project), ['name' => 'Design', 'priority' => 'medium', 'weight' => 20, 'due_date' => today()->addWeek()->toDateString()]],
            [route('tasks.time.store', $task), ['work_date' => $today, 'hours' => 1.5, 'description' => 'Layout', 'billable' => '0']],
            [route('tasks.comments.store', $task), ['body' => 'Started layout.']],
            [route('projects.previews.store', $project), ['title' => 'Home page', 'version_label' => 'v1', 'url' => 'https://staging.example.test']],
            [route('projects.test-cases.store', $project), ['title' => 'Submit booking', 'steps' => 'Fill and submit', 'expected_result' => 'Saved']],
            [route('projects.defects.store', $project), ['title' => 'Button misaligned', 'severity' => 'minor', 'priority' => 'low']],
            [route('projects.checklist.generate', ['project' => $project, 'type' => 'release']), []],
            [route('projects.checklist.generate', ['project' => $project, 'type' => 'handover']), []],
            [route('meetings.store'), ['title' => 'Kickoff call', 'type' => 'kickoff', 'project_id' => $project->id, 'client_id' => $project->client_id, 'starts_at' => now()->addDay()->format('Y-m-d H:i'), 'duration_minutes' => 30, 'status' => 'scheduled']],
            [route('communications.store'), ['client_id' => $project->client_id, 'project_id' => $project->id, 'type' => 'email', 'direction' => 'inbound', 'subject' => 'Logo files', 'occurred_at' => now()->subHour()->format('Y-m-d H:i')]],
            [route('client-actions.store'), ['project_id' => $project->id, 'type' => 'content_submission', 'title' => 'Send page copy', 'requested_at' => $today, 'next_follow_up_at' => today()->addDays(2)->toDateString()]],
            [route('projects.changes.store', $project), ['title' => 'Extra page', 'description' => 'Add a careers page.', 'received_at' => $today, 'priority' => 'medium']],
        ];
        foreach ($posts as [$url, $data]) {
            $this->post($url, $data)->assertRedirect()->assertSessionHasNoErrors();
        }

        foreach (array_keys(self::globalPages()) as $route) {
            $this->get(route($route))->assertOk();
        }
        foreach (self::projectPages() as [$route, $parameters]) {
            $this->get(route($route, ['project' => $project] + $parameters))->assertOk();
        }
        $this->get(route('tasks.show', $task))->assertOk()->assertSee('non-billable');
        $this->get(route('meetings.show', $project->meetings()->firstOrFail()))->assertOk();
        $this->get(route('defects.show', $project->defects()->firstOrFail()))->assertOk();
        $this->get(route('change-requests.show', $project->changeRequests()->firstOrFail()))->assertOk();
    }
}
