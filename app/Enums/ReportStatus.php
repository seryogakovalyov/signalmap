<?php

namespace App\Enums;

enum ReportStatus: string
{
    case Unverified = 'unverified';
    case PartiallyConfirmed = 'partially_confirmed';
    case Confirmed = 'confirmed';
    case Resolved = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::Unverified => 'Unverified',
            self::PartiallyConfirmed => 'Partially confirmed',
            self::Confirmed => 'Confirmed',
            self::Resolved => 'Resolved',
        };
    }
}
