<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Models\ChangeRequest;
use App\Models\Client;
use App\Models\ClientAction;
use App\Models\Communication;
use App\Models\Defect;
use App\Models\Meeting;
use App\Models\Milestone;
use App\Models\Preview;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\Task;
use App\Models\TestCase as QaTestCase;
use App\Models\User;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SubmitsForms;
use Tests\TestCase;

/**
 * End-to-end create forms: render the real form, fill it like a user, submit, and check that
 * every value the user chose is the value that was saved.
 */
class FormSubmissionTest extends TestCase
{
    use RefreshDatabase;
    use SubmitsForms;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        $this->actingAs($this->admin);
    }

    /**
     * Assert each submitted value equals the stored attribute (enums, dates and decimals normalised).
     *
     * @param  array<string, mixed>  $values
     */
    private function assertSaved(Model $model, array $values): void
    {
        $model->refresh();
        foreach ($values as $key => $expected) {
            $actual = $model->getAttribute($key);
            $actual = match (true) {
                $actual instanceof BackedEnum => $actual->value,
                $actual instanceof DateTimeInterface => strlen((string) $expected) > 10 ? $actual->format('Y-m-d\TH:i') : $actual->format('Y-m-d'),
                is_bool($actual) => $actual ? '1' : '0',
                default => $actual,
            };
            if (is_numeric($expected) && is_numeric($actual)) {
                $this->assertEqualsWithDelta((float) $expected, (float) $actual, 0.001, "Saved {$key} differs from the submitted value.");

                continue;
            }
            $this->assertSame((string) $expected, (string) $actual, "Saved {$key} differs from the submitted value.");
        }
    }

    public function test_client_form_saves_submitted_values(): void
    {
        $values = [
            'name' => 'Ravi Kumar', 'company_name' => 'Kumar Clinics', 'email' => 'ravi@kumar.test', 'phone' => '+91 98765 43210',
            'website' => 'https://kumar.test', 'status' => 'active', 'source' => 'Referral', 'notes' => 'Prefers WhatsApp.',
        ];

        $this->submitForm(route('clients.create'), route('clients.store'), $values)->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSaved(Client::where('email', 'ravi@kumar.test')->sole(), $values);
    }

    public function test_project_form_saves_the_selected_initial_status(): void
    {
        $client = Client::factory()->create();
        $values = [
            'name' => 'Clinic portal', 'client_id' => (string) $client->id, 'type' => 'web_application', 'priority' => 'high',
            'manager_id' => (string) $this->admin->id, 'status' => 'in_progress', 'start_date' => '2026-11-02', 'due_date' => '2026-12-20',
            'estimated_hours' => '120', 'description' => 'Patient portal.',
        ];

        $this->submitForm(route('projects.create'), route('projects.store'), $values)->assertSessionHasNoErrors()->assertRedirect();

        $project = Project::where('name', 'Clinic portal')->sole();
        $this->assertSaved($project, $values);
        $this->assertSame(ProjectStatus::InProgress, $project->status);
        $this->assertDatabaseHas('project_status_histories', ['project_id' => $project->id, 'from_status' => null, 'to_status' => 'in_progress']);
    }

    public function test_task_form_saves_submitted_values_and_initial_status(): void
    {
        $project = Project::factory()->create();
        $values = [
            'title' => 'Build login page', 'description' => 'Email + password.', 'assignee_id' => (string) $this->admin->id,
            'priority' => 'urgent', 'status' => 'ready', 'start_date' => '2026-11-02', 'due_date' => '2026-11-06', 'estimated_hours' => '6.5',
            'tags' => 'frontend, auth', 'acceptance_criteria' => 'User can sign in.', 'internal_notes' => 'Use the shared layout.',
        ];

        $this->submitForm(route('tasks.create', ['project' => $project->id]), route('tasks.store'), $values)->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSaved(Task::where('title', 'Build login page')->sole(), $values + ['project_id' => $project->id]);
    }

    public function test_meeting_form_saves_submitted_values(): void
    {
        $project = Project::factory()->create();
        $values = [
            'title' => 'Kick-off call', 'client_id' => (string) $project->client_id, 'project_id' => (string) $project->id, 'type' => 'kickoff',
            'starts_at' => '2026-11-03T10:30', 'duration_minutes' => '45', 'agenda' => 'Scope walkthrough.',
        ];

        $this->submitForm(route('meetings.create'), route('meetings.store'), $values)->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSaved(Meeting::where('title', 'Kick-off call')->sole(), $values);
    }

    public function test_communication_dialog_saves_submitted_values(): void
    {
        $project = Project::factory()->create();
        $values = [
            'client_id' => (string) $project->client_id, 'project_id' => (string) $project->id, 'type' => 'phone', 'direction' => 'outbound',
            'subject' => 'Content reminder', 'summary' => 'Asked for homepage copy.', 'occurred_at' => '2026-10-28T15:00',
        ];

        $this->submitForm(route('communications.index'), route('communications.store'), $values)->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSaved(Communication::where('subject', 'Content reminder')->sole(), $values);
    }

    public function test_client_action_dialog_saves_submitted_values(): void
    {
        $project = Project::factory()->create();
        $values = [
            'project_id' => (string) $project->id, 'type' => 'content_submission', 'title' => 'Send homepage copy',
            'requested_at' => '2026-10-28', 'next_follow_up_at' => '2026-11-02', 'owner_id' => (string) $this->admin->id,
        ];

        $this->submitForm(route('client-actions.index'), route('client-actions.store'), $values)->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSaved(ClientAction::where('title', 'Send homepage copy')->sole(), $values);
    }

    public function test_requirement_dialog_saves_submitted_values(): void
    {
        $project = Project::factory()->create();
        $values = [
            'title' => 'Online booking', 'category' => 'functional', 'priority' => 'high', 'description' => 'Book appointments.',
            'acceptance_criteria' => 'Slots shown per doctor.', 'module' => 'Booking', 'estimated_hours' => '16',
        ];

        $this->submitForm(route('projects.requirements', $project), route('projects.requirements.store', $project), $values)->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSaved(Requirement::where('title', 'Online booking')->sole(), $values);
    }

    public function test_milestone_dialog_saves_submitted_values(): void
    {
        $project = Project::factory()->create();
        $values = [
            'name' => 'Design sign-off', 'owner_id' => (string) $this->admin->id, 'priority' => 'high', 'start_date' => '2026-11-01',
            'due_date' => '2026-11-15', 'weight' => '3', 'description' => 'Homepage and inner pages.',
        ];

        $this->submitForm(route('projects.milestones', $project), route('projects.milestones.store', $project), $values)->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSaved(Milestone::where('name', 'Design sign-off')->sole(), $values);
    }

    public function test_change_request_dialog_saves_submitted_values(): void
    {
        $project = Project::factory()->create();
        $values = [
            'title' => 'Add Hindi language', 'description' => 'Second language for all pages.', 'requested_by' => 'Dr. Mehta',
            'source' => 'Email', 'received_at' => '2026-10-27', 'priority' => 'medium', 'reason' => 'Patients prefer Hindi.',
        ];

        $this->submitForm(route('projects.changes', $project), route('projects.changes.store', $project), $values)->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSaved(ChangeRequest::where('title', 'Add Hindi language')->sole(), $values);
    }

    public function test_test_case_and_defect_dialogs_save_submitted_values(): void
    {
        $project = Project::factory()->create();
        $case = [
            'title' => 'Login with valid password', 'preconditions' => 'User exists.', 'steps' => 'Open /login, sign in.',
            'expected_result' => 'Dashboard opens.',
        ];
        $this->submitForm(route('projects.qa', $project), route('projects.test-cases.store', $project), $case)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSaved(QaTestCase::where('title', 'Login with valid password')->sole(), $case);

        $defect = [
            'title' => 'Login button overlaps footer', 'severity' => 'major', 'priority' => 'high', 'steps' => 'Open on 360px.',
            'expected_behavior' => 'Button above footer.', 'actual_behavior' => 'Overlaps.', 'assignee_id' => (string) $this->admin->id,
        ];
        $this->submitForm(route('projects.qa', $project), route('projects.defects.store', $project), $defect)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSaved(Defect::where('title', 'Login button overlaps footer')->sole(), $defect);
    }

    public function test_preview_dialog_saves_submitted_values(): void
    {
        $project = Project::factory()->create();
        $values = [
            'title' => 'Homepage v1', 'url' => 'https://staging.example.test', 'version_label' => 'v1.0',
            'review_due_at' => '2026-11-05', 'internal_notes' => 'Check mobile menu.',
        ];

        $this->submitForm(route('projects.previews', $project), route('projects.previews.store', $project), $values)->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSaved(Preview::where('title', 'Homepage v1')->sole(), $values);
    }

    public function test_user_form_saves_submitted_values(): void
    {
        $values = [
            'name' => 'Priya Sharma', 'email' => 'priya@example.test', 'role' => 'reviewer', 'job_title' => 'QA lead',
            'weekly_capacity_hours' => '32', 'skills' => 'testing, accessibility',
            'password' => 'a-Long-unique-passphrase-91', 'password_confirmation' => 'a-Long-unique-passphrase-91',
        ];

        $this->submitForm(route('users.create'), route('users.store'), $values)->assertSessionHasNoErrors()->assertRedirect();

        $user = User::where('email', 'priya@example.test')->sole();
        $this->assertSaved($user, collect($values)->except(['password', 'password_confirmation'])->all());
        $this->assertNotSame('a-Long-unique-passphrase-91', $user->getRawOriginal('password'));
    }
}
