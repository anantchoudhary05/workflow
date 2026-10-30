<?php

namespace Tests\Feature;

use App\Enums\ReviewRequestStatus;
use App\Models\Project;
use App\Models\ReviewRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewAndHandoffTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_lead_requests_review_and_manager_decides(): void
    {
        $lead = User::factory()->teamLead()->create();
        $manager = User::factory()->manager()->create();
        $project = Project::factory()->create();

        $this->actingAs($lead)->post(route('projects.review-requests.store', $project), ['title' => 'Homepage ready', 'url' => 'https://staging.example.test'])->assertRedirect();
        $review = ReviewRequest::firstOrFail();
        $this->assertSame(ReviewRequestStatus::Pending, $review->status);
        $this->assertTrue($manager->notifications()->exists());

        $this->actingAs($manager)->get(route('reviews.index'))->assertOk()->assertSee('Homepage ready');
        $this->actingAs($lead)->post(route('review-requests.decide', $review), ['status' => 'ready_for_preview'])->assertForbidden();
        $this->actingAs($manager)->post(route('review-requests.decide', $review), ['status' => 'needs_corrections'])->assertSessionHasErrors('decision_notes');
        $this->actingAs($manager)->post(route('review-requests.decide', $review), ['status' => 'needs_corrections', 'decision_notes' => 'Fix the footer'])->assertRedirect();

        $review->refresh();
        $this->assertSame(ReviewRequestStatus::NeedsCorrections, $review->status);
        $this->assertSame($manager->id, $review->decided_by);
        $this->assertTrue($lead->notifications()->exists());
        $this->actingAs($manager)->post(route('review-requests.decide', $review), ['status' => 'ready_for_preview'])->assertStatus(422);
    }

    public function test_developers_and_management_cannot_use_review_queue(): void
    {
        $project = Project::factory()->create();
        $developer = User::factory()->create();
        $project->members()->attach($developer);

        $this->actingAs($developer)->post(route('projects.review-requests.store', $project), ['title' => 'x'])->assertForbidden();
        $this->actingAs($developer)->get(route('reviews.index'))->assertForbidden();
        $this->actingAs(User::factory()->management()->create())->post(route('projects.review-requests.store', $project), ['title' => 'x'])->assertForbidden();
    }

    public function test_marketing_hands_off_and_team_lead_acknowledges(): void
    {
        $marketing = User::factory()->marketing()->create();
        $lead = User::factory()->teamLead()->create();
        $project = Project::factory()->create();

        $this->actingAs($marketing)->post(route('projects.handoff', $project), [])->assertSessionHasErrors('handoff_notes');
        $this->actingAs($marketing)->post(route('projects.handoff', $project), ['handoff_notes' => 'Requirements and contract uploaded.'])->assertRedirect();

        $project->refresh();
        $this->assertSame($marketing->id, $project->handed_off_by);
        $this->assertNull($project->handoff_acknowledged_at);
        $this->assertTrue($lead->notifications()->exists());
        $this->actingAs($lead)->get(route('dashboard'))->assertSee($project->name)->assertSee('New from Marketing');

        $this->actingAs($marketing)->post(route('projects.handoff.acknowledge', $project))->assertForbidden();
        $this->actingAs($lead)->post(route('projects.handoff.acknowledge', $project))->assertRedirect();
        $this->assertSame($lead->id, $project->fresh()->handoff_acknowledged_by);
        $this->assertTrue($marketing->notifications()->exists());
        $this->actingAs($lead)->post(route('projects.handoff.acknowledge', $project))->assertStatus(422);
    }

    public function test_developers_cannot_hand_off(): void
    {
        $project = Project::factory()->create();
        $developer = User::factory()->create();
        $project->members()->attach($developer);

        $this->actingAs($developer)->post(route('projects.handoff', $project), ['handoff_notes' => 'x'])->assertForbidden();
    }

    public function test_new_pages_render(): void
    {
        $manager = User::factory()->manager()->create();
        foreach (['todos.index', 'reviews.index', 'team.index', 'dashboard'] as $route) {
            $this->actingAs($manager)->get(route($route))->assertOk();
        }
    }
}
