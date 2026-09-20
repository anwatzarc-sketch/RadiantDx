<?php

declare(strict_types=1);

namespace App\Enums;

use App\Support\Enums\ProvidesSharedEnumMetadata;
use App\Support\Enums\SharedEnum;

/**
 * Clinical speciality.
 *
 * Machine values are lower_snake_case to match every other enum in this
 * application; they are stable and must never change once a staff record has
 * stored one. Display text lives in label().
 *
 * sortOrder groups the list clinically rather than alphabetically — medicine,
 * then surgery, then diagnostic and supporting specialities — with gaps so a
 * speciality added later can sit in its group instead of at the end.
 */
enum Speciality: string implements SharedEnum
{
    use ProvidesSharedEnumMetadata;

    // Primary and general
    case GeneralPractice = 'general_practice';
    case FamilyMedicine = 'family_medicine';
    case EmergencyMedicine = 'emergency_medicine';

    // Internal medicine and its major specialities
    case InternalMedicine = 'internal_medicine';
    case Cardiology = 'cardiology';
    case Pulmonology = 'pulmonology';
    case Gastroenterology = 'gastroenterology';
    case Nephrology = 'nephrology';
    case Endocrinology = 'endocrinology';
    case Neurology = 'neurology';
    case Rheumatology = 'rheumatology';
    case InfectiousDiseases = 'infectious_diseases';
    case Oncology = 'oncology';
    case Haematology = 'haematology';
    case Dermatology = 'dermatology';

    // Women's and children's health
    case ObstetricsGynaecology = 'obstetrics_gynaecology';
    case Paediatrics = 'paediatrics';

    // Surgery
    case GeneralSurgery = 'general_surgery';
    case OrthopaedicSurgery = 'orthopaedic_surgery';
    case CardiothoracicSurgery = 'cardiothoracic_surgery';
    case Neurosurgery = 'neurosurgery';
    case Urology = 'urology';
    case Ophthalmology = 'ophthalmology';
    case Otolaryngology = 'otolaryngology';
    case Anaesthesiology = 'anaesthesiology';

    // Diagnostic and laboratory
    case Pathology = 'pathology';
    case LaboratoryMedicine = 'laboratory_medicine';
    case Microbiology = 'microbiology';
    case Radiology = 'radiology';

    // Other
    case Psychiatry = 'psychiatry';
    case PublicHealth = 'public_health';

    public function label(): string
    {
        return match ($this) {
            self::GeneralPractice => 'General Practice',
            self::FamilyMedicine => 'Family Medicine',
            self::EmergencyMedicine => 'Emergency Medicine',
            self::InternalMedicine => 'Internal Medicine',
            self::Cardiology => 'Cardiology',
            self::Pulmonology => 'Pulmonology',
            self::Gastroenterology => 'Gastroenterology',
            self::Nephrology => 'Nephrology',
            self::Endocrinology => 'Endocrinology',
            self::Neurology => 'Neurology',
            self::Rheumatology => 'Rheumatology',
            self::InfectiousDiseases => 'Infectious Diseases',
            self::Oncology => 'Oncology',
            self::Haematology => 'Haematology',
            self::Dermatology => 'Dermatology',
            self::ObstetricsGynaecology => 'Obstetrics & Gynaecology',
            self::Paediatrics => 'Paediatrics',
            self::GeneralSurgery => 'General Surgery',
            self::OrthopaedicSurgery => 'Orthopaedic Surgery',
            self::CardiothoracicSurgery => 'Cardiothoracic Surgery',
            self::Neurosurgery => 'Neurosurgery',
            self::Urology => 'Urology',
            self::Ophthalmology => 'Ophthalmology',
            self::Otolaryngology => 'Otolaryngology (ENT)',
            self::Anaesthesiology => 'Anaesthesiology',
            self::Pathology => 'Pathology',
            self::LaboratoryMedicine => 'Laboratory Medicine',
            self::Microbiology => 'Microbiology',
            self::Radiology => 'Radiology',
            self::Psychiatry => 'Psychiatry',
            self::PublicHealth => 'Public Health',
        };
    }

    /**
     * Lay terms and alternative spellings, so someone searching "heart" or the
     * American spelling of a word still finds the right speciality.
     *
     * @return list<string>
     */
    public function searchKeywords(): array
    {
        return match ($this) {
            self::GeneralPractice => ['gp', 'primary care'],
            self::FamilyMedicine => ['family practice', 'primary care'],
            self::EmergencyMedicine => ['a&e', 'accident', 'casualty', 'er'],
            self::InternalMedicine => ['general medicine', 'physician'],
            self::Cardiology => ['heart', 'cardiac'],
            self::Pulmonology => ['lung', 'respiratory', 'chest', 'pulmonary'],
            self::Gastroenterology => ['stomach', 'bowel', 'liver', 'gi', 'digestive'],
            self::Nephrology => ['kidney', 'renal', 'dialysis'],
            self::Endocrinology => ['hormone', 'diabetes', 'thyroid'],
            self::Neurology => ['brain', 'nerve', 'neurological'],
            self::Rheumatology => ['joint', 'arthritis', 'autoimmune'],
            self::InfectiousDiseases => ['id', 'infection', 'tropical'],
            self::Oncology => ['cancer', 'tumour', 'tumor'],
            self::Haematology => ['blood', 'hematology', 'anaemia'],
            self::Dermatology => ['skin'],
            self::ObstetricsGynaecology => ['obgyn', 'og', 'gynecology', 'maternity', 'womens health'],
            self::Paediatrics => ['pediatrics', 'child', 'children', 'infant'],
            self::GeneralSurgery => ['surgery', 'surgical'],
            self::OrthopaedicSurgery => ['orthopedic', 'bone', 'joint', 'trauma'],
            self::CardiothoracicSurgery => ['heart surgery', 'thoracic', 'cardiac surgery'],
            self::Neurosurgery => ['brain surgery', 'neurological surgery'],
            self::Urology => ['bladder', 'prostate', 'urinary'],
            self::Ophthalmology => ['eye', 'vision', 'optic'],
            self::Otolaryngology => ['ent', 'ear', 'nose', 'throat'],
            self::Anaesthesiology => ['anesthesiology', 'anaesthesia', 'anesthesia'],
            self::Pathology => ['histopathology', 'biopsy', 'morbid anatomy'],
            self::LaboratoryMedicine => ['lab', 'clinical pathology', 'chemical pathology'],
            self::Microbiology => ['bacteriology', 'culture', 'infection'],
            self::Radiology => ['imaging', 'x-ray', 'scan', 'ultrasound'],
            self::Psychiatry => ['mental health', 'psychiatric'],
            self::PublicHealth => ['community health', 'epidemiology'],
        };
    }
}
