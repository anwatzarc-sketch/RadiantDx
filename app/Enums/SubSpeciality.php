<?php

declare(strict_types=1);

namespace App\Enums;

use App\Support\Enums\ProvidesSharedEnumMetadata;
use App\Support\Enums\SharedEnum;

/**
 * Sub-speciality, always owned by exactly one parent {@see Speciality}.
 *
 * parent() is what makes the pairing enforceable: a sub-speciality submitted
 * against the wrong speciality is rejected server-side by
 * {@see \App\Rules\ValidSubSpeciality}, not merely hidden in the UI.
 */
enum SubSpeciality: string implements SharedEnum
{
    use ProvidesSharedEnumMetadata;

    // Cardiology
    case InterventionalCardiology = 'interventional_cardiology';
    case Electrophysiology = 'electrophysiology';
    case Echocardiography = 'echocardiography';
    case HeartFailure = 'heart_failure';

    // Internal medicine
    case CriticalCare = 'critical_care';
    case GeriatricMedicine = 'geriatric_medicine';

    // Gastroenterology
    case Hepatology = 'hepatology';
    case TherapeuticEndoscopy = 'therapeutic_endoscopy';

    // Neurology
    case StrokeMedicine = 'stroke_medicine';
    case Epileptology = 'epileptology';

    // Oncology
    case MedicalOncology = 'medical_oncology';
    case RadiationOncology = 'radiation_oncology';

    // Haematology
    case HaematologicalOncology = 'haematological_oncology';
    case TransfusionMedicine = 'transfusion_medicine';
    case Coagulation = 'coagulation';

    // Paediatrics
    case Neonatology = 'neonatology';
    case PaediatricCardiology = 'paediatric_cardiology';
    case PaediatricOncology = 'paediatric_oncology';

    // Obstetrics & gynaecology
    case MaternalFetalMedicine = 'maternal_fetal_medicine';
    case ReproductiveMedicine = 'reproductive_medicine';

    // General surgery
    case ColorectalSurgery = 'colorectal_surgery';
    case HepatobiliarySurgery = 'hepatobiliary_surgery';
    case TraumaSurgery = 'trauma_surgery';

    // Pathology
    case Histopathology = 'histopathology';
    case Cytopathology = 'cytopathology';
    case ForensicPathology = 'forensic_pathology';

    // Laboratory medicine
    case ClinicalChemistry = 'clinical_chemistry';
    case ClinicalImmunology = 'clinical_immunology';
    case MolecularDiagnostics = 'molecular_diagnostics';
    case Parasitology = 'parasitology';

    // Microbiology
    case Bacteriology = 'bacteriology';
    case Virology = 'virology';
    case Mycology = 'mycology';

    // Radiology
    case InterventionalRadiology = 'interventional_radiology';
    case CrossSectionalImaging = 'cross_sectional_imaging';

    public function label(): string
    {
        return match ($this) {
            self::InterventionalCardiology => 'Interventional Cardiology',
            self::Electrophysiology => 'Electrophysiology',
            self::Echocardiography => 'Echocardiography',
            self::HeartFailure => 'Heart Failure',
            self::CriticalCare => 'Critical Care',
            self::GeriatricMedicine => 'Geriatric Medicine',
            self::Hepatology => 'Hepatology',
            self::TherapeuticEndoscopy => 'Therapeutic Endoscopy',
            self::StrokeMedicine => 'Stroke Medicine',
            self::Epileptology => 'Epileptology',
            self::MedicalOncology => 'Medical Oncology',
            self::RadiationOncology => 'Radiation Oncology',
            self::HaematologicalOncology => 'Haematological Oncology',
            self::TransfusionMedicine => 'Transfusion Medicine',
            self::Coagulation => 'Coagulation',
            self::Neonatology => 'Neonatology',
            self::PaediatricCardiology => 'Paediatric Cardiology',
            self::PaediatricOncology => 'Paediatric Oncology',
            self::MaternalFetalMedicine => 'Maternal-Fetal Medicine',
            self::ReproductiveMedicine => 'Reproductive Medicine',
            self::ColorectalSurgery => 'Colorectal Surgery',
            self::HepatobiliarySurgery => 'Hepatobiliary Surgery',
            self::TraumaSurgery => 'Trauma Surgery',
            self::Histopathology => 'Histopathology',
            self::Cytopathology => 'Cytopathology',
            self::ForensicPathology => 'Forensic Pathology',
            self::ClinicalChemistry => 'Clinical Chemistry',
            self::ClinicalImmunology => 'Clinical Immunology',
            self::MolecularDiagnostics => 'Molecular Diagnostics',
            self::Parasitology => 'Parasitology',
            self::Bacteriology => 'Bacteriology',
            self::Virology => 'Virology',
            self::Mycology => 'Mycology',
            self::InterventionalRadiology => 'Interventional Radiology',
            self::CrossSectionalImaging => 'Cross-Sectional Imaging',
        };
    }

