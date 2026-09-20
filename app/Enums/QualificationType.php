<?php

declare(strict_types=1);

namespace App\Enums;

use App\Support\Enums\ProvidesSharedEnumMetadata;
use App\Support\Enums\SharedEnum;

/**
 * Kind of academic or professional qualification held.
 */
enum QualificationType: string implements SharedEnum
{
    use ProvidesSharedEnumMetadata;

    case MedicalDegree = 'medical_degree';
    case Doctorate = 'doctorate';
    case Master = 'master';
    case Bachelor = 'bachelor';
    case Diploma = 'diploma';
    case Certificate = 'certificate';
    case SpecialityCertification = 'speciality_certification';
    case Fellowship = 'fellowship';
    case Other = 'other';


    public function label(): string
    {
        return match ($this) {
            self::MedicalDegree => 'Medical Degree',
            self::Doctorate => 'Doctorate',
            self::Master => 'Master\'s Degree',
            self::Bachelor => 'Bachelor\'s Degree',
            self::Diploma => 'Diploma',
            self::Certificate => 'Certificate',
            self::SpecialityCertification => 'Speciality Certification',
            self::Fellowship => 'Fellowship',
            self::Other => 'Other',
        };
    }
}
