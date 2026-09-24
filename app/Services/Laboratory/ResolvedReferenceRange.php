<?php

declare(strict_types=1);

namespace App\Services\Laboratory;

use App\Enums\AbnormalWhen;
use App\Enums\PatientAgeBasis;
use App\Enums\ReferenceRangeBasis;
use App\Models\LaboratoryReferenceRange;

/**
 * The outcome of choosing a reference range for one parameter and patient:
 * the values to judge against, and an honest account of where they came from.
 */
final class ResolvedReferenceRange
{
    public function __construct(
        public readonly ReferenceRangeBasis $basis,
        public readonly PatientAgeBasis $ageBasis,
        public readonly ?LaboratoryReferenceRange $range,
        public readonly ?string $referenceLow,
        public readonly ?string $referenceHigh,
        public readonly ?string $criticalLow,
        public readonly ?string $criticalHigh,
        public readonly ?AbnormalWhen $rule,
        public readonly ?string $label,
    ) {}

    /**
     * The columns this writes onto a laboratory_result_parameters row.
     *
     * @return array<string, mixed>
     */
    public function snapshotColumns(): array
    {
        return [
            'laboratory_reference_range_id' => $this->range?->getKey(),
            'reference_range_basis' => $this->basis,
            'patient_age_basis' => $this->ageBasis,
            'reference_range' => $this->label,
            'reference_low' => $this->referenceLow,
            'reference_high' => $this->referenceHigh,
            'critical_low' => $this->criticalLow,
            'critical_high' => $this->criticalHigh,
            'abnormal_when' => $this->rule?->value,
        ];
    }
}
