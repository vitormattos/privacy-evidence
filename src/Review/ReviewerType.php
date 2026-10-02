<?php

declare(strict_types=1);

namespace PrivacyEvidence\Review;

enum ReviewerType: string
{
    case Human = 'human';
    case AiSuggestion = 'ai_suggestion';
    case Adjudicator = 'adjudicator';
}
