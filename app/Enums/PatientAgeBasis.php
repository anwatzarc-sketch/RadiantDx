<?php

declare(strict_types=1);

namespace App\Enums;

/** Where the patient age used to select a reference range came from. */
enum PatientAgeBasis: string
{
    case DateOfBirth = 'dob';
    case AgeYears = 'age_years';
    case Unknown = 'unknown';
}
