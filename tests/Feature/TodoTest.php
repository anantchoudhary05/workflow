<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Todo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TodoTest extends TestCase
{
    use RefreshDatabase;

    public function test_head_creates_project_todo_shown_on_dashboard_and_project(): void
    {
        $lead = User::factory()->teamLead()->create();
        $project = Project::factory()->create();

        $this->actingAs($lead)->post(route('todos.store'), [
            'title' => 'Call client about logo', 'priority' => 'high', 'project_id' => $project->id, 'due_date' => today()->toDateString(),
        ])->assertRedirect();

        $todo = Todo::firstOrFail();
        $this->assertSame($lead->id, $todo->user_id);
        $this->actingAs($lead)->get(route('dashboard'))->assertSee('Call client about logo')->assertSee('Due today');
        $this->actingAs($lead)->get(route('projects.show', $project))->assertSee('Call client about logo');
        $this->assertDatabaseMissing('activity_logs', ['subject_type' => Todo::class]);
    }

    public function test_todos_are_private_even_from_admins(): void
    {
        $owner = User::factory()->manager()->create();
        $todo = Todo::factory()->for($owner)->create(['title' => 'Secret plan']);
        $admin = User::factory()->admin()->create();
        $other = User::factory()->manager()->create();

        foreach ([$admin, $other] as $intruder) {
            $this->actingAs($intruder)->get(route('todos.index'))->assertOk()->assertDontSee('Secret plan');
            $this->actingAs($intruder)->get(route('dashboard'))->assertDontSee('Secret plan');
            $this->actingAs($intruder)->post(route('todos.toggle', $todo))->assertForbidden();
            $this->actingAs($intruder)->put(route('todos.update', $todo), ['title' => 'x', 'priority' => 'low'])->assertForbidden();
            $this->actingAs($intruder)->delete(route('todos.destroy', $todo))->assertForbidden();
        }
        $this->assertNull($todo->fresh()->completed_at);
        $this->assertSame('Secret plan', $todo->fresh()->title);
    }

    public function test_owner_toggles_updates_and_deletes(): void
    {
        $owner = User::factory()->create();
        $todo = Todo::factory()->for($owner)->create();

        $this->actingAs($owner)->post(route('todos.toggle', $todo))->assertRedirect();
        $this->assertNotNull($todo->fresh()->completed_at);
        $this->actingAs($owner)->post(route('todos.toggle', $todo))->assertRedirect();
        $this->assertNull($todo->fresh()->completed_at);
        $this->actingAs($owner)->put(route('todos.update', $todo), ['title' => 'Renamed', 'priority' => 'low'])->assertRedirect();
        $this->assertSame('Renamed', $todo->fresh()->title);
        $this->actingAs($owner)->delete(route('todos.destroy', $todo))->assertRedirect();
        $this->assertModelMissing($todo);
    }

    public function test_todo_cannot_link_a_project_the_user_cannot_open(): void
    {
        $developer = User::factory()->create();
        $project = Project::factory()->create();

        $this->actingAs($developer)->post(route('todos.store'), [
            'title' => 'Peek', 'priority' => 'low', 'project_id' => $project->id,
        ])->assertForbidden();
        $this->assertSame(0, Todo::count());
    }

    public function test_overdue_todos_are_highlighted(): void
    {
        $owner = User::factory()->management()->create();
        Todo::factory()->for($owner)->create(['title' => 'Late review', 'due_date' => today()->subDays(3)]);

        $this->actingAs($owner)->get(route('todos.index'))->assertOk()->assertSee('Late review')->assertSee('Overdue');
    }
}
