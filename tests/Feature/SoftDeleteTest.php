<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\Trash;
use App\Support\Activity;
use Illuminate\Auth\SessionGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SoftDeleteTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Client $client;

    private Project $project;

    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create(['name' => 'Asha Admin']);
        $this->client = Client::factory()->create(['name' => 'Zephyr Traders']);
        $this->project = Project::factory()->create(['client_id' => $this->client->id, 'name' => 'Quokka Portal']);
        $this->task = Task::factory()->create(['project_id' => $this->project->id, 'title' => 'Wire the xylophone checkout']);
    }

    public function test_admin_deletes_a_project_with_a_reason_and_it_is_hidden_everywhere(): void
    {
        $this->actingAs($this->admin)->get(route('projects.index'))->assertSee(route('projects.show', $this->project));
        $this->get(route('search', ['q' => 'Quokka']))->assertSee(route('projects.show', $this->project));
        $this->get(route('search', ['q' => 'xylophone']))->assertSee(route('tasks.show', $this->task));

        $this->actingAs($this->admin)->delete(route('projects.destroy', $this->project), ['reason' => 'Duplicate of the main portal project'])
            ->assertSessionHasNoErrors()->assertRedirect(route('projects.index'));

        $project = Project::withTrashed()->findOrFail($this->project->id);
        $this->assertTrue($project->trashed());
        $this->assertSame($this->admin->id, $project->deleted_by);
        $this->assertSame('Duplicate of the main portal project', $project->deletion_reason);

        $log = ActivityLog::where('event', 'project.deleted')->firstOrFail();
        $this->assertSame($this->admin->id, $log->user_id);
        $this->assertTrue($log->is_audit);
        $this->assertStringContainsString('Duplicate of the main portal project', $log->description);

        // Lists, dashboard, search, task lists and show pages.
        // (The audit entry "Deleted project … Quokka Portal" may appear in recent activity, so check for links instead.)
        $this->get(route('projects.index'))->assertOk()->assertDontSee(route('projects.show', $this->project));
        $this->get(route('dashboard'))->assertOk()->assertDontSee(route('projects.show', $this->project))
            ->assertDontSee(route('tasks.show', $this->task))->assertDontSee('Wire the xylophone checkout');
        $this->get(route('search', ['q' => 'Quokka']))->assertOk()->assertDontSee(route('projects.show', $this->project));
        $this->get(route('search', ['q' => 'xylophone']))->assertOk()->assertDontSee(route('tasks.show', $this->task));
        $this->get(route('tasks.index'))->assertOk()->assertDontSee('Wire the xylophone checkout');
        $this->get(route('projects.show', $this->project))->assertNotFound();
        $this->get(route('tasks.show', $this->task))->assertNotFound();

        // The project's tasks are hidden with it (not deleted themselves).
        $this->assertSame(0, Task::count());
        $this->assertNull(Task::withTrashed()->find($this->task->id)?->deleted_at);

        // History shows on the activity log.
        $this->get(route('activity.index'))->assertOk()->assertSee('Duplicate of the main portal project');
    }

    public function test_reason_is_required_and_must_be_meaningful(): void
    {
        $this->actingAs($this->admin)->delete(route('projects.destroy', $this->project), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->actingAs($this->admin)->delete(route('projects.destroy', $this->project), ['reason' => 'abc'])->assertSessionHasErrors('reason');
        $this->assertFalse($this->project->fresh()->trashed());
    }

    /** @return array<string, array{string}> */
    public static function nonAdminRoles(): array
    {
        return collect(Role::cases())->reject(fn (Role $r) => $r === Role::Admin)
            ->mapWithKeys(fn (Role $r) => [$r->value => [$r->value]])->all();
    }

    #[DataProvider('nonAdminRoles')]
    public function test_non_admins_cannot_delete_or_restore_or_open_trash(string $role): void
    {
        $user = User::factory()->create(['role' => $role]);
        $this->project->members()->attach($user->id);
        $other = User::factory()->create();
        $reason = ['reason' => 'Trying to delete this'];

        $this->actingAs($user)->delete(route('projects.destroy', $this->project), $reason)->assertForbidden();
        $this->actingAs($user)->delete(route('clients.destroy', $this->client), $reason)->assertForbidden();
        $this->actingAs($user)->delete(route('tasks.destroy', $this->task), $reason)->assertForbidden();
        $this->actingAs($user)->delete(route('users.destroy', $other), $reason)->assertForbidden();
        $this->actingAs($user)->get(route('trash.index'))->assertForbidden();

        $this->task->delete();
        $this->actingAs($user)->post(route('trash.restore', ['tasks', $this->task->id]))->assertForbidden();
        $this->assertTrue($this->task->fresh()->trashed());
        $this->assertFalse($this->project->fresh()->trashed());
        $this->assertFalse($this->client->fresh()->trashed());
        $this->assertFalse($other->fresh()->trashed());

        // The button is not rendered for them.
        $this->task->restore();
        $this->actingAs($user)->get(route('tasks.show', $this->task))->assertDontSee('data-delete-open', false);
    }

    public function test_admin_sees_delete_buttons(): void
    {
        $this->actingAs($this->admin);
        $this->get(route('projects.show', $this->project))->assertOk()->assertSee('data-delete-open', false)->assertSee(route('projects.destroy', $this->project));
        $this->get(route('clients.show', $this->client))->assertOk()->assertSee(route('clients.destroy', $this->client));
        $this->get(route('tasks.show', $this->task))->assertOk()->assertSee(route('tasks.destroy', $this->task));
        $other = User::factory()->create();
        $this->get(route('users.index'))->assertOk()->assertSee(route('users.destroy', $other));
        $this->get(route('users.edit', $other))->assertOk()->assertSee(route('users.destroy', $other));
        $this->get(route('trash.index'))->assertOk();
    }

    public function test_restoring_a_project_brings_back_its_tasks_and_is_logged(): void
    {
        $milestone = $this->project->milestones()->create(['name' => 'Launch']);
        $this->actingAs($this->admin)->delete(route('projects.destroy', $this->project), ['reason' => 'Created by mistake']);
        $this->assertSame(0, Milestone::count());

        $this->get(route('trash.index', ['type' => 'projects']))->assertOk()
            ->assertSee('Quokka Portal')->assertSee('Created by mistake')->assertSee('Asha Admin');

        $this->post(route('trash.restore', ['projects', $this->project->id]), ['note' => 'Client came back'])
            ->assertSessionHasNoErrors()->assertRedirect();

        $project = $this->project->fresh();
        $this->assertFalse($project->trashed());
        $this->assertNull($project->deleted_by);
        $this->assertNull($project->deletion_reason);
        $this->assertSame(1, Task::count());
        $this->assertTrue(Milestone::whereKey($milestone->id)->exists());
        $this->get(route('tasks.show', $this->task))->assertOk();

        $log = ActivityLog::where('event', 'project.restored')->firstOrFail();
        $this->assertStringContainsString('Client came back', $log->description);
        $this->assertSame('Created by mistake', $log->changes['deletion_reason']);
        $this->get(route('projects.activity', $project))->assertOk()->assertSee('Created by mistake')->assertSee('Client came back');
    }

    public function test_task_delete_and_restore_and_restore_order(): void
    {
        $this->actingAs($this->admin)->delete(route('tasks.destroy', $this->task), ['reason' => 'Out of scope now'])
            ->assertRedirect(route('projects.tasks', $this->project));
        $this->get(route('tasks.show', $this->task))->assertNotFound();
        $this->get(route('trash.index', ['type' => 'tasks']))->assertSee('Wire the xylophone checkout')->assertSee('Out of scope now');

        // A task of a deleted project cannot come back before its project.
        $this->delete(route('projects.destroy', $this->project), ['reason' => 'Whole project cancelled']);
        $this->post(route('trash.restore', ['tasks', $this->task->id]))->assertSessionHasErrors('workflow');
        $this->assertTrue(Task::withTrashed()->withoutGlobalScopes()->find($this->task->id)->trashed());

        // A project of a deleted client cannot come back before its client.
        $this->client->forceFill(['deleted_by' => $this->admin->id])->save();
        $this->client->delete();
        $this->post(route('trash.restore', ['projects', $this->project->id]))->assertSessionHasErrors('workflow');

        $this->post(route('trash.restore', ['clients', $this->client->id]))->assertSessionHasNoErrors();
        $this->post(route('trash.restore', ['projects', $this->project->id]))->assertSessionHasNoErrors();
        $this->post(route('trash.restore', ['tasks', $this->task->id]))->assertSessionHasNoErrors();
        $this->get(route('tasks.show', $this->task))->assertOk()->assertSee('Out of scope now');
    }

    public function test_client_with_projects_cannot_be_deleted(): void
    {
        $this->actingAs($this->admin)->from(route('clients.show', $this->client))
            ->delete(route('clients.destroy', $this->client), ['reason' => 'No longer a client'])
            ->assertRedirect(route('clients.show', $this->client))->assertSessionHasErrors('workflow');
        $this->assertFalse($this->client->fresh()->trashed());

        $empty = Client::factory()->create(['name' => 'Empty Co']);
        $this->delete(route('clients.destroy', $empty), ['reason' => 'Never became a client'])->assertRedirect(route('clients.index'));
        $this->assertTrue($empty->fresh()->trashed());
        $this->get(route('clients.index'))->assertDontSee(route('clients.show', $empty));
        $this->get(route('clients.show', $empty))->assertNotFound();
        $this->get(route('search', ['q' => 'Empty Co']))->assertOk()->assertDontSee(route('clients.show', $empty));
    }

    public function test_self_delete_is_blocked_and_another_admin_can_be_deleted(): void
    {
        $this->actingAs($this->admin)->delete(route('users.destroy', $this->admin), ['reason' => 'Leaving the company'])
            ->assertSessionHasErrors(['workflow' => 'You cannot delete your own account.']);
        $this->assertFalse($this->admin->fresh()->trashed());

        $second = User::factory()->admin()->create();
        $this->delete(route('users.destroy', $second), ['reason' => 'Duplicate admin account'])->assertSessionHasNoErrors();
        $this->assertTrue($second->fresh()->trashed());
    }

    public function test_last_active_admin_cannot_be_deleted(): void
    {
        // Through the UI the acting admin always remains, so the guard is checked on the service itself
        // (e.g. a deactivated admin record trying to remove the only active one).
        $inactiveAdmin = User::factory()->admin()->inactive()->create();

        try {
            app(Trash::class)->delete($this->admin, $inactiveAdmin, 'Removing the only admin');
            $this->fail('Expected the last active administrator to be protected.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('last active administrator', $e->errors()['workflow'][0]);
        }
        $this->assertFalse($this->admin->fresh()->trashed());
    }

    public function test_deleted_user_cannot_sign_in_and_their_session_is_rejected_and_tasks_show_deleted_user(): void
    {
        $dev = User::factory()->create(['name' => 'Devika Dev', 'email' => 'devika@example.test']);
        $this->project->members()->attach($dev->id);
        $this->task->forceFill(['assignee_id' => $dev->id])->save();

        $this->actingAs($dev)->get(route('dashboard'))->assertOk();

        $this->actingAs($this->admin)->delete(route('users.destroy', $dev), ['reason' => 'Left the company'])->assertRedirect(route('users.index'));
        $this->assertTrue($dev->fresh()->trashed());
        $this->get(route('users.index'))->assertDontSee('devika@example.test');
        $this->get(route('users.edit', $dev))->assertNotFound();
        $this->get(route('tasks.show', $this->task))->assertOk()->assertSee('Deleted user (Devika Dev)');
        $this->assertSame($dev->id, $this->task->fresh()->assignee_id);
        $this->get(route('trash.index', ['type' => 'users']))->assertSee('Devika Dev')->assertSee('Left the company');

        // Existing session: the user id no longer resolves, so the request is treated as a guest.
        $this->app['auth']->forgetGuards();
        $this->withSession(['login_web_'.sha1(SessionGuard::class) => $dev->id])
            ->get(route('dashboard'))->assertRedirect(route('login'));

        $this->post(route('logout'));
        $this->app['auth']->forgetGuards();
        $this->post(route('login.store'), ['email' => 'devika@example.test', 'password' => 'password'])->assertSessionHasErrors();
        $this->assertGuest();

        // Re-creating with the same email is refused with a pointer to Trash.
        $this->actingAs($this->admin)->post(route('users.store'), [
            'name' => 'Devika Again', 'email' => 'devika@example.test', 'role' => 'developer',
            'password' => 'A-long-Passw0rd!x', 'password_confirmation' => 'A-long-Passw0rd!x', 'is_active' => 1,
        ])->assertSessionHasErrors(['email' => 'This email belongs to a deleted user. Restore that account from Trash instead of creating a new one.']);

        // Restore lets them sign in again.
        $this->post(route('trash.restore', ['users', $dev->id]))->assertSessionHasNoErrors();
        $this->post(route('logout'));
        $this->app['auth']->forgetGuards();
        $this->post(route('login.store'), ['email' => 'devika@example.test', 'password' => 'password'])->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($dev->fresh());
        $this->assertTrue(ActivityLog::where('event', 'user.restored')->exists());
    }

    public function test_created_by_is_recorded_and_shown(): void
    {
        $manager = User::factory()->manager()->create(['name' => 'Meera Manager']);
        $this->actingAs($manager)->post(route('clients.store'), ['name' => 'Fresh Client Ltd', 'status' => 'active'])->assertSessionHasNoErrors();
        $client = Client::where('name', 'Fresh Client Ltd')->firstOrFail();
        $this->assertSame($manager->id, $client->created_by);
        $this->actingAs($this->admin)->get(route('clients.show', $client))->assertSee('Created by Meera Manager');

        // Older records without created_by fall back to the first activity entry; with none the line is hidden.
        $this->get(route('projects.show', $this->project))->assertOk()->assertDontSee('data-created-by', false);
        $this->actingAs($manager);
        Activity::log('project.created', 'created', $this->project);
        $this->actingAs($this->admin)->get(route('projects.show', $this->project))->assertSee('Created by Meera Manager');
    }
}
