<?php

namespace Tests\Feature\Console;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SendRemindersTest extends TestCase
{
    use RefreshDatabase;

    public function test_notifies_assignee_of_overdue_task_once_per_day(): void
    {
        $developer = User::factory()->create();
        Task::factory()->overdue()->create(['assignee_id' => $developer->id, 'status' => TaskStatus::InProgress]);

        $this->artisan('doc:send-reminders')->assertSuccessful();
        $this->artisan('doc:send-reminders')->assertSuccessful();

        $this->assertSame(1, $developer->notifications()->count());
        $this->assertStringStartsWith('Overdue', $developer->notifications()->first()->data['title']);
    }

    public function test_skips_tasks_that_are_not_due_or_are_done(): void
    {
        $developer = User::factory()->create();
        Task::factory()->create(['assignee_id' => $developer->id]);
        Task::factory()->overdue()->create(['assignee_id' => $developer->id, 'status' => TaskStatus::Done]);

        $this->artisan('doc:send-reminders')->assertSuccessful();

        $this->assertSame(0, $developer->notifications()->count());
    }
}
