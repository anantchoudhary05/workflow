<?php

namespace App\Services;

use App\Enums\ApprovalOutcome;
use App\Enums\ClientActionType;
use App\Enums\QuotationStatus;
use App\Exceptions\WorkflowException;
use App\Models\Approval;
use App\Models\ClientAction;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\User;
use App\Support\Activity;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class QuotationService
{
    public function create(Project $project, array $header, array $items, User $user): Quotation
    {
        return DB::transaction(function () use ($project, $header, $items, $user) {
            $q = new Quotation($header);
            $q->project_id = $project->id;
            $q->client_id = $project->client_id;
            $q->number = sprintf('QT-%s-%02d', $project->code, $project->quotations()->distinct('number')->count('number') + 1);
            $q->version = 1;
            $q->status = QuotationStatus::Draft;
            $q->created_by = $user->id;
            $q->save();
            $this->syncItems($q, $items);
            Activity::log('quotation.created', "Quotation {$q->reference()} created", $q);

            return $q;
        });
    }

    public function update(Quotation $q, array $header, array $items): void
    {
        if ($q->isLocked()) {
            throw new WorkflowException('Sent or decided quotations are immutable. Create a new version instead.');
        }
        DB::transaction(function () use ($q, $header, $items) {
            $q->fill($header)->save();
            $this->syncItems($q, $items);
            Activity::log('quotation.updated', "Quotation {$q->reference()} updated", $q);
        });
    }

    /** Rule 20: all totals are recomputed here from line inputs; submitted totals are ignored. */
    public function syncItems(Quotation $q, array $items): void
    {
        $q->items()->delete();
        $sub = $opt = $cost = 0;
        $hours = 0.0;
        foreach (array_values($items) as $i => $row) {
            $qtyC = Money::cents($row['quantity'] ?? 1);
            $priceC = Money::cents($row['unit_price'] ?? 0);
            $lineC = intdiv($qtyC * $priceC + 50, 100);
            $h = (float) ($row['hours'] ?? 0);
            $costC = (int) round($h * Money::cents($row['cost_rate'] ?? 0));
            $optional = ! empty($row['is_optional']);
            $q->items()->create([
                'category' => $row['category'] ?? null,
                'description' => $row['description'],
                'quantity' => Money::fromCents($qtyC),
                'unit' => $row['unit'] ?? 'item',
                'hours' => $h,
                'cost_rate' => $row['cost_rate'] ?? 0,
                'internal_cost' => Money::fromCents($costC),
                'unit_price' => Money::fromCents($priceC),
                'line_total' => Money::fromCents($lineC),
                'is_optional' => $optional,
                'notes' => $row['notes'] ?? null,
                'sort_order' => $i,
            ]);
            if ($optional) {
                $opt += $lineC;
            } else {
                $sub += $lineC;
                $cost += $costC;
                $hours += $h;
            }
        }
        $discount = min(Money::cents($q->discount_amount ?? 0), $sub);
        $taxable = $sub - $discount;
        $tax = (int) round($taxable * (float) ($q->tax_rate ?? 0) / 100);
        $q->forceFill([
            'subtotal' => Money::fromCents($sub),
            'discount_amount' => Money::fromCents($discount),
            'tax_amount' => Money::fromCents($tax),
            'total' => Money::fromCents($taxable + $tax),
            'optional_total' => Money::fromCents($opt),
            'internal_cost_total' => Money::fromCents($cost),
            'estimated_hours' => $hours,
        ])->save();
    }

    public function submitForReview(Quotation $q): void
    {
        $this->move($q, QuotationStatus::Draft, QuotationStatus::InternalReview);
    }

    public function markSent(Quotation $q, User $user): void
    {
        if ($q->items()->count() === 0) {
            throw new WorkflowException('Add at least one line item before sending.');
        }
        DB::transaction(function () use ($q, $user) {
            $this->move($q, QuotationStatus::InternalReview, QuotationStatus::Sent, ['sent_at' => now()]);
            ClientAction::forceCreate([
                'client_id' => $q->client_id, 'project_id' => $q->project_id, 'type' => ClientActionType::QuotationApproval,
                'title' => "Approve quotation {$q->reference()}", 'related_type' => $q->getMorphClass(), 'related_id' => $q->id,
                'requested_at' => today(), 'next_follow_up_at' => today()->addDays((int) config('doc.reminders.client_follow_up_days')), 'owner_id' => $user->id, 'status' => 'waiting',
            ]);
        });
    }

    /** Rule 6: a decision freezes the version; approval links it to the project. */
    public function decide(Quotation $q, ApprovalOutcome $outcome, array $data, User $user): void
    {
        if ($q->status !== QuotationStatus::Sent) {
            throw new WorkflowException('Only sent quotations can be approved or rejected.');
        }
        DB::transaction(function () use ($q, $outcome, $data, $user) {
            $approved = $outcome->isPositive();
            $q->forceFill([
                'status' => $approved ? QuotationStatus::Approved : QuotationStatus::Rejected,
                'decided_by' => $user->id, 'decided_at' => $data['decided_at'],
                'decision_source' => $data['source_reference'], 'decision_notes' => $data['notes'] ?? null,
            ])->save();
            Approval::forceCreate([
                'project_id' => $q->project_id, 'approvable_type' => $q->getMorphClass(), 'approvable_id' => $q->id,
                'outcome' => $outcome, 'version_label' => $q->reference(), 'decided_by_name' => $data['decided_by_name'],
                'source_reference' => $data['source_reference'], 'notes' => $data['notes'] ?? null,
                'decided_at' => $data['decided_at'], 'recorded_by' => $user->id,
            ]);
            if ($approved) {
                $q->project->forceFill(['approved_quotation_id' => $q->id])->save();
            }
            ClientAction::where('related_type', $q->getMorphClass())->where('related_id', $q->id)->where('status', 'waiting')
                ->update(['status' => 'received', 'resolved_at' => now(), 'resolution' => $outcome->label()]);
            Activity::log('quotation.decided', "Quotation {$q->reference()} {$outcome->label()} by {$data['decided_by_name']}", $q,
                ['status' => ['to' => $q->status->value], 'total' => $q->total, 'source' => $data['source_reference']], true);
        });
    }

    public function newVersion(Quotation $q, User $user): Quotation
    {
        if ($q->status->isEditable()) {
            throw new WorkflowException('This version is still editable.');
        }
        if (Quotation::where('number', $q->number)->whereIn('status', [QuotationStatus::Draft, QuotationStatus::InternalReview])->exists()) {
            throw new WorkflowException('A newer editable version already exists for this quotation.');
        }

        return DB::transaction(function () use ($q, $user) {
            $new = $q->replicate(['status', 'sent_at', 'decided_by', 'decided_at', 'decision_source', 'decision_notes']);
            $new->version = Quotation::where('number', $q->number)->max('version') + 1;
            $new->status = QuotationStatus::Draft;
            $new->supersedes_id = $q->id;
            $new->created_by = $user->id;
            $new->save();
            foreach ($q->items as $item) {
                $new->items()->create($item->only($item->getFillable()));
            }
            if (in_array($q->status, [QuotationStatus::Sent, QuotationStatus::InternalReview], true)) {
                $q->forceFill(['status' => QuotationStatus::Superseded])->save();
            }
            Activity::log('quotation.version', "Quotation {$new->reference()} created from v{$q->version}", $new);

            return $new;
        });
    }

    private function move(Quotation $q, QuotationStatus $from, QuotationStatus $to, array $extra = []): void
    {
        if ($q->status !== $from) {
            throw new WorkflowException("Quotation must be {$from->label()} to move to {$to->label()}.");
        }
        $q->forceFill(['status' => $to] + $extra)->save();
        Activity::log('quotation.status', "Quotation {$q->reference()} moved to {$to->label()}", $q, ['status' => ['from' => $from->value, 'to' => $to->value]], true);
    }
}
