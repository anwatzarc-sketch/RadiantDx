<?php

declare(strict_types=1);

namespace App\Services\Laboratory;

use App\Enums\AuditAction;
use App\Enums\Interpretation;
use App\Enums\ParameterDataType;
use App\Enums\RequisitionItemStatus;
use App\Enums\RequisitionStatus;
use App\Enums\ResultStatus;
use App\Enums\ValidationStatus;
use App\Exceptions\WorkflowViolationException;
use App\Models\LaboratoryRequisition;
use App\Models\LaboratoryRequisitionItem;
use App\Models\LaboratoryResult;
use App\Models\LaboratoryResultParameter;
use App\Models\LaboratoryTestParameter;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\AuthenticatedStaffResolver;
use Illuminate\Support\Facades\DB;

/**
 * The result side of the laboratory workflow: opening result records for the
 * requested work, recording values, and the controlled validation and
 * correction of finalised results.
 */
class ResultService
{
    public function __construct(
        private readonly ReferenceNumberGenerator $numbers,
        private readonly InterpretationEvaluator $evaluator,
        private readonly AuditLogger $audit,
        private readonly AuthenticatedStaffResolver $identity,
    ) {}

    /**
     * Opens a result record for every investigation on the requisition that does
     * not have one yet. Safe to call more than once.
     *
     * @return int number of results opened
     */
    public function openResultsFor(LaboratoryRequisition $requisition, User $actor): int
    {
        $opened = 0;

        foreach ($requisition->items()->with('result')->get() as $item) {
            if ($item->result !== null || $item->status === RequisitionItemStatus::Cancelled) {
                continue;
            }

            $this->createFor($item, $actor);
            $opened++;
        }

        return $opened;
    }

    public function createFor(LaboratoryRequisitionItem $item, User $actor): LaboratoryResult
    {
        if ($item->result()->exists()) {
            throw WorkflowViolationException::because(
                "A result already exists for {$item->test_name} on this requisition."
            );
        }

        return DB::transaction(function () use ($item, $actor): LaboratoryResult {
            $test = $item->test()->with('activeParameters.options')->firstOrFail();

            $result = new LaboratoryResult([
                'laboratory_requisition_id' => $item->laboratory_requisition_id,
                'laboratory_requisition_item_id' => $item->getKey(),
                'laboratory_test_id' => $item->laboratory_test_id,
                'test_name' => $item->test_name,
                'test_code' => $item->test_code,
                'specimen_type' => $item->specimen_type,
                'panel_name' => $item->panel_name,
                'status' => ResultStatus::Pending,
                'validation_status' => ValidationStatus::PendingValidation,
            ]);

            $result->result_number = $this->numbers->forResult();
            $result->created_by = $actor->getKey();
            $result->updated_by = $actor->getKey();
            $result->save();

            $this->materialiseParameters($result, $test);

            $this->audit->record(
                AuditAction::ResultCreated,
                $result,
                "Result {$result->result_number} opened for {$result->test_name}.",
                ['requisition' => $item->requisition->requisition_number],
                $actor,
            );

            return $result->refresh();
        });
    }

    /**
     * Records entered values.
     *
     * @param  array<int, array{value: string|null, interpretation: string|null, comment: string|null}>  $values
     *                                                                                                          keyed by result parameter id
     * @param  array{interpretation?: string|null, comments?: string|null}  $attributes
     */
    public function recordValues(
        LaboratoryResult $result,
        array $values,
        array $attributes,
        User $actor,
    ): LaboratoryResult {
        $this->assertEditable($result);

        return DB::transaction(function () use ($result, $values, $attributes, $actor): LaboratoryResult {
            foreach ($result->parameters as $parameter) {
                if (! array_key_exists($parameter->getKey(), $values)) {
                    continue;
                }

                $this->applyValue($parameter, $values[$parameter->getKey()]);
            }

            $result->fill([
                'interpretation' => $attributes['interpretation'] ?? $result->interpretation,
                'comments' => $attributes['comments'] ?? $result->comments,
            ]);

            $result->load('parameters');
            $result->status = $this->deriveStatus($result);
            $result->updated_by = $actor->getKey();

            if ($result->status === ResultStatus::Completed && $result->performed_at === null) {
                $result->performed_by = $actor->getKey();
                $result->recordActor('performed_by', $this->identity->resolve($actor));
                $result->performed_at = now();
            }

            $result->save();

            $this->syncItemStatus($result);

            $this->audit->record(
                AuditAction::ResultUpdated,
                $result,
                "Result {$result->result_number} updated for {$result->test_name}.",
                [
                    'status' => $result->status->value,
                    'entered' => $result->enteredParameterCount(),
                    'total' => $result->parameters->count(),
                ],
                $actor,
            );

            return $result->refresh();
        });
    }

