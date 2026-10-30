<?php

namespace App\Http\Controllers;

use App\Enums\ApprovalOutcome;
use App\Enums\QuotationStatus;
use App\Exceptions\WorkflowException;
use App\Http\Requests\QuotationRequest;
use App\Models\Project;
use App\Models\Quotation;
use App\Services\QuotationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Every action requires the view-financials ability (Rule 19); writes are admin-only. */
class QuotationController extends Controller
{
    public function __construct(private QuotationService $service) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Quotation::class);

        return view('quotations.index', [
            'quotations' => Quotation::query()->visibleTo($request->user())
                ->with(['project:id,code,name', 'client:id,name'])
                ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
                ->latest('updated_at')->paginate(30)->withQueryString(),
            'statuses' => QuotationStatus::options(),
        ]);
    }

    public function forProject(Project $project): View
    {
        $this->authorize('viewAny', Quotation::class);
        $this->authorize('view', $project);

        return view('projects.quotations', [
            'project' => $project,
            'quotations' => $project->quotations()->with('creator:id,name')->orderBy('number')->orderByDesc('version')->get(),
        ]);
    }

    public function create(Project $project): View
    {
        $this->authorize('create', Quotation::class);
        $this->authorize('view', $project);
        $q = new Quotation([
            'currency' => config('doc.currency'),
            'title' => Str::limit("{$project->name} — development quotation", 250),
            'scope_version_id' => $project->approved_scope_version_id,
            'scope_summary' => $project->approvedScope?->summary,
        ]);

        return view('quotations.form', ['quotation' => $q, 'project' => $project, 'items' => old('items', [[]]), 'scopes' => $this->scopes($project)]);
    }

    public function store(QuotationRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('view', $project);
        $q = $this->service->create($project, $request->quotationData(), $request->validated('items'), $request->user());

        return redirect()->route('quotations.show', $q)->with('status', 'Quotation saved as draft.');
    }

    public function show(Quotation $quotation): View
    {
        $this->authorize('view', $quotation);
        $quotation->load(['items', 'project', 'client', 'creator:id,name', 'decider:id,name', 'scopeVersion']);

        return view('quotations.show', [
            'quotation' => $quotation,
            'versions' => Quotation::where('number', $quotation->number)->orderByDesc('version')->get(['id', 'version', 'status', 'total', 'updated_at']),
            'outcomes' => ApprovalOutcome::options(),
        ]);
    }

    public function edit(Quotation $quotation): View
    {
        $this->authorize('update', $quotation);
        $this->ensureEditable($quotation);

        return view('quotations.form', [
            'quotation' => $quotation, 'project' => $quotation->project,
            'items' => old('items', $quotation->items->map->toArray()->all()),
            'scopes' => $this->scopes($quotation->project),
        ]);
    }

    public function update(QuotationRequest $request, Quotation $quotation): RedirectResponse
    {
        $this->ensureEditable($quotation);
        $this->service->update($quotation, $request->quotationData(), $request->validated('items'));

        return redirect()->route('quotations.show', $quotation)->with('status', 'Quotation updated.');
    }

    public function print(Quotation $quotation): View
    {
        $this->authorize('view', $quotation);

        return view('quotations.print', ['quotation' => $quotation->load(['items', 'project', 'client'])]);
    }

    public function submit(Quotation $quotation): RedirectResponse
    {
        $this->authorize('update', $quotation);
        $this->service->submitForReview($quotation);

        return back()->with('status', 'Submitted for internal review.');
    }

    public function send(Request $request, Quotation $quotation): RedirectResponse
    {
        $this->authorize('send', $quotation);
        $this->service->markSent($quotation, $request->user());

        return back()->with('status', 'Marked as sent. A client approval follow-up was created.');
    }

    public function decide(Request $request, Quotation $quotation): RedirectResponse
    {
        $this->authorize('decide', $quotation);
        $data = $request->validate([
            'outcome' => ['required', Rule::in([ApprovalOutcome::Approved->value, ApprovalOutcome::ApprovedWithConditions->value, ApprovalOutcome::Rejected->value])],
            'decided_by_name' => ['required', 'string', 'max:255'],
            'source_reference' => ['required', 'string', 'max:255'],
            'decided_at' => ['required', 'date', 'before_or_equal:now'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $this->service->decide($quotation, ApprovalOutcome::from($data['outcome']), $data, $request->user());

        return back()->with('status', 'Client decision recorded.');
    }

    public function newVersion(Request $request, Quotation $quotation): RedirectResponse
    {
        $this->authorize('update', $quotation);
        $new = $this->service->newVersion($quotation, $request->user());

        return redirect()->route('quotations.edit', $new)->with('status', "Version {$new->version} created as draft.");
    }

    private function ensureEditable(Quotation $q): void
    {
        if ($q->isLocked()) {
            throw new WorkflowException("{$q->reference()} is {$q->status->label()} and can no longer be edited. Create a new version instead.");
        }
    }

    private function scopes(Project $project): array
    {
        return $project->scopeVersions()->orderByDesc('version')->get()->mapWithKeys(fn ($s) => [$s->id => "v{$s->version} — {$s->approved_at->format('d M Y')}"])->all();
    }
}
