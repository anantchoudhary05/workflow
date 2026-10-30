<?php

namespace Tests\Feature;

use App\Enums\Priority;
use App\Enums\Role;
use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_department_role_exists_with_a_description(): void
    {
        $this->assertSame(
            ['admin', 'manager', 'team_lead', 'developer', 'reviewer', 'marketing', 'management'],
            array_map(fn (Role $r): string => $r->value, Role::cases()),
        );
        foreach (Role::cases() as $role) {
            $this->assertNotSame('', $role->description());
        }
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: bool}>
     */
    public static function abilityMatrix(): array
    {
        return [
            'manager sets urgency' => ['manager', 'set-urgency', true],
            'manager approves for preview' => ['manager', 'approve-for-preview', true],
            'manager sets deadlines' => ['manager', 'set-deadlines', true],
            'team lead manages tasks' => ['team_lead', 'manage-tasks', true],
            'team lead requests review' => ['team_lead', 'request-review', true],
            'team lead receives handoffs' => ['team_lead', 'receive-handoffs', true],
            'team lead cannot set urgency' => ['team_lead', 'set-urgency', false],
            'team lead cannot approve for preview' => ['team_lead', 'approve-for-preview', false],
            'team lead cannot see financials' => ['team_lead', 'view-financials', false],
            'marketing manages clients' => ['marketing', 'manage-clients', true],
            'marketing uploads client documents' => ['marketing', 'manage-client-documents', true],
            'marketing hands off projects' => ['marketing', 'hand-off-projects', true],
            'marketing cannot manage tasks' => ['marketing', 'manage-tasks', false],
            'developer works tasks' => ['developer', 'work-tasks', true],
            'developer cannot upload client documents' => ['developer', 'manage-client-documents', false],
            'developer cannot manage tasks' => ['developer', 'manage-tasks', false],
            'qa reviews tasks' => ['reviewer', 'review-tasks', true],
            'qa cannot manage tasks' => ['reviewer', 'manage-tasks', false],
            'management sees reports' => ['management', 'view-reports', true],
            'management sees all projects' => ['management', 'view-all-projects', true],
            'management cannot manage projects' => ['management', 'manage-projects', false],
            'management cannot upload files' => ['management', 'upload-files', false],
            'management cannot comment' => ['management', 'comment', false],
            'only admin manages users' => ['manager', 'manage', false],
        ];
    }

    #[DataProvider('abilityMatrix')]
    public function test_role_ability_matrix(string $role, string $ability, bool $expected): void
    {
        $user = User::factory()->create(['role' => $role]);

        $this->assertSame($expected, $user->can($ability), "{$role} / {$ability}");
    }

    public function test_management_is_read_only(): void
    {
        $director = User::factory()->management()->create();
        $project = Project::factory()->create();

        $this->actingAs($director)->get(route('projects.show', $project))->assertOk();
        $this->actingAs($director)->get(route('reports.index'))->assertOk();
        $this->actingAs($director)->get(route('team.index'))->assertOk();
        $this->actingAs($director)->get(route('projects.create'))->assertForbidden();
        $this->actingAs($director)->get(route('tasks.create'))->assertForbidden();
        $this->actingAs($director)->post(route('clients.documents.store', $project->client_id), [])->assertForbidden();
        $this->actingAs($director)->get(route('users.index'))->assertForbidden();
    }

    public function test_marketing_cannot_create_tasks_but_can_create_projects(): void
    {
        $marketing = User::factory()->marketing()->create();

        $this->actingAs($marketing)->get(route('projects.create'))->assertOk();
        $this->actingAs($marketing)->get(route('tasks.create'))->assertForbidden();
    }

    public function test_only_set_urgency_holders_mark_tasks_urgent(): void
    {
        $lead = User::factory()->teamLead()->create();
        $manager = User::factory()->manager()->create();
        $task = Task::factory()->create(['priority' => Priority::Medium]);

        $this->actingAs($lead)->post(route('tasks.store'), [
            'project_id' => $task->project_id, 'title' => 'Hotfix', 'priority' => 'urgent',
        ])->assertSessionHasErrors('priority');
        $this->actingAs($lead)->post(route('tasks.priority', $task), ['priority' => 'urgent'])->assertForbidden();
        $this->assertSame(Priority::Medium, $task->fresh()->priority);

        $this->actingAs($manager)->post(route('tasks.priority', $task), ['priority' => 'urgent'])->assertRedirect();
        $this->assertSame(Priority::Urgent, $task->fresh()->priority);
    }

    public function test_developer_cannot_change_priority(): void
    {
        $developer = User::factory()->create();
        $task = Task::factory()->create(['assignee_id' => $developer->id]);
        $task->project->members()->attach($developer);

        $this->actingAs($developer)->post(route('tasks.priority', $task), ['priority' => 'low'])->assertForbidden();
    }

    public function test_admin_assigns_department_roles_on_users_page(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->actingAs($admin)->get(route('users.index'))->assertOk()->assertSee('Team Lead');
        $this->actingAs($admin)->get(route('users.edit', $user))->assertOk()->assertSee('Which role to choose')->assertSee(Role::Marketing->description());
        $this->actingAs($admin)->put(route('users.update', $user), [
            'name' => $user->name, 'email' => $user->email, 'role' => 'team_lead', 'is_active' => '1',
        ])->assertRedirect();
        $this->assertSame(Role::TeamLead, $user->fresh()->role);
    }

    public function test_role_data_migration_normalizes_unknown_roles_only(): void
    {
        $kept = User::factory()->create(['role' => 'reviewer']);
        $odd = User::factory()->create();
        DB::table('users')->where('id', $odd->id)->update(['role' => 'legacy']);

        (require database_path('migrations/2026_10_30_090000_normalize_user_roles.php'))->up();

        $this->assertSame('reviewer', DB::table('users')->where('id', $kept->id)->value('role'));
        $this->assertSame('developer', DB::table('users')->where('id', $odd->id)->value('role'));
    }

    public function test_every_role_gets_a_dashboard(): void
    {
        $client = Client::factory()->create();
        Project::factory()->create(['client_id' => $client->id]);

        foreach (Role::cases() as $role) {
            $this->actingAs(User::factory()->create(['role' => $role->value]))
                ->get(route('dashboard'))->assertOk()->assertSee('My to-dos')->assertSee($role->label());
        }
    }

    public function test_role_dashboards_show_role_widgets(): void
    {
        $this->actingAs(User::factory()->manager()->create())->get(route('dashboard'))
            ->assertSee('data-widget="reviews"', false)->assertSee('data-widget="urgent"', false)->assertSee('data-kpi="reviews"', false);
        $this->actingAs(User::factory()->teamLead()->create())->get(route('dashboard'))
            ->assertSee('data-widget="handoffs"', false)->assertSee('data-widget="unassigned"', false);
        $this->actingAs(User::factory()->marketing()->create())->get(route('dashboard'))
            ->assertSee('data-widget="my-handoffs"', false)->assertSee('data-widget="pipeline"', false)
            ->assertDontSee('data-widget="urgent"', false)->assertDontSee('data-kpi="overdue"', false);
        $this->actingAs(User::factory()->management()->create())->get(route('dashboard'))
            ->assertSee('data-kpi="team"', false)->assertDontSee('data-widget="unassigned"', false);
        $this->actingAs(User::factory()->create())->get(route('dashboard'))
            ->assertSee('data-kpi="my_open"', false)->assertDontSee('data-widget="reviews"', false);
    }
}
