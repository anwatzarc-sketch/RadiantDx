<?php

declare(strict_types=1);

namespace App\Enums;

use App\Support\Enums\ProvidesSharedEnumMetadata;
use App\Support\Enums\SharedEnum;

/**
 * Basis on which a staff member is engaged.
 */
enum EmploymentType: string implements SharedEnum
{
    use ProvidesSharedEnumMetadata;

    case Permanent = 'permanent';
    case Contract = 'contract';
    case Locum = 'locum';
    case PartTime = 'part_time';
    case Intern = 'intern';
    case Volunteer = 'volunteer';


    public function label(): string
    {
        return match ($this) {
            self::Permanent => 'Permanent',
            self::Contract => 'Contract',
            self::Locum => 'Locum',
            self::PartTime => 'Part-time',
            self::Intern => 'Intern',
            self::Volunteer => 'Volunteer',
        };
    }
}
