<?php

namespace Tests\Feature;

use App\Enums\ChangeRequestStatus;
use App\Models\ChangeRequest;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChangeRequestControllerTest extends TestCase
{
    use RefreshDatabase;

    private function logChange(User $user, Project $project, array $overrides = []): ChangeRequest
    {
        $this->actingAs($user)->post(route('projects.changes.store', $project), $overrides + [
            'title' => 'Add a newsletter signup',
            'description' => 'Client asked for a signup block in the footer.',
            'received_at' => today()->toDateString(),
            'priority' => 'medium',
        ])->assertRedirect();

        return ChangeRequest::latest('id')->firstOrFail();
    }

    public function test_admin_takes_new_scope_change_through_review_and_client_approval(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create(['manager_id' => $admin->id]);
        $cr = $this->logChange($admin, $project, ['classification' => 'new_scope', 'classification_rationale' => 'Not in the signed scope.']);

        $this->get(route('change-requests.show', $cr))->assertOk()->assertSee($cr->number);

        $this->post(route('change-requests.status', $cr), ['status' => 'under_assessment'])->assertSessionHasNoErrors();
        $this->post(route('change-requests.status', $cr), ['status' => 'awaiting_internal_review'])->assertSessionHasNoErrors();
        $this->post(route('change-requests.status', $cr), ['status' => 'awaiting_client_approval'])->assertSessionHasErrors('workflow');

        $this->post(route('change-requests.internal-review', $cr), ['notes' => 'Estimate checked.'])->assertSessionHasNoErrors();
        $this->post(route('change-requests.status', $cr), ['status' => 'awaiting_client_approval'])->assertSessionHasNoErrors();
        $this->post(route('change-requests.status', $cr), ['status' => 'approved'])->assertSessionHasErrors('workflow');

        $this->post(route('change-requests.client-decision', $cr), [
            'outcome' => 'approved',
            'decided_by_name' => 'Client Director',
            'source_reference' => 'Email of '.today()->toDateString(),
            'decided_at' => today()->toDateString(),
        ])->assertSessionHasNoErrors();

        $this->assertSame(ChangeRequestStatus::Approved, $cr->fresh()->status);
    }

    public function test_new_scope_cannot_be_scheduled_without_override(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create(['manager_id' => $admin->id]);
        $cr = $this->logChange($admin, $project, ['classification' => 'new_scope', 'classification_rationale' => 'Extra work.']);
        $this->post(route('change-requests.status', $cr), ['status' => 'under_assessment']);

        $this->post(route('change-requests.status', $cr), ['status' => 'scheduled'])->assertSessionHasErrors('workflow');
        $this->assertSame(ChangeRequestStatus::UnderAssessment, $cr->fresh()->status);
    }

    public function test_developer_logs_change_without_classifying_it(): void
    {
        $developer = User::factory()->create();
        $project = Project::factory()->create();
        $project->members()->attach($developer);

        $cr = $this->logChange($developer, $project, ['classification' => 'bug', 'classification_rationale' => 'Self-classified.']);

        $this->assertNull($cr->classification);
        $this->post(route('change-requests.status', $cr), ['status' => 'under_assessment'])->assertForbidden();
    }

    public function test_user_outside_project_cannot_view_change(): void
    {
        $admin = User::factory()->admin()->create();
        $cr = $this->logChange($admin, Project::factory()->create());

        $this->actingAs(User::factory()->create())->get(route('change-requests.show', $cr))->assertForbidden();
    }
}
