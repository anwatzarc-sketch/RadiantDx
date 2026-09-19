<?php

declare(strict_types=1);

namespace App\Services\Laboratory;

use App\Enums\AuditAction;
use App\Enums\ParameterDataType;
use App\Exceptions\WorkflowViolationException;
use App\Models\LaboratoryTestParameter;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class TestParameterService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array{value: string, label: string, is_abnormal?: bool}>  $options
     */
    public function create(array $attributes, array $options, User $actor): LaboratoryTestParameter
    {
        return DB::transaction(function () use ($attributes, $options, $actor): LaboratoryTestParameter {
            $parameter = LaboratoryTestParameter::query()->create($this->normalise($attributes));

            $this->syncOptions($parameter, $options);

            $this->audit->record(
                AuditAction::ParameterCreated,
                $parameter,
                "Parameter {$parameter->name} added to {$parameter->test->name}.",
                ['data_type' => $parameter->data_type->value],
                $actor,
            );

            return $parameter;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array{value: string, label: string, is_abnormal?: bool}>  $options
     */
    public function update(
        LaboratoryTestParameter $parameter,
        array $attributes,
        array $options,
        User $actor,
    ): LaboratoryTestParameter {
        return DB::transaction(function () use ($parameter, $attributes, $options, $actor): LaboratoryTestParameter {
            $parameter->fill($this->normalise($attributes));
            $changed = array_keys($parameter->getDirty());
            $parameter->save();

            $this->syncOptions($parameter, $options);

            $this->audit->record(
                AuditAction::ParameterUpdated,
                $parameter,
                "Parameter {$parameter->name} updated.",
                ['changed' => $changed],
                $actor,
            );

            return $parameter;
        });
    }

    public function setActive(LaboratoryTestParameter $parameter, bool $active, User $actor): LaboratoryTestParameter
    {
        $parameter->is_active = $active;
        $parameter->save();

        $this->audit->record(
            $active ? AuditAction::ParameterActivated : AuditAction::ParameterDeactivated,
            $parameter,
            "Parameter {$parameter->name} ".($active ? 'activated.' : 'deactivated.'),
            [],
            $actor,
        );

        return $parameter;
    }

    public function delete(LaboratoryTestParameter $parameter, User $actor): void
    {
        if ($parameter->isReferencedByLaboratoryRecords()) {
            throw WorkflowViolationException::because(
                'This parameter has already been reported on. Deactivate it instead so historical results stay intact.'
            );
        }

        DB::transaction(function () use ($parameter, $actor): void {
            $label = $parameter->name;
            $parameter->options()->delete();
            $parameter->delete();

            $this->audit->record(
                AuditAction::ParameterDeleted,
                $parameter,
                "Parameter {$label} deleted.",
                [],
                $actor,
            );
        });
    }

    /**
     * Dropdown values are stored relationally. Replacing the set is safe because
     * results keep their own snapshot of the reported value.
     *
     * @param  array<int, array{value: string, label: string, is_abnormal?: bool}>  $options
     */
    private function syncOptions(LaboratoryTestParameter $parameter, array $options): void
    {
        if ($parameter->data_type !== ParameterDataType::Dropdown) {
            $parameter->options()->delete();

            return;
        }

        $keep = [];
        $order = 0;

        foreach ($options as $option) {
            $value = trim((string) ($option['value'] ?? ''));
            $label = trim((string) ($option['label'] ?? '')) ?: $value;

            if ($value === '') {
                continue;
            }

            $record = $parameter->options()->updateOrCreate(
                ['value' => $value],
                [
                    'label' => $label,
                    'is_abnormal' => (bool) ($option['is_abnormal'] ?? false),
                    'is_active' => true,
                    'display_order' => ++$order,
                ],
            );

            $keep[] = $record->getKey();
        }

        if ($keep === []) {
            throw WorkflowViolationException::because('A dropdown parameter needs at least one value.');
        }

        $parameter->options()->whereKeyNot($keep)->delete();
        $parameter->unsetRelation('options');
    }

    /**
     * Reference bounds only mean something for numeric parameters, and the
     * abnormal outcome rule only for the fixed value types.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function normalise(array $attributes): array
    {
        $dataType = ParameterDataType::tryFrom((string) ($attributes['data_type'] ?? ''));

        if ($dataType !== null && ! $dataType->isNumeric()) {
            $attributes['reference_low'] = null;
            $attributes['reference_high'] = null;
            $attributes['critical_low'] = null;
            $attributes['critical_high'] = null;
            $attributes['decimal_precision'] = 0;
        }

        if ($dataType === null || ! in_array($dataType, [ParameterDataType::Boolean, ParameterDataType::PositiveNegative], true)) {
            $attributes['abnormal_when'] = null;
        }

        return $attributes;
    }
}
