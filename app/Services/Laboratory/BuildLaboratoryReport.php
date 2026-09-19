<?php

declare(strict_types=1);

namespace App\Services\Laboratory;

use App\Enums\ValidationStatus;
use App\Models\LaboratoryRequisition;
use App\Models\LaboratoryResult;
use Illuminate\Support\Collection;

/**
 * Assembles everything the printed laboratory report needs.
 *
 * The report is built from the snapshots stored on the result, not from the
 * live catalogue, so a report reprinted years later reproduces exactly what was
 * issued at the time.
 */
class BuildLaboratoryReport
{
    /**
     * A report covering one investigation.
     *
     * @return array<string, mixed>
     */
    public function forResult(LaboratoryResult $result): array
    {
        $result->loadMissing(['requisition', 'parameters', 'validatedBy', 'performedBy']);

        return $this->assemble($result->requisition, collect([$result]));
    }

    /**
     * A combined report covering every validated investigation on a requisition.
     * Investigations still awaiting validation are deliberately left out.
     *
     * @return array<string, mixed>
     */
    public function forRequisition(LaboratoryRequisition $requisition): array
    {
        $results = $requisition->results()
            ->with(['parameters', 'validatedBy', 'performedBy'])
            ->where('validation_status', ValidationStatus::Validated->value)
            ->orderBy('panel_name')
            ->orderBy('test_name')
            ->get();

        return $this->assemble($requisition, $results);
    }

    /**
     * @param  Collection<int, LaboratoryResult>  $results
     * @return array<string, mixed>
     */
    private function assemble(LaboratoryRequisition $requisition, Collection $results): array
    {
        return [
            'organisation' => config('laboratory.organisation'),
            'footer' => config('laboratory.report.footer'),
            'requisition' => $requisition,
            'results' => $results,
            'isProvisional' => $results->contains(
                fn (LaboratoryResult $result): bool => ! $result->isValidated()
            ),
            'hasCriticalValues' => $results->contains(
                fn (LaboratoryResult $result): bool => $result->hasCriticalValues()
            ),
            'generatedAt' => now(),
        ];
    }
}
