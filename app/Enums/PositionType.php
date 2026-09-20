<?php

declare(strict_types=1);

namespace App\Enums;

use App\Support\Enums\ProvidesSharedEnumMetadata;
use App\Support\Enums\SharedEnum;

/**
 * Post held within the organisation, independent of profession.
 */
enum PositionType: string implements SharedEnum
{
    use ProvidesSharedEnumMetadata;

    case HeadOfDepartment = 'head_of_department';
    case UnitHead = 'unit_head';
    case Consultant = 'consultant';
    case Specialist = 'specialist';
    case Supervisor = 'supervisor';
    case SeniorOfficer = 'senior_officer';
    case Officer = 'officer';
    case Resident = 'resident';
    case Intern = 'intern';


    public function label(): string
    {
        return match ($this) {
            self::HeadOfDepartment => 'Head of Department',
            self::UnitHead => 'Unit Head',
            self::Consultant => 'Consultant',
            self::Specialist => 'Specialist',
            self::Supervisor => 'Supervisor',
            self::SeniorOfficer => 'Senior Officer',
            self::Officer => 'Officer',
            self::Resident => 'Resident',
            self::Intern => 'Intern',
        };
    }
}
