<?php

declare(strict_types=1);

namespace App\Services\Laboratory;

use App\Enums\AuditAction;
use App\Exceptions\WorkflowViolationException;
use App\Models\LaboratoryPanel;
use App\Models\LaboratoryTest;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class LaboratoryPanelService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, int>  $testIds  in the order they should appear
     */
    public function create(array $attributes, array $testIds, User $actor): LaboratoryPanel
    {
        return DB::transaction(function () use ($attributes, $testIds, $actor): LaboratoryPanel {
            $panel = LaboratoryPanel::query()->create($attributes);

            $this->syncTests($panel, $testIds);

            $this->audit->record(
                AuditAction::PanelCreated,
                $panel,
                "Panel {$panel->name} ({$panel->code}) created.",
                ['tests' => count($testIds)],
                $actor,
            );

            return $panel;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, int>  $testIds
     */
    public function update(LaboratoryPanel $panel, array $attributes, array $testIds, User $actor): LaboratoryPanel
    {
        return DB::transaction(function () use ($panel, $attributes, $testIds, $actor): LaboratoryPanel {
            $panel->fill($attributes);
            $changed = array_keys($panel->getDirty());
            $panel->save();

            $this->syncTests($panel, $testIds);

            $this->audit->record(
                AuditAction::PanelUpdated,
                $panel,
                "Panel {$panel->name} ({$panel->code}) updated.",
                ['changed' => $changed, 'tests' => count($testIds)],
                $actor,
            );

            return $panel->refresh();
        });
    }

    public function setActive(LaboratoryPanel $panel, bool $active, User $actor): LaboratoryPanel
    {
        if ($active && $panel->activeTests()->doesntExist()) {
            throw WorkflowViolationException::because(
                'A panel needs at least one active test before it can be activated.'
            );
        }

        $panel->is_active = $active;
        $panel->save();

        $this->audit->record(
            $active ? AuditAction::PanelActivated : AuditAction::PanelDeactivated,
            $panel,
            "Panel {$panel->name} ".($active ? 'activated.' : 'deactivated.'),
            [],
            $actor,
        );

        return $panel;
    }

    public function delete(LaboratoryPanel $panel, User $actor): void
    {
        if ($panel->isReferencedByLaboratoryRecords()) {
            throw WorkflowViolationException::because(
                'This panel appears on existing requisitions. Deactivate it instead so historical records stay intact.'
            );
        }

        DB::transaction(function () use ($panel, $actor): void {
            $label = "{$panel->name} ({$panel->code})";
            $panel->tests()->detach();
            $panel->delete();

            $this->audit->record(
                AuditAction::PanelDeleted,
                $panel,
                "Panel {$label} deleted.",
                [],
                $actor,
            );
        });
    }

    /**
     * Membership is relational and ordered: the pivot carries the position each
     * test occupies on the panel.
     *
     * @param  array<int, int>  $testIds
     */
    private function syncTests(LaboratoryPanel $panel, array $testIds): void
    {
        $testIds = array_values(array_unique(array_map('intval', $testIds)));

        if ($testIds !== [] && LaboratoryTest::query()->whereKey($testIds)->count() !== count($testIds)) {
            throw WorkflowViolationException::because('One of the selected tests no longer exists.');
        }

        $payload = [];
        $order = 0;

        foreach ($testIds as $testId) {
            $payload[$testId] = ['display_order' => ++$order];
        }

        $panel->tests()->sync($payload);
        $panel->unsetRelation('tests');
    }
}