    /** The owning speciality's machine value. */
    public function parent(): ?string
    {
        return match ($this) {
            self::InterventionalCardiology,
            self::Electrophysiology,
            self::Echocardiography,
            self::HeartFailure => Speciality::Cardiology->value,

            self::CriticalCare,
            self::GeriatricMedicine => Speciality::InternalMedicine->value,

            self::Hepatology,
            self::TherapeuticEndoscopy => Speciality::Gastroenterology->value,

            self::StrokeMedicine,
            self::Epileptology => Speciality::Neurology->value,

            self::MedicalOncology,
            self::RadiationOncology => Speciality::Oncology->value,

            self::HaematologicalOncology,
            self::TransfusionMedicine,
            self::Coagulation => Speciality::Haematology->value,

            self::Neonatology,
            self::PaediatricCardiology,
            self::PaediatricOncology => Speciality::Paediatrics->value,

            self::MaternalFetalMedicine,
            self::ReproductiveMedicine => Speciality::ObstetricsGynaecology->value,

            self::ColorectalSurgery,
            self::HepatobiliarySurgery,
            self::TraumaSurgery => Speciality::GeneralSurgery->value,

            self::Histopathology,
            self::Cytopathology,
            self::ForensicPathology => Speciality::Pathology->value,

            self::ClinicalChemistry,
            self::ClinicalImmunology,
            self::MolecularDiagnostics,
            self::Parasitology => Speciality::LaboratoryMedicine->value,

            self::Bacteriology,
            self::Virology,
            self::Mycology => Speciality::Microbiology->value,

            self::InterventionalRadiology,
            self::CrossSectionalImaging => Speciality::Radiology->value,
        };
    }

    /** @return list<string> */
    public function searchKeywords(): array
    {
        return match ($this) {
            self::InterventionalCardiology => ['angioplasty', 'stent', 'cath lab'],
            self::Electrophysiology => ['arrhythmia', 'pacemaker', 'ep'],
            self::Echocardiography => ['echo', 'ultrasound heart'],
            self::Hepatology => ['liver'],
            self::StrokeMedicine => ['cva', 'stroke'],
            self::TransfusionMedicine => ['blood bank', 'crossmatch'],
            self::Coagulation => ['clotting', 'inr', 'haemostasis'],
            self::Neonatology => ['newborn', 'nicu'],
            self::Histopathology => ['biopsy', 'tissue'],
            self::Cytopathology => ['cytology', 'smear', 'fna'],
            self::ClinicalChemistry => ['biochemistry', 'chemical pathology'],
            self::MolecularDiagnostics => ['pcr', 'genetics', 'molecular'],
            self::Bacteriology => ['culture', 'sensitivity', 'bacteria'],
            self::Virology => ['viral', 'serology'],
            self::Mycology => ['fungal', 'fungus'],
            default => [],
        };
    }
}
