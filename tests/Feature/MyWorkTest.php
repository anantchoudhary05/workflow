<?php

namespace Tests\Feature;

use App\Enums\Priority;
use App\Enums\TaskStatus;
use App\Models\ClientAction;
use App\Models\Project;
use App\Models\Task;
use App\Models\Todo;
use App\Models\User;
use App\Services\MyWork;
use App\Services\Trash;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyWorkTest extends TestCase
{
    use RefreshDatabase;

    private User $dev;

    private User $other;

    private Project $alpha;

    private Project $beta;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dev = User::factory()->create(['name' => 'Devika Dev']);
        $this->other = User::factory()->create(['name' => 'Omar Other']);
        $this->alpha = Project::factory()->create(['name' => 'Alpha Shop']);
        $this->beta = Project::factory()->create(['name' => 'Beta Portal']);
        $this->alpha->members()->attach([$this->dev->id, $this->other->id]);
        $this->beta->members()->attach([$this->dev->id]);
    }

    private function task(Project $project, string $title, array $attrs = []): Task
    {
        return Task::factory()->create($attrs + ['project_id' => $project->id, 'title' => $title, 'assignee_id' => $this->dev->id]);
    }

    public function test_project_panel_shows_only_my_tasks_from_that_project(): void
    {
        $mine = $this->task($this->alpha, 'Build alpha cart');
        $elsewhere = $this->task($this->beta, 'Beta login page');
        $theirs = $this->task($this->alpha, 'Alpha banner for Omar', ['assignee_id' => $this->other->id]);
        $done = $this->task($this->alpha, 'Alpha finished thing', ['status' => TaskStatus::Done]);

        $this->actingAs($this->dev)->get(route('projects.show', $this->alpha))->assertOk()
            ->assertSee('id="my-work-panel"', false)
            ->assertSee('Assigned tasks')->assertSee('My to-dos')
            ->assertSee('data-my-task="'.$mine->id.'"', false)
            ->assertDontSee('data-my-task="'.$elsewhere->id.'"', false)
            ->assertDontSee('data-my-task="'.$theirs->id.'"', false)
            ->assertDontSee('data-my-task="'.$done->id.'"', false)
            ->assertSee('aria-selected="true" tabindex="0" data-tab="tasks"', false);

        // A Ready task gets a quick "Start" button that uses the normal status route.
        $this->actingAs($this->dev)->get(route('projects.show', $this->alpha))->assertSee(route('tasks.status', $mine), false);
        $this->post(route('tasks.status', $mine), ['status' => 'in_progress'])->assertRedirect();
        $this->assertSame(TaskStatus::InProgress, $mine->fresh()->status);
    }

    public function test_panel_orders_overdue_and_urgent_first_and_highlights_them(): void
    {
        $later = $this->task($this->alpha, 'Later polish', ['due_date' => today()->addDays(20)]);
        $urgent = $this->task($this->alpha, 'Urgent hotfix', ['due_date' => today()->addDays(10), 'priority' => Priority::Urgent]);
        $overdue = $this->task($this->alpha, 'Overdue copy', ['due_date' => today()->subDays(2)]);

        $ids = MyWork::for($this->dev, $this->alpha->id)->tasks()->pluck('id')->all();
        $this->assertSame([$overdue->id, $urgent->id, $later->id], $ids);

        $this->actingAs($this->dev)->get(route('projects.show', $this->alpha))
            ->assertSeeInOrder(['Overdue copy', 'Urgent hotfix', 'Later polish'])->assertSee('Overdue ·');
    }

    public function test_my_work_page_lists_each_task_once(): void
    {
        $blocked = $this->task($this->alpha, 'Blocked gateway', ['status' => TaskStatus::Blocked, 'due_date' => today()->addDays(2)]);

        $groups = MyWork::groups(MyWork::for($this->dev)->tasks());
        $this->assertSame([$blocked->id], $groups['Blocked']->pluck('id')->all());
        $this->assertTrue($groups['Next 7 days']->isEmpty());
        $this->assertSame(1, substr_count($this->actingAs($this->dev)->get(route('my-work'))->getContent(), 'Blocked gateway'));
    }

    public function test_empty_state_and_reviews_and_follow_ups_in_the_project(): void
    {
        $this->actingAs($this->dev)->get(route('projects.show', $this->alpha))->assertSee('Nothing assigned to you in this project.');

        $review = Task::factory()->create(['project_id' => $this->alpha->id, 'title' => 'Review the alpha checkout', 'assignee_id' => $this->other->id,
            'reviewer_id' => $this->dev->id, 'status' => TaskStatus::InReview]);
        $otherReview = Task::factory()->create(['project_id' => $this->beta->id, 'title' => 'Review beta thing', 'reviewer_id' => $this->dev->id, 'status' => TaskStatus::InReview]);
        ClientAction::create(['client_id' => $this->alpha->client_id, 'project_id' => $this->alpha->id, 'type' => 'requirements_approval',
            'title' => 'Chase alpha sign-off', 'requested_at' => today()->subDays(3), 'owner_id' => $this->dev->id]);

        $this->get(route('projects.show', $this->alpha))->assertOk()
            ->assertSee('Waiting for my review (1)')->assertSee(route('tasks.show', $review), false)
            ->assertDontSee(route('tasks.show', $otherReview), false)
            ->assertSee('Chase alpha sign-off');
    }

    public function test_trashed_tasks_are_excluded(): void
    {
        $kept = $this->task($this->alpha, 'Kept alpha task');
        $gone = $this->task($this->alpha, 'Deleted alpha task');
        $hiddenWithProject = $this->task($this->beta, 'Beta task of deleted project');
        $admin = User::factory()->admin()->create();
        app(Trash::class)->delete($gone, $admin, 'Duplicate of the kept task');

        $this->actingAs($this->dev)->get(route('projects.show', $this->alpha))
            ->assertSee('data-my-task="'.$kept->id.'"', false)->assertDontSee('data-my-task="'.$gone->id.'"', false);
        $this->get(route('my-work'))->assertSee('Kept alpha task')->assertDontSee('Deleted alpha task')->assertSee('Beta task of deleted project');

        app(Trash::class)->delete($this->beta, $admin, 'Client cancelled the portal');
        $this->get(route('my-work'))->assertOk()->assertDontSee('Beta task of deleted project')->assertDontSee('Beta Portal');
        $this->assertNotContains($this->beta->id, array_keys(MyWork::for($this->dev)->projectOptions()));
        $this->assertNotNull($hiddenWithProject->id);
    }

    public function test_my_work_page_lists_all_projects_with_names_and_filters_by_project(): void
    {
        $this->task($this->alpha, 'Build alpha cart');
        $this->task($this->beta, 'Beta login page');
        $this->task($this->alpha, 'Alpha banner for Omar', ['assignee_id' => $this->other->id]);

        $this->actingAs($this->dev)->get(route('my-work'))->assertOk()
            ->assertSee('Build alpha cart')->assertSee('Beta login page')->assertDontSee('Alpha banner for Omar')
            ->assertSee($this->alpha->code.' · Alpha Shop')->assertSee($this->beta->code.' · Beta Portal')
            ->assertSee('name="project"', false)->assertSee('All projects');

        $this->get(route('my-work', ['project' => $this->alpha->id]))->assertOk()
            ->assertSee('Build alpha cart')->assertDontSee('Beta login page')
            ->assertSee('<option value="'.$this->alpha->id.'" selected', false);

        $this->get(route('my-work', ['project' => $this->beta->id]))->assertOk()
            ->assertSee('Beta login page')->assertDontSee('Build alpha cart');

        // Filtering to a project with nothing for this user shows the empty state, never other people's work.
        $empty = Project::factory()->create();
        $this->get(route('my-work', ['project' => $empty->id]))->assertOk()->assertSee('Nothing assigned to you in this project')
            ->assertDontSee('Alpha banner for Omar');
    }

    public function test_to_dos_live_in_the_second_tab_and_marketing_defaults_to_it(): void
    {
        Todo::factory()->create(['user_id' => $this->dev->id, 'project_id' => $this->alpha->id, 'title' => 'Ask lead about fonts']);
        $this->actingAs($this->dev)->get(route('projects.show', $this->alpha))->assertSee('Ask lead about fonts')
            ->assertSee('id="mywork-panel-todos" aria-labelledby="mywork-tab-todos" tabindex="0"  hidden', false);

        $marketing = User::factory()->marketing()->create();
        Todo::factory()->create(['user_id' => $marketing->id, 'project_id' => $this->alpha->id, 'title' => 'Send brochure']);
        $this->actingAs($marketing)->get(route('projects.show', $this->alpha))->assertOk()
            ->assertSee('Send brochure')
            ->assertSee('aria-selected="true" tabindex="0" data-tab="todos"', false)
            ->assertDontSee('Ask lead about fonts');

        $director = User::factory()->management()->create();
        $this->actingAs($director)->get(route('projects.show', $this->alpha))->assertOk()
            ->assertSee('aria-selected="true" tabindex="0" data-tab="todos"', false);
    }

    public function test_project_panel_requires_access_to_the_project(): void
    {
        $outsider = User::factory()->create();
        Task::factory()->create(['project_id' => $this->alpha->id, 'assignee_id' => $outsider->id, 'title' => 'Orphan task']);

        $this->actingAs($outsider)->get(route('projects.show', $this->alpha))->assertForbidden();
    }
}