    public function validate(LaboratoryResult $result, User $actor): LaboratoryResult
    {
        if ($result->isValidated()) {
            throw WorkflowViolationException::because('This result has already been validated.');
        }

        if ($result->requisition->isCancelled()) {
            throw WorkflowViolationException::because('The requisition for this result has been cancelled.');
        }

        $result->load('parameters');

        if (! $result->isComplete()) {
            throw WorkflowViolationException::because(
                'Every parameter must have a result before the result can be validated.'
            );
        }

        return DB::transaction(function () use ($result, $actor): LaboratoryResult {
            $result->status = ResultStatus::Completed;
            $result->validation_status = ValidationStatus::Validated;
            $result->validated_by = $actor->getKey();
            $result->validated_at = now();

            /*
             * The signatory line on every future reprint of this report. Frozen
             * deliberately: a result validated today by a cardiologist must
             * still say so if they move to another speciality next year.
             */
            $result->recordActor('validated_by', $this->identity->resolve($actor));
            $result->updated_by = $actor->getKey();
            $result->save();

            $result->requisitionItem()->update(['status' => RequisitionItemStatus::Validated->value]);

            $this->audit->record(
                AuditAction::ResultValidated,
                $result,
                "Result {$result->result_number} validated for {$result->test_name}.",
                ['revision' => $result->revision],
                $actor,
            );

            $this->completeRequisitionIfFinished($result->requisition, $actor);

            return $result->refresh();
        });
    }

    /**
     * Reopens a validated result for correction. This is the only way a
     * finalised result can change, and it always leaves a reason and an audit
     * entry behind.
     */
    public function unvalidate(LaboratoryResult $result, string $reason, User $actor): LaboratoryResult
    {
        if (! $result->isValidated()) {
            throw WorkflowViolationException::because('This result is not validated.');
        }

        if (trim($reason) === '') {
            throw WorkflowViolationException::because('A reason is required to unvalidate a result.');
        }

        return DB::transaction(function () use ($result, $reason, $actor): LaboratoryResult {
            $previousValidator = $result->validated_by;
            $previousValidatedAt = $result->validated_at;

            $result->validation_status = ValidationStatus::PendingValidation;
            $result->validated_by = null;
            // Unvalidating undoes the act of validating, so its snapshot goes
            // with it. This is the only sanctioned way one is removed.
            $result->clearActorSnapshot('validated_by');
            $result->validated_at = null;
            $result->unvalidated_by = $actor->getKey();
            $result->unvalidated_at = now();
            $result->unvalidation_reason = $reason;
            $result->revision = $result->revision + 1;
            $result->updated_by = $actor->getKey();
            $result->save();

            $result->requisitionItem()->update(['status' => RequisitionItemStatus::Resulted->value]);

            $this->audit->record(
                AuditAction::ResultUnvalidated,
                $result,
                "Result {$result->result_number} unvalidated for correction.",
                [
                    'reason' => $reason,
                    'revision' => $result->revision,
                    'previous_validated_by' => $previousValidator,
                    'previous_validated_at' => $previousValidatedAt?->toDateTimeString(),
                ],
                $actor,
            );

            $this->reopenRequisitionIfCompleted($result->requisition, $actor);

            return $result->refresh();
        });
    }

    public function recordPrint(LaboratoryResult $result, User $actor): void
    {
        $result->forceFill([
            'last_printed_at' => now(),
            'print_count' => $result->print_count + 1,
            'printed_by' => $actor->getKey(),
        ]);

        // Only the first print is frozen: it records who issued the report,
        // which is the fact a reprint cannot change.
        if (! $result->hasActorSnapshot('printed_by')) {
            $result->recordActor('printed_by', $this->identity->resolve($actor));
        }

        $result->save();

        $this->audit->record(
            AuditAction::ResultPrinted,
            $result,
            "Laboratory report {$result->result_number} produced.",
            ['revision' => $result->revision],
            $actor,
        );
    }

    public function delete(LaboratoryResult $result, User $actor): void
    {
        if ($result->isValidated()) {
            throw WorkflowViolationException::because('A validated result cannot be deleted.');
        }

        DB::transaction(function () use ($result, $actor): void {
            $number = $result->result_number;

            $result->requisitionItem()->update(['status' => RequisitionItemStatus::Processing->value]);
            $result->parameters()->delete();
            $result->delete();

            $this->audit->record(
                AuditAction::ResultDeleted,
                $result,
                "Result {$number} deleted.",
                [],
                $actor,
            );
        });
    }

    /**
     * Creates one result parameter row per reportable value, copying the
     * catalogue definition so the report stays faithful if the catalogue is
     * later changed.
     */
    private function materialiseParameters(LaboratoryResult $result, \App\Models\LaboratoryTest $test): void
    {
        if (! $test->isParameterised()) {
            $result->parameters()->create([
                'laboratory_test_parameter_id' => null,
                'parameter_name' => $test->name,
                'parameter_code' => $test->code,
                'data_type' => ParameterDataType::Numeric,
                'unit' => $test->unit,
                'reference_range' => $test->referenceSummary() ?: null,
                'reference_low' => $test->reference_low,
                'reference_high' => $test->reference_high,
                'decimal_precision' => $test->decimal_precision,
                'display_order' => 1,
            ]);

            return;
        }

        $order = 0;

        foreach ($test->activeParameters as $parameter) {
            $result->parameters()->create($this->snapshotOf($parameter, ++$order));
        }

        if ($order === 0) {
            throw WorkflowViolationException::because(
                "The test {$test->name} has no active parameters, so no result can be recorded for it."
            );
        }
    }

