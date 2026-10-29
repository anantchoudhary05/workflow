<?php

namespace App\Enums;

enum MeetingType: string
{
    use EnumHelpers;

    case Discovery = 'discovery';
    case Kickoff = 'kickoff';
    case DesignReview = 'design_review';
    case Progress = 'progress';
    case Technical = 'technical';
    case ClientFeedback = 'client_feedback';
    case DeliveryReview = 'delivery_review';
    case Other = 'other';
}
