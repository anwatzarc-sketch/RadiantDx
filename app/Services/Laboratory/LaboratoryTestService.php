<?php

declare(strict_types=1);

namespace App\Services\Laboratory;

use App\Enums\AuditAction;
use App\Enums\TestResultType;
use App\Exceptions\WorkflowViolationException;
use App\Models\LaboratoryTest;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class LaboratoryTestService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes, User $actor): LaboratoryTest
    {
        return DB::transaction(function () use ($attributes, $actor): LaboratoryTest {
            $test = LaboratoryTest::query()->create($this->normalise($attributes));

            $this->audit->record(
                AuditAction::TestCreated,
                $test,
                "Laboratory test {$test->name} ({$test->code}) created.",
                ['result_type' => $test->result_type->value],
                $actor,
            );

            return $test;
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(LaboratoryTest $test, array $attributes, User $actor): LaboratoryTest
    {
        return DB::transaction(function () use ($test, $attributes, $actor): LaboratoryTest {
            $test->fill($this->normalise($attributes));
            $changed = array_keys($test->getDirty());
            $test->save();

            $this->audit->record(
                AuditAction::TestUpdated,
                $test,
                "Laboratory test {$test->name} ({$test->code}) updated.",
                ['changed' => $changed],
                $actor,
            );

            return $test;
        });
    }

    public function setActive(LaboratoryTest $test, bool $active, User $actor): LaboratoryTest
    {
        if ($active && $test->isParameterised() && $test->activeParameters()->doesntExist()) {
            throw WorkflowViolationException::because(
                'Add at least one active parameter before activating a multi-parameter test.'
            );
        }

        $test->is_active = $active;
        $test->save();

        $this->audit->record(
            $active ? AuditAction::TestActivated : AuditAction::TestDeactivated,
            $test,
            "Laboratory test {$test->name} ".($active ? 'activated.' : 'deactivated.'),
            [],
            $actor,
        );

        return $test;
    }

    /**
     * Catalogue entries that historical laboratory records depend on are never
     * removed; the caller is expected to deactivate them instead.
     */
    public function delete(LaboratoryTest $test, User $actor): void
    {
        if ($test->isReferencedByLaboratoryRecords()) {
            throw WorkflowViolationException::because(
                'This test appears on existing requisitions. Deactivate it instead so historical records stay intact.'
            );
        }

        if ($test->panels()->exists()) {
            throw WorkflowViolationException::because(
                'Remove this test from its panels before deleting it.'
            );
        }

        DB::transaction(function () use ($test, $actor): void {
            $label = "{$test->name} ({$test->code})";
            $test->parameters()->delete();
            $test->delete();

            $this->audit->record(
                AuditAction::TestDeleted,
                $test,
                "Laboratory test {$label} deleted.",
                [],
                $actor,
            );
        });
    }

    /**
     * A parameterised test reports through its parameters, so the single value
     * columns are cleared to keep the catalogue unambiguous.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function normalise(array $attributes): array
    {
        $resultType = $attributes['result_type'] ?? null;

        if ($resultType === TestResultType::Parameterised->value) {
            $attributes['unit'] = null;
            $attributes['reference_range'] = null;
            $attributes['reference_low'] = null;
            $attributes['reference_high'] = null;
        }

        return $attributes;
    }
}
