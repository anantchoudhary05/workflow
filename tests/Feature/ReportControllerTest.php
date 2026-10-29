<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_exports_overdue_tasks_as_csv(): void
    {
        $task = Task::factory()->overdue()->create(['title' => '=HYPERLINK("x")']);

        $response = $this->actingAs(User::factory()->admin()->create())
            ->get(route('reports.export', ['report' => 'overdue-tasks']));

        $response->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();
        $this->assertStringContainsString($task->project->code, $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
    }

    public function test_developer_cannot_open_reports(): void
    {
        $this->actingAs(User::factory()->create())->get(route('reports.index'))->assertForbidden();
    }

    public function test_unknown_report_returns_not_found(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('reports.export', ['report' => 'nope']))->assertNotFound();
    }
}
