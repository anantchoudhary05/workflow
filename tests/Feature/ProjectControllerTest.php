<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_creates_project_with_manager_as_member(): void
    {
        $admin = User::factory()->admin()->create();
        $client = Client::factory()->create();

        $this->actingAs($admin)->post(route('projects.store'), [
            'name' => 'Clinic website',
            'client_id' => $client->id,
            'type' => 'business_website',
            'manager_id' => $admin->id,
            'priority' => 'high',
        ])->assertRedirect();

        $project = Project::where('name', 'Clinic website')->firstOrFail();
        $this->assertNotEmpty($project->code);
        $this->assertTrue($project->members()->whereKey($admin->id)->exists());
        $this->assertDatabaseHas('activity_logs', ['event' => 'project.created', 'project_id' => $project->id]);
    }

    public function test_developer_cannot_create_project(): void
    {
        $this->actingAs(User::factory()->create())->get(route('projects.create'))->assertForbidden();
    }

    public function test_developer_lists_only_assigned_projects(): void
    {
        $developer = User::factory()->create();
        $mine = Project::factory()->create(['name' => 'Assigned build']);
        $mine->members()->attach($developer);
        $other = Project::factory()->create(['name' => 'Someone else build']);

        $this->actingAs($developer)->get(route('projects.index'))
            ->assertOk()->assertSee('Assigned build')->assertDontSee('Someone else build');
        $this->get(route('projects.show', $other))->assertForbidden();
    }

    public function test_management_views_all_projects_but_cannot_edit(): void
    {
        $project = Project::factory()->create();
        $manager = User::factory()->management()->create();

        $this->actingAs($manager)->get(route('projects.show', $project))->assertOk();
        $this->get(route('projects.edit', $project))->assertForbidden();
    }

    public function test_inactive_session_is_rejected(): void
    {
        $this->actingAs(User::factory()->inactive()->create())->get(route('dashboard'))->assertRedirect(route('login'));
    }
}
