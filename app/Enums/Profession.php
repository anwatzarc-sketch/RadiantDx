<?php

declare(strict_types=1);

namespace App\Enums;

use App\Support\Enums\ProvidesSharedEnumMetadata;
use App\Support\Enums\SharedEnum;

/**
 * Professional discipline.
 *
 * Distinct from Speciality: a Profession is what someone is qualified as, a
 * Speciality is the clinical field they practise in. Only some professions
 * carry a speciality at all.
 */
enum Profession: string implements SharedEnum
{
    use ProvidesSharedEnumMetadata;

    case Physician = 'physician';
    case Nurse = 'nurse';
    case LaboratoryScientist = 'laboratory_scientist';
    case LaboratoryTechnician = 'laboratory_technician';
    case Phlebotomist = 'phlebotomist';
    case Pharmacist = 'pharmacist';
    case Radiographer = 'radiographer';
    case Administrator = 'administrator';
    case SupportStaff = 'support_staff';


    public function label(): string
    {
        return match ($this) {
            self::Physician => 'Physician',
            self::Nurse => 'Nurse',
            self::LaboratoryScientist => 'Laboratory Scientist',
            self::LaboratoryTechnician => 'Laboratory Technician',
            self::Phlebotomist => 'Phlebotomist',
            self::Pharmacist => 'Pharmacist',
            self::Radiographer => 'Radiographer',
            self::Administrator => 'Administrator',
            self::SupportStaff => 'Support Staff',
        };
    }

    /**
     * Whether a clinical speciality is meaningful for this profession.
     *
     * Used to decide whether the speciality fields are offered at all, rather
     * than asking a phlebotomist to choose a clinical speciality.
     */
    public function carriesSpeciality(): bool
    {
        return match ($this) {
            self::Physician, self::LaboratoryScientist, self::Pharmacist => true,
            default => false,
        };
    }

    /** Whether a professional licence is expected for this profession. */
    public function requiresLicence(): bool
    {
        return match ($this) {
            self::Physician, self::Nurse, self::LaboratoryScientist,
            self::Pharmacist, self::Radiographer => true,
            default => false,
        };
    }
}
