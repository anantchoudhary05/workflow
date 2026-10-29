<?php

namespace App\Enums;

enum FileCategory: string
{
    use EnumHelpers;

    case Requirements = 'requirements';
    case Quotation = 'quotation';
    case Scope = 'scope';
    case Design = 'design';
    case ClientAsset = 'client_asset';
    case MeetingNotes = 'meeting_notes';
    case QaEvidence = 'qa_evidence';
    case Handover = 'handover';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::QaEvidence => 'QA evidence',
            self::ClientAsset => 'Client asset',
            default => ucfirst(str_replace('_', ' ', $this->value)),
        };
    }
}
