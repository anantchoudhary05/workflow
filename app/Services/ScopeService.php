<?php

namespace App\Services;

use App\Enums\RequirementStatus;
use App\Exceptions\WorkflowException;
use App\Models\Project;
use App\Models\ScopeVersion;
use App\Models\User;
use App\Support\Activity;
use Illuminate\Support\Facades\DB;

class ScopeService
{
    /** Freezes all currently approved requirements into a new immutable baseline. */
    public function baseline(Project $project, array $data, User $user): ScopeVersion
    {
        $reqs = $project->requirements()->whereIn('status', [RequirementStatus::Approved, RequirementStatus::Implemented, RequirementStatus::Verified])->orderBy('code')->get();
        if ($reqs->isEmpty()) {
            throw new WorkflowException('Approve at least one requirement before creating a scope baseline.');
        }

        return DB::transaction(function () use ($project, $data, $user, $reqs) {
            $version = (int) $project->scopeVersions()->max('version') + 1;
            $scope = ScopeVersion::create([
                'project_id' => $project->id,
                'version' => $version,
                'summary' => $data['summary'],
                'snapshot' => $reqs->map(fn ($r) => [
                    'id' => $r->id, 'code' => $r->code, 'title' => $r->title, 'category' => $r->category->value,
                    'priority' => $r->priority->value, 'acceptance_criteria' => $r->acceptance_criteria, 'estimated_hours' => $r->estimated_hours,
                ])->all(),
                'approval_source' => $data['approval_source'],
                'approved_by_name' => $data['approved_by_name'],
                'recorded_by' => $user->id,
                'approved_at' => $data['approved_at'],
            ]);
            $project->forceFill(['approved_scope_version_id' => $scope->id])->save();
            Activity::log('scope.approved', "Scope baseline v{$version} approved for {$project->code} ({$reqs->count()} requirements)", $project,
                ['scope_version' => $version, 'source' => $data['approval_source']], true);

            return $scope;
        });
    }
}
