<?php

declare(strict_types=1);

namespace App\Services\Laboratory;

use App\Enums\AuditAction;
use App\Enums\RequisitionItemStatus;
use App\Enums\RequisitionStatus;
use App\Exceptions\WorkflowViolationException;
use App\Models\LaboratoryPanel;
use App\Models\LaboratoryRequisition;
use App\Models\LaboratoryRequisitionItem;
use App\Models\LaboratoryTest;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The requisition side of the laboratory workflow: creating a request,
 * expanding what was selected into work items, and moving the request through
 * its controlled states.
 *
 * Every operation that touches more than one table runs in a transaction, and
 * each one leaves an audit event behind.
 */
class RequisitionService
{
    public function __construct(
        private readonly ReferenceNumberGenerator $numbers,
        private readonly AuditLogger $audit,
        private readonly ResultService $results,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array{type: string, id: int}>  $selections
     */
    public function create(array $attributes, array $selections, User $actor): LaboratoryRequisition
    {
        return DB::transaction(function () use ($attributes, $selections, $actor): LaboratoryRequisition {
            $requisition = new LaboratoryRequisition($attributes);
            $requisition->requisition_number = $this->numbers->forRequisition();
            $requisition->status = RequisitionStatus::Draft;
            $requisition->created_by = $actor->getKey();
            $requisition->updated_by = $actor->getKey();
            $requisition->save();

            $this->syncItems($requisition, $selections);

            $this->audit->record(
                AuditAction::RequisitionCreated,
                $requisition,
                "Requisition {$requisition->requisition_number} created for {$requisition->patient_name}.",
                ['investigations' => $requisition->items()->count()],
                $actor,
            );

            return $requisition->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array{type: string, id: int}>  $selections
     */
    public function update(
        LaboratoryRequisition $requisition,
        array $attributes,
        array $selections,
        User $actor,
    ): LaboratoryRequisition {
        if (! $requisition->isEditable()) {
            throw WorkflowViolationException::because(
                'Only a draft requisition can be edited. This requisition is '.$requisition->status->label().'.'
            );
        }

        return DB::transaction(function () use ($requisition, $attributes, $selections, $actor): LaboratoryRequisition {
            $requisition->fill($attributes);
            $requisition->updated_by = $actor->getKey();
            $requisition->save();

            $this->syncItems($requisition, $selections);

            $this->audit->record(
                AuditAction::RequisitionUpdated,
                $requisition,
                "Requisition {$requisition->requisition_number} updated.",
                ['investigations' => $requisition->items()->count()],
                $actor,
            );

            return $requisition->refresh();
        });
    }

    public function submit(LaboratoryRequisition $requisition, User $actor): LaboratoryRequisition
    {
        if ($requisition->status !== RequisitionStatus::Draft) {
            throw WorkflowViolationException::because(
                'Only a draft requisition can be submitted. This requisition is '.$requisition->status->label().'.'
            );
        }

        if ($requisition->items()->doesntExist()) {
            throw WorkflowViolationException::because(
                'Add at least one investigation before submitting the requisition.'
            );
        }

        return DB::transaction(function () use ($requisition, $actor): LaboratoryRequisition {
            $requisition->status = RequisitionStatus::Submitted;
            $requisition->submitted_at = now();
            $requisition->updated_by = $actor->getKey();
            $requisition->save();

            $this->audit->record(
                AuditAction::RequisitionSubmitted,
                $requisition,
                "Requisition {$requisition->requisition_number} submitted to the laboratory.",
                ['investigations' => $requisition->items()->count()],
                $actor,
            );

            return $requisition;
        });
    }

    /**
     * Moves the requisition one step along the laboratory workflow. Arbitrary
     * jumps are refused: only the transitions declared by the status enum are
     * accepted, and cancellation has its own entry point.
     */
    public function transitionTo(
        LaboratoryRequisition $requisition,
        RequisitionStatus $target,
        User $actor,
    ): LaboratoryRequisition {
        if ($target === RequisitionStatus::Cancelled) {
            throw WorkflowViolationException::because('Use the cancel action to cancel a requisition.');
        }

        if (! $requisition->status->canTransitionTo($target)) {
            throw WorkflowViolationException::because(sprintf(
                'A requisition cannot move from %s to %s.',
                $requisition->status->label(),
                $target->label(),
            ));
        }

        if ($target === RequisitionStatus::Completed && ! $this->isReadyForCompletion($requisition)) {
            throw WorkflowViolationException::because(
                'Every investigation must have a validated result before the requisition can be completed.'
            );
        }

        return DB::transaction(function () use ($requisition, $target, $actor): LaboratoryRequisition {
            $requisition->status = $target;
            $requisition->updated_by = $actor->getKey();

            match ($target) {
                RequisitionStatus::Collected => $requisition->collected_at = now(),
                RequisitionStatus::Processing => $requisition->processing_at = now(),
                RequisitionStatus::Completed => $requisition->completed_at = now(),
                default => null,
            };

            $requisition->save();

            if ($target === RequisitionStatus::Collected) {
                $this->markItems($requisition, RequisitionItemStatus::Collected);
                $this->results->openResultsFor($requisition, $actor);
            }

            if ($target === RequisitionStatus::Processing) {
                $this->markItems($requisition, RequisitionItemStatus::Processing);
            }

            $this->audit->record(
                match ($target) {
                    RequisitionStatus::Collected => AuditAction::RequisitionCollected,
                    RequisitionStatus::Processing => AuditAction::RequisitionProcessing,
                    RequisitionStatus::Completed => AuditAction::RequisitionCompleted,
                    default => AuditAction::RequisitionUpdated,
                },
                $requisition,
                "Requisition {$requisition->requisition_number} moved to {$target->label()}.",
                ['status' => $target->value],
                $actor,
            );

            return $requisition->refresh();
        });
    }

    public function cancel(LaboratoryRequisition $requisition, string $reason, User $actor): LaboratoryRequisition
    {
        if (! $requisition->canBeCancelled()) {
            throw WorkflowViolationException::because(
                'A '.mb_strtolower($requisition->status->label()).' requisition cannot be cancelled.'
            );
        }

        if ($requisition->results()->where('validation_status', 'validated')->exists()) {
            throw WorkflowViolationException::because(
                'This requisition already carries a validated result and can no longer be cancelled.'
            );
        }

        return DB::transaction(function () use ($requisition, $reason, $actor): LaboratoryRequisition {
            $requisition->status = RequisitionStatus::Cancelled;
            $requisition->cancelled_at = now();
            $requisition->cancelled_by = $actor->getKey();
            $requisition->cancellation_reason = $reason;
            $requisition->updated_by = $actor->getKey();
            $requisition->save();

            $this->markItems($requisition, RequisitionItemStatus::Cancelled);

            $this->audit->record(
                AuditAction::RequisitionCancelled,
                $requisition,
                "Requisition {$requisition->requisition_number} cancelled.",
                ['reason' => $reason],
                $actor,
            );

            return $requisition->refresh();
        });
    }

    public function delete(LaboratoryRequisition $requisition, User $actor): void
    {
        if ($requisition->status !== RequisitionStatus::Draft) {
            throw WorkflowViolationException::because('Only a draft requisition can be deleted.');
        }

        if ($requisition->results()->exists()) {
            throw WorkflowViolationException::because(
                'This requisition carries laboratory results and cannot be deleted.'
            );
        }

        DB::transaction(function () use ($requisition, $actor): void {
            $number = $requisition->requisition_number;
            $requisition->items()->delete();
            $requisition->delete();

            $this->audit->record(
                AuditAction::RequisitionDeleted,
                $requisition,
                "Draft requisition {$number} deleted.",
                [],
                $actor,
            );
        });
    }

    /**
     * Replaces the work items with the current selection.
     *
     * A selected panel is expanded into one item per active member test, which
     * is what the laboratory actually performs, while the panel reference and
     * its name are snapshotted so the original request stays visible.
     *
     * @param  array<int, array{type: string, id: int}>  $selections
     */
    private function syncItems(LaboratoryRequisition $requisition, array $selections): void
    {
        $testIds = $this->idsOfType($selections, 'test');
        $panelIds = $this->idsOfType($selections, 'panel');

        $tests = LaboratoryTest::query()->whereKey($testIds)->get()->keyBy('id');
        $panels = LaboratoryPanel::query()->with('activeTests')->whereKey($panelIds)->get()->keyBy('id');

        $this->assertSelectionIsUsable($testIds, $tests, $panelIds, $panels);

        $requisition->items()->delete();

        $order = 0;
        $rows = [];

        foreach ($selections as $selection) {
            if ($selection['type'] === 'test') {
                /** @var LaboratoryTest $test */
                $test = $tests[$selection['id']];
                $rows[] = $this->itemRow($test, null, ++$order);

                continue;
            }

            /** @var LaboratoryPanel $panel */
            $panel = $panels[$selection['id']];

            foreach ($panel->activeTests as $test) {
                $rows[] = $this->itemRow($test, $panel, ++$order);
            }
        }

        foreach ($rows as $row) {
            $requisition->items()->create($row);
        }

        $requisition->unsetRelation('items');
    }

    /** @return array<string, mixed> */
    private function itemRow(LaboratoryTest $test, ?LaboratoryPanel $panel, int $order): array
    {
        return [
            'source_type' => $panel === null
                ? LaboratoryRequisitionItem::SOURCE_TEST
                : LaboratoryRequisitionItem::SOURCE_PANEL,
            'laboratory_test_id' => $test->id,
            'laboratory_panel_id' => $panel?->id,
            'test_name' => $test->name,
            'test_code' => $test->code,
            'specimen_type' => $test->specimen_type,
            'panel_name' => $panel?->name,
            'panel_code' => $panel?->code,
            'status' => RequisitionItemStatus::Pending,
            'display_order' => $order,
        ];
    }

    /**
     * @param  array<int, int>  $testIds
     * @param  Collection<int, LaboratoryTest>  $tests
     * @param  array<int, int>  $panelIds
     * @param  Collection<int, LaboratoryPanel>  $panels
     */
    private function assertSelectionIsUsable(
        array $testIds,
        Collection $tests,
        array $panelIds,
        Collection $panels,
    ): void {
        foreach ($testIds as $id) {
            $test = $tests->get($id);

            if ($test === null || ! $test->isReadyForRequisition()) {
                throw WorkflowViolationException::because(
                    'One of the selected tests is no longer available for requisition.'
                );
            }
        }

        foreach ($panelIds as $id) {
            $panel = $panels->get($id);

            if ($panel === null || $panel->activeTests->isEmpty() || ! $panel->is_active) {
                throw WorkflowViolationException::because(
                    'One of the selected panels is no longer available for requisition.'
                );
            }
        }
    }

    /**
     * @param  array<int, array{type: string, id: int}>  $selections
     * @return array<int, int>
     */
    private function idsOfType(array $selections, string $type): array
    {
        return array_values(array_unique(array_map(
            static fn (array $selection): int => (int) $selection['id'],
            array_filter($selections, static fn (array $selection): bool => $selection['type'] === $type),
        )));
    }

    private function markItems(LaboratoryRequisition $requisition, RequisitionItemStatus $status): void
    {
        $requisition->items()
            ->whereNotIn('status', [RequisitionItemStatus::Validated->value, RequisitionItemStatus::Cancelled->value])
            ->update(['status' => $status->value]);
    }

    private function isReadyForCompletion(LaboratoryRequisition $requisition): bool
    {
        $outstanding = $requisition->items()
            ->where('status', '!=', RequisitionItemStatus::Cancelled->value)
            ->where('status', '!=', RequisitionItemStatus::Validated->value)
            ->exists();

        return ! $outstanding && $requisition->items()->exists();
    }
}
