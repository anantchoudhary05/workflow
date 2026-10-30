<?php

namespace Tests\Feature;

use App\Enums\MilestoneStatus;
use App\Enums\TaskStatus;
use App\Models\ActivityLog;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\MilestoneTrack;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MilestoneTrackTest extends TestCase
{
    use RefreshDatabase;

    private User $dev;

    private User $lead;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dev = User::factory()->create(['name' => 'Devika Dev']);
        $this->lead = User::factory()->teamLead()->create(['name' => 'Lena Lead']);
        $this->project = Project::factory()->create(['name' => 'Journey Shop']);
        $this->project->members()->attach([$this->dev->id, $this->lead->id]);
    }

    private function milestone(string $name, int $order, MilestoneStatus $status = MilestoneStatus::Planned, array $extra = []): Milestone
    {
        $m = $this->project->milestones()->create(['name' => $name, 'sort_order' => $order]);
        $m->forceFill(['status' => $status] + $extra)->save();

        return $m;
    }

    private function task(string $title, ?Milestone $milestone, TaskStatus $status = TaskStatus::Ready, array $attrs = []): Task
    {
        return Task::factory()->create($attrs + [
            'project_id' => $this->project->id,
            'milestone_id' => $milestone?->id,
            'title' => $title,
            'assignee_id' => $this->dev->id,
            'status' => $status,
            'completed_at' => $status === TaskStatus::Done ? now()->subDay() : null,
        ]);
    }

    /** HTML of one milestone's detail panel. */
    private function panel(string $html, string $key): string
    {
        $start = strpos($html, 'data-ms-panel="'.$key.'"');
        $this->assertNotFalse($start, "Panel {$key} is missing");
        $next = strpos($html, 'data-ms-panel="', $start + 10);

        return substr($html, $start, $next === false ? null : $next - $start);
    }

    public function test_track_counts_tasks_per_milestone_and_picks_the_current_stop(): void
    {
        $design = $this->milestone('Design sign-off', 1, MilestoneStatus::Completed, ['completed_at' => now()->subDays(3)]);
        $build = $this->milestone('Build', 2, MilestoneStatus::InProgress);
        $launch = $this->milestone('Launch', 3);
        $this->task('Wireframes', $design, TaskStatus::Done);
        $this->task('Visual design', $design, TaskStatus::Done);
        $this->task('Cart page', $build, TaskStatus::Done);
        $this->task('Checkout page', $build, TaskStatus::InProgress);
        $this->task('Search page', $build);
        $this->task('Old idea', $build, TaskStatus::Cancelled);
        $this->task('Go live', $launch);

        $track = (new MilestoneTrack($this->project))->build();
        $stops = $track['stops']->keyBy('key');

        $this->assertSame(['done', 'current', 'upcoming'], $track['stops']->pluck('state')->all());
        $this->assertSame([2, 2, 100], [$stops[$design->id]->done, $stops[$design->id]->total, $stops[$design->id]->percent]);
        $this->assertSame([1, 3, 33], [$stops[$build->id]->done, $stops[$build->id]->total, $stops[$build->id]->percent], 'Cancelled tasks are not counted');
        $this->assertSame([0, 1], [$stops[$launch->id]->done, $stops[$launch->id]->total]);
        $this->assertSame(['done' => 3, 'total' => 6, 'milestonesDone' => 1, 'milestones' => 3], array_intersect_key($track['overall'], array_flip(['done', 'total', 'milestonesDone', 'milestones'])));
        $this->assertNull($track['unplanned']);

        $this->actingAs($this->dev)->get(route('projects.show', $this->project))->assertOk()
            ->assertSee('id="milestone-journey"', false)
            ->assertSee('data-ms-count="2/2"', false)->assertSee('data-ms-count="1/3"', false)->assertSee('data-ms-count="0/1"', false)
            ->assertSee('data-state="done"', false)->assertSee('data-state="current"', false)->assertSee('data-state="upcoming"', false)
            ->assertSee('aria-controls="ms-panel-'.$build->id.'"', false)
            ->assertSee('You are here · 33%');
    }

    public function test_milestone_panel_lists_only_tasks_completed_in_that_milestone(): void
    {
        $design = $this->milestone('Design sign-off', 1);
        $build = $this->milestone('Build', 2);
        $wire = $this->task('Wireframes', $design, TaskStatus::Done);
        $open = $this->task('Style guide', $design, TaskStatus::InProgress);
        $cart = $this->task('Cart page', $build, TaskStatus::Done);
        $trashed = $this->task('Deleted thing', $design, TaskStatus::Done);
        $trashed->delete();

        $html = $this->actingAs($this->dev)->get(route('projects.show', $this->project))->assertOk()->getContent();
        $panel = $this->panel($html, (string) $design->id);

        $this->assertStringContainsString('data-ms-task="'.$wire->id.'"', $panel);
        $this->assertStringContainsString('Wireframes', $panel);
        $this->assertStringContainsString($this->dev->initials(), $panel);
        $this->assertStringContainsString(now()->subDay()->format('d M Y'), $panel);
        $this->assertStringNotContainsString('data-ms-task="'.$cart->id.'"', $panel);
        $this->assertStringNotContainsString('data-ms-task="'.$open->id.'"', $panel, 'Open tasks are not in the completed list');
        $this->assertStringContainsString('data-ms-open-task="'.$open->id.'"', $panel, 'Open tasks are listed (collapsed) below');
        $this->assertStringNotContainsString('Deleted thing', $panel);
        $this->assertStringContainsString('data-ms-task="'.$cart->id.'"', $this->panel($html, (string) $build->id));
    }

    public function test_tasks_without_a_milestone_get_their_own_bucket_and_cancelled_milestones_are_left_out(): void
    {
        $build = $this->milestone('Build', 1);
        $dropped = $this->milestone('Dropped phase', 2, MilestoneStatus::Cancelled);
        $this->task('Cart page', $build);
        $loose = $this->task('Fix favicon', null, TaskStatus::Done);
        $this->task('Update footer', null);

        $track = (new MilestoneTrack($this->project))->build();
        $this->assertSame([$build->id], $track['stops']->pluck('milestone.id')->all());
        $this->assertSame(1, $track['cancelled']);
        $this->assertSame([1, 2, 50], [$track['unplanned']->done, $track['unplanned']->total, $track['unplanned']->percent]);

        $html = $this->actingAs($this->dev)->get(route('projects.show', $this->project))->assertOk()
            ->assertSee('data-ms-toggle="unplanned"', false)->assertSee('Unplanned work')
            ->assertDontSee('Dropped phase')->getContent();
        $this->assertStringContainsString('data-ms-task="'.$loose->id.'"', $this->panel($html, 'unplanned'));
    }

    public function test_milestone_query_parameter_opens_that_panel(): void
    {
        $design = $this->milestone('Design sign-off', 1);
        $build = $this->milestone('Build', 2);

        $this->actingAs($this->dev)->get(route('projects.show', [$this->project, 'milestone' => $build->id]))->assertOk()
            ->assertSee('aria-expanded="true" aria-controls="ms-panel-'.$build->id.'"', false)
            ->assertSee('aria-expanded="false" aria-controls="ms-panel-'.$design->id.'"', false);

        // Unknown or malformed values open nothing instead of failing.
        $this->get(route('projects.show', $this->project).'?milestone[]=1')->assertOk()->assertDontSee('aria-expanded="true" aria-controls="ms-panel-', false);
        $this->get(route('projects.show', [$this->project, 'milestone' => 99999]))->assertOk()->assertDontSee('aria-expanded="true" aria-controls="ms-panel-', false);
    }

    public function test_outsiders_cannot_see_the_track(): void
    {
        $this->milestone('Secret phase', 1);
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->get(route('projects.show', $this->project))->assertForbidden();
        $this->actingAs($outsider)->get(route('projects.show', [$this->project, 'milestone' => 'unplanned']))->assertForbidden();
    }

    public function test_empty_state_offers_add_milestone_only_to_people_who_may_manage_milestones(): void
    {
        $this->actingAs($this->lead)->get(route('projects.show', $this->project))->assertOk()
            ->assertSee('data-ms-empty', false)->assertSee('data-ms-add', false)
            ->assertSee(route('projects.milestones', $this->project).'#new-milestone', false);

        $this->actingAs($this->dev)->get(route('projects.show', $this->project))->assertOk()
            ->assertSee('data-ms-empty', false)->assertDontSee('data-ms-add', false);
    }

    public function test_completed_log_lists_finished_tasks_and_milestones_with_who_completed_them(): void
    {
        $design = $this->milestone('Design sign-off', 1, MilestoneStatus::Completed, ['completed_at' => now()->subHours(2)]);
        $task = $this->task('Wireframes', $design, TaskStatus::Done, ['completed_at' => now()->subHours(5)]);
        $this->task('Still open', $design);
        ActivityLog::create(['user_id' => $this->lead->id, 'project_id' => $this->project->id, 'subject_type' => $task->getMorphClass(), 'subject_id' => $task->id,
            'event' => 'task.status', 'description' => 'Task completed', 'changes' => ['status' => ['from' => 'in_review', 'to' => 'done']]]);

        $log = (new MilestoneTrack($this->project))->build()['log'];
        $this->assertSame(['milestone', 'task'], $log->pluck('type')->all(), 'Newest first, open tasks left out');
        $this->assertSame('Lena Lead', $log[1]->by, 'The person who completed it, from the activity log');

        $this->actingAs($this->dev)->get(route('projects.show', $this->project))->assertOk()
            ->assertSee('data-completed-log', false)
            ->assertSee('data-log-entry="task-'.$task->id.'"', false)
            ->assertSee('data-log-entry="milestone-'.$design->id.'"', false)
            ->assertDontSee('data-log-entry="task-'.($task->id + 1).'"', false);
    }
}
