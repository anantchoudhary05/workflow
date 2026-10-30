<?php

namespace App\Http\Controllers;

use App\Enums\ReviewRequestStatus;
use App\Enums\Role;
use App\Models\Project;
use App\Models\ReviewRequest;
use App\Models\User;
use App\Services\Notifier;
use App\Support\Activity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Manager review queue. A Team Lead asks "is this ready for client preview?"; the Manager answers
 * "Ready for client preview" or "Needs corrections" with notes. Both sides are notified.
 */
class ReviewRequestController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->can('approve-for-preview') || $user->can('request-review'), 403);
        $base = ReviewRequest::query()->visibleTo($user)->with(['project:id,code,name,priority', 'requester:id,name', 'decider:id,name']);

        return view('reviews.index', [
            'pending' => (clone $base)->where('status', ReviewRequestStatus::Pending)->oldest()->get(),
            'decided' => (clone $base)->where('status', '!=', ReviewRequestStatus::Pending)->latest('decided_at')->limit(30)->get(),
            'canDecide' => $user->can('approve-for-preview'),
        ]);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('project-ability', [$project, 'request-review']);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'url' => ['nullable', 'url', 'max:500'],
        ]);
        $review = new ReviewRequest($data);
        $review->project_id = $project->id;
        $review->requested_by = $request->user()->id;
        $review->status = ReviewRequestStatus::Pending;
        $review->save();
        Activity::log('review.requested', "Manager review requested: \"{$review->title}\"", $review);
        foreach (self::managers() as $manager) {
            Notifier::send($manager, "Review requested on {$project->code}: {$review->title}", route('reviews.index'), 'info', "review-{$review->id}");
        }

        return back()->with('status', 'Review requested. Managers have been notified.');
    }

    public function decide(Request $request, ReviewRequest $reviewRequest): RedirectResponse
    {
        $this->authorize('project-ability', [$reviewRequest->project, 'approve-for-preview']);
        abort_unless($reviewRequest->isPending(), 422, 'This review has already been decided.');
        $data = $request->validate([
            'status' => ['required', Rule::in([ReviewRequestStatus::ReadyForPreview->value, ReviewRequestStatus::NeedsCorrections->value])],
            'decision_notes' => [Rule::requiredIf($request->input('status') === ReviewRequestStatus::NeedsCorrections->value), 'nullable', 'string', 'max:5000'],
        ], ['decision_notes.required' => 'Describe the corrections needed.']);
        $reviewRequest->status = ReviewRequestStatus::from($data['status']);
        $reviewRequest->decision_notes = $data['decision_notes'] ?? null;
        $reviewRequest->decided_by = $request->user()->id;
        $reviewRequest->decided_at = now();
        $reviewRequest->save();
        Activity::log('review.decided', "Manager review \"{$reviewRequest->title}\": {$reviewRequest->status->label()}", $reviewRequest, [], true);
        Notifier::send(
            $reviewRequest->requester,
            "{$reviewRequest->status->label()}: {$reviewRequest->title}",
            route('projects.show', $reviewRequest->project_id),
            $reviewRequest->status === ReviewRequestStatus::ReadyForPreview ? 'success' : 'warning',
        );

        return back()->with('status', 'Review decision recorded.');
    }

    /**
     * Active Managers, or administrators when no Manager account exists yet.
     *
     * @return Collection<int, User>
     */
    public static function managers(): Collection
    {
        $managers = User::active()->where('role', Role::Manager)->get();

        return $managers->isNotEmpty() ? $managers : User::active()->where('role', Role::Admin)->get();
    }
}
