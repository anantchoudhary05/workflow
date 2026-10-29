<?php

namespace App\Enums;

enum ClientActionType: string
{
    use EnumHelpers;

    case RequirementsApproval = 'requirements_approval';
    case QuotationApproval = 'quotation_approval';
    case DesignApproval = 'design_approval';
    case PreviewFeedback = 'preview_feedback';
    case ChangeApproval = 'change_approval';
    case ContentSubmission = 'content_submission';
    case FinalAcceptance = 'final_acceptance';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::ContentSubmission => 'Content / asset submission',
            self::ChangeApproval => 'Change request approval',
            default => ucfirst(str_replace('_', ' ', $this->value)),
        };
    }
}