    /** @return array<string, mixed> */
    private function snapshotOf(LaboratoryTestParameter $parameter, int $order): array
    {
        return [
            'laboratory_test_parameter_id' => $parameter->getKey(),
            'parameter_name' => $parameter->name,
            'parameter_code' => $parameter->code,
            'data_type' => $parameter->data_type,
            'unit' => $parameter->unit,
            'reference_range' => $parameter->referenceSummary() ?: null,
            'reference_low' => $parameter->reference_low,
            'reference_high' => $parameter->reference_high,
            'critical_low' => $parameter->critical_low,
            'critical_high' => $parameter->critical_high,
            'decimal_precision' => $parameter->decimal_precision,
            'abnormal_when' => $parameter->abnormal_when,
            'display_order' => $order,
        ];
    }

    /**
     * Stores one entered value.
     *
     * The entered value is kept verbatim and is authoritative. The evaluator
     * only produces a suggestion, which is applied to the reported
     * interpretation when the laboratory has not chosen one explicitly.
     *
     * @param  array{value?: string|null, interpretation?: string|null, comment?: string|null}  $input
     */
    private function applyValue(LaboratoryResultParameter $parameter, array $input): void
    {
        $value = $input['value'] ?? null;
        $value = is_string($value) ? trim($value) : $value;
        $value = ($value === '' ? null : $value);

        $parameter->result_value = $value;
        $parameter->result_numeric = $parameter->data_type === ParameterDataType::Numeric
            ? $this->evaluator->toNumeric($value)
            : null;
        $parameter->comment = $input['comment'] ?? null;

        $parameter->setRelation('parameter', $parameter->parameter);
        $suggestion = $this->evaluator->suggest($parameter);
        $parameter->auto_interpretation = $suggestion;

        $chosen = $input['interpretation'] ?? null;

        $parameter->interpretation = match (true) {
            $value === null => null,
            is_string($chosen) && $chosen !== '' => Interpretation::tryFrom($chosen) ?? $suggestion,
            default => $suggestion,
        };

        $parameter->save();
    }

    private function deriveStatus(LaboratoryResult $result): ResultStatus
    {
        $total = $result->parameters->count();
        $entered = $result->enteredParameterCount();

        return match (true) {
            $entered === 0 => ResultStatus::Pending,
            $entered === $total => ResultStatus::Completed,
            default => ResultStatus::InProgress,
        };
    }

    private function syncItemStatus(LaboratoryResult $result): void
    {
        $status = match ($result->status) {
            ResultStatus::Completed => RequisitionItemStatus::Resulted,
            ResultStatus::InProgress => RequisitionItemStatus::Processing,
            ResultStatus::Pending => RequisitionItemStatus::Collected,
        };

        $result->requisitionItem()->update(['status' => $status->value]);
    }

    private function assertEditable(LaboratoryResult $result): void
    {
        if ($result->isValidated()) {
            throw WorkflowViolationException::because(
                'This result is validated. Unvalidate it first if a correction is required.'
            );
        }

        if ($result->requisition->isCancelled()) {
            throw WorkflowViolationException::because('The requisition for this result has been cancelled.');
        }
    }

    /** The requisition closes itself once every investigation has been validated. */
    private function completeRequisitionIfFinished(LaboratoryRequisition $requisition, User $actor): void
    {
        if ($requisition->status === RequisitionStatus::Completed) {
            return;
        }

        $outstanding = $requisition->items()
            ->whereNotIn('status', [
                RequisitionItemStatus::Validated->value,
                RequisitionItemStatus::Cancelled->value,
            ])
            ->exists();

        if ($outstanding) {
            return;
        }

        $requisition->forceFill([
            'status' => RequisitionStatus::Completed->value,
            'completed_at' => now(),
            'updated_by' => $actor->getKey(),
        ])->save();

        $this->audit->record(
            AuditAction::RequisitionCompleted,
            $requisition,
            "Requisition {$requisition->requisition_number} completed: every result is validated.",
            [],
            $actor,
        );
    }

    /** A correction on a completed requisition puts it back into processing. */
    private function reopenRequisitionIfCompleted(LaboratoryRequisition $requisition, User $actor): void
    {
        if ($requisition->status !== RequisitionStatus::Completed) {
            return;
        }

        $requisition->forceFill([
            'status' => RequisitionStatus::Processing->value,
            'completed_at' => null,
            'updated_by' => $actor->getKey(),
        ])->save();

        $this->audit->record(
            AuditAction::RequisitionProcessing,
            $requisition,
            "Requisition {$requisition->requisition_number} reopened for correction.",
            [],
            $actor,
        );
    }
}
