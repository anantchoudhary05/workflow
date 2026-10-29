<?php

namespace App\Enums;

enum ProjectStatus: string
{
    use EnumHelpers;

    case Lead = 'lead';
    case Planning = 'planning';
    case AwaitingApproval = 'awaiting_approval';
    case ReadyToStart = 'ready_to_start';
    case InProgress = 'in_progress';
    case OnHold = 'on_hold';
    case Blocked = 'blocked';
    case InReview = 'in_review';
    case Qa = 'qa';
    case ReadyForLaunch = 'ready_for_launch';
    case Delivered = 'delivered';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return $this === self::Qa ? 'QA' : ucwords(str_replace('_', ' ', $this->value));
    }

    public function tone(): string
    {
        return match ($this) {
            self::Lead, self::Planning => 'neutral',
            self::AwaitingApproval, self::OnHold => 'warning',
            self::Blocked, self::Cancelled => 'danger',
            self::Delivered, self::Closed => 'success',
            default => 'brand',
        };
    }

    /** Statuses counted as "active delivery work". */
    public static function active(): array
    {
        return [self::ReadyToStart, self::InProgress, self::Blocked, self::InReview, self::Qa, self::ReadyForLaunch];
    }

    /** Statuses counted as "pipeline" work: inquiries and pre-sale planning that has not started delivery yet. */
    public static function pipeline(): array
    {
        return [self::Lead, self::Planning, self::AwaitingApproval];
    }

    /**
     * Statuses a project may be created in.
     *
     * @return array<int, self>
     */
    public static function initial(): array
    {
        return [self::Lead, self::Planning, self::AwaitingApproval, self::ReadyToStart, self::InProgress];
    }

    /**
     * Options for the "Initial status" select, labelled with the dashboard group the status counts towards.
     *
     * @return array<string, string>
     */
    public static function initialOptions(): array
    {
        return collect(self::initial())->mapWithKeys(fn (self $s): array => [
            $s->value => $s->label().(in_array($s, self::active(), true) ? ' — active delivery' : ' — pipeline'),
        ])->all();
    }

    /** Dashboard/filter group: "active", "pipeline", "on_hold" or "finished". */
    public function group(): string
    {
        return match (true) {
            in_array($this, self::active(), true) => 'active',
            in_array($this, self::pipeline(), true) => 'pipeline',
            $this === self::OnHold => 'on_hold',
            default => 'finished',
        };
    }

    public static function open(): array
    {
        return array_values(array_filter(self::cases(), fn ($s) => ! in_array($s, [self::Delivered, self::Closed, self::Cancelled], true)));
    }

    /** Allowed transitions. Closing/delivery is handled by the closure workflow. */
    public function transitions(): array
    {
        return match ($this) {
            self::Lead => [self::Planning, self::Cancelled],
            self::Planning => [self::AwaitingApproval, self::ReadyToStart, self::OnHold, self::Cancelled],
            self::AwaitingApproval => [self::Planning, self::ReadyToStart, self::OnHold, self::Cancelled],
            self::ReadyToStart => [self::InProgress, self::OnHold, self::Cancelled],
            self::InProgress => [self::Blocked, self::OnHold, self::InReview, self::Qa, self::Cancelled],
            self::Blocked => [self::InProgress, self::OnHold, self::Cancelled],
            self::OnHold => [self::Planning, self::InProgress, self::Cancelled],
            self::InReview => [self::InProgress, self::Qa],
            self::Qa => [self::InProgress, self::ReadyForLaunch],
            self::ReadyForLaunch => [self::Qa, self::Delivered],
            self::Delivered => [self::InProgress],
            self::Closed => [],
            self::Cancelled => [self::Planning],
        };
    }

    public function stage(): string
    {
        return match ($this) {
            self::Lead => 'Inquiry',
            self::Planning => 'Requirements & Scope',
            self::AwaitingApproval => 'Quotation & Approval',
            self::ReadyToStart => 'Kickoff',
            self::InProgress, self::Blocked, self::OnHold => 'Design & Development',
            self::InReview => 'Client Preview & Feedback',
            self::Qa => 'QA & Testing',
            self::ReadyForLaunch => 'Launch',
            self::Delivered => 'Handover & Acceptance',
            self::Closed => 'Closed',
            self::Cancelled => 'Cancelled',
        };
    }
}
