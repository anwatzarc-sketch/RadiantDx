<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\AuditAction;
use App\Enums\ValidationStatus;
use App\Models\LaboratoryRequisition;
use App\Services\AuditLogger;
use App\Services\Laboratory\ResultService;
use Illuminate\Support\Facades\Auth;

/**
 * Keeps unvalidated results judged against the right reference range when
 * the patient's details are corrected.
 *
 * A model observer rather than a step in an edit screen, so the rule holds
 * whatever path changes the patient: today requisitions can only be edited as
 * drafts, which have no results, but a later correction screen, an import or
 * a console fix all pass through here.
 *
 * Validated results are never touched. Their ranges are part of what was
 * signed; correcting one means unvalidating it, which increments the
 * revision, and the range is chosen afresh when values are next entered.
 */
class LaboratoryRequisitionObserver
{
    private const PATIENT_FIELDS = ['patient_gender', 'patient_date_of_birth', 'patient_age_years'];

    public function __construct(
        private readonly ResultService $results,
        private readonly AuditLogger $audit,
    ) {}

    public function updated(LaboratoryRequisition $requisition): void
    {
        if (! $requisition->wasChanged(self::PATIENT_FIELDS)) {
            return;
        }

        $pending = $requisition->results()
            ->where('validation_status', '!=', ValidationStatus::Validated->value)
            ->with('parameters')
            ->get();

        foreach ($pending as $result) {
            $result->setRelation('requisition', $requisition);
            $changed = $this->results->refreshReferenceRanges($result);

            if ($changed === 0) {
                continue;
            }

            $this->audit->record(
                AuditAction::ResultUpdated,
                $result,
                "Reference ranges on {$result->result_number} re-selected after the patient's details changed.",
                [
                    'rows_changed' => $changed,
                    'changed_fields' => array_values(array_intersect(self::PATIENT_FIELDS, array_keys($requisition->getChanges()))),
                ],
                Auth::user(),
            );
        }
    }
}
