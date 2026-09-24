<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ParameterDataType;
use App\Enums\TestResultType;
use App\Models\LaboratoryTest;
use App\Models\LaboratoryTestParameter;
use App\Models\LaboratoryTestParameterOption;
use Database\Seeders\Concerns\ReadsHl7MasterData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The starter laboratory catalogue: tests, their parameters and the answer
 * lists of coded parameters.
 *
 * Every test is seeded as parameterised. A single-result test gets one
 * parameter carrying the test's own code, because that is the path that
 * supports critical limits, coded answers and stratified reference ranges.
 * The single-result path of the application is left as it is.
 *
 * Re-running is safe on a live catalogue. A row that does not exist is
 * created in full. A row that does keeps everything the laboratory can edit
 * (names, units, precision, answer labels, adult defaults) and has only its
 * HL7 interface columns refreshed. A soft-deleted row stays deleted.
 *
 * The adult default ranges are placeholders too, so they are written only
 * when config('seeding.placeholder_ranges') is on, and only on creation.
 */
class LaboratoryCatalogSeeder extends Seeder
{
    use ReadsHl7MasterData;

    private const DATA_TYPES = [
        'NM' => ParameterDataType::Numeric,
        'TX' => ParameterDataType::Text,
        'CWE' => ParameterDataType::Dropdown,
    ];

    /**
     * Descriptions the workbook implies but does not state as such. Keyed by
     * parameter code.
     */
    private const DESCRIPTIONS = [
        'LDL' => 'Calculated (Friedewald) – enter manually.',
        'S-OP' => 'Choose the principal finding. Record any additional parasites under Other findings.',
        'S-OTH' => 'Free text: additional parasites, and other findings such as RBC, WBC or yeast.',
    ];

    /** @var array{tests: int, parameters: int, options: int} */
    private array $created = ['tests' => 0, 'parameters' => 0, 'options' => 0];

    public function run(): void
    {
        $catalog = $this->hl7Data('lab_catalog.json')['tests'];
        $options = collect($this->hl7Data('option_sets.json')['rows'])->groupBy('option_set');
        $withDefaults = $this->placeholderRangesEnabled();

        DB::transaction(function () use ($catalog, $options, $withDefaults): void {
            foreach ($catalog as $row) {
                $test = $this->seedTest($row);

                if ($test === null) {
                    continue;
                }

                foreach ($row['parameters'] as $parameterRow) {
                    $parameter = $this->seedParameter($test, $parameterRow, $withDefaults);

                    if ($parameter !== null && $parameterRow['option_set'] !== null) {
                        $this->seedOptions($parameter, $options->get($parameterRow['option_set'], collect())->all());
                    }
                }
            }
        });

        $this->command?->info(sprintf(
            '  Catalogue: %d tests, %d parameters; created %d tests, %d parameters, %d options.',
            count($catalog),
            array_sum(array_map(fn (array $test): int => count($test['parameters']), $catalog)),
            $this->created['tests'],
            $this->created['parameters'],
            $this->created['options'],
        ));

        if (! $withDefaults) {
            $this->command?->warn(
                '  Adult default ranges were not seeded (SEED_PLACEHOLDER_RANGES is off). '
                .'Parameters have no default range until the laboratory enters one.'
            );
        }
    }

    /** @param array<string, mixed> $row */
    private function seedTest(array $row): ?LaboratoryTest
    {
        $interface = [
            'loinc_code' => $row['loinc_code'],
            'hl7_section_code' => $row['hl7_section_code'],
            'hl7_nature_code' => $row['hl7_nature_code'],
        ];

        $test = LaboratoryTest::withTrashed()->where('code', $row['code'])->first();

        if ($test?->trashed()) {
            $this->command?->warn("  Test {$row['code']} was deleted here; left deleted, with its parameters.");

            return null;
        }

        if ($test !== null) {
            $test->update($interface);

            return $test;
        }

        $this->created['tests']++;

        return LaboratoryTest::query()->create([
            ...$interface,
            'code' => $row['code'],
            'name' => $row['name'],
            'category' => $this->displayForHl7Code('test_category_section', $row['hl7_section_code']),
            'specimen_type' => $this->displayForHl7Code('specimen_type', $row['hl7_specimen_code']),
            'result_type' => TestResultType::Parameterised,
            'turnaround_time_hours' => $row['turnaround_time_hours'],
            'is_active' => true,
            'display_order' => $row['display_order'],
        ]);
    }

    /** @param array<string, mixed> $row */
    private function seedParameter(LaboratoryTest $test, array $row, bool $withDefaults): ?LaboratoryTestParameter
    {
        $interface = [
            'loinc_code' => $row['loinc_code'],
            'hl7_value_type' => $row['hl7_value_type'],
        ];

        $parameter = LaboratoryTestParameter::withTrashed()
            ->where('laboratory_test_id', $test->getKey())
            ->where('code', $row['code'])
            ->first();

        if ($parameter?->trashed()) {
            $this->command?->warn("  Parameter {$test->code}/{$row['code']} was deleted here; left deleted.");

            return null;
        }

        if ($parameter !== null) {
            $parameter->update($interface);

            return $parameter;
        }

        $dataType = self::DATA_TYPES[$row['hl7_value_type']]
            ?? throw new RuntimeException("{$test->code}/{$row['code']}: no data type for HL7 value type '{$row['hl7_value_type']}'.");

        $defaults = $withDefaults && $dataType === ParameterDataType::Numeric
            ? [
                'reference_low' => $row['reference_low'],
                'reference_high' => $row['reference_high'],
                'critical_low' => $row['critical_low'],
                'critical_high' => $row['critical_high'],
            ]
            : [];

        $this->created['parameters']++;

        return LaboratoryTestParameter::query()->create([
            ...$interface,
            ...$defaults,
            'laboratory_test_id' => $test->getKey(),
            'code' => $row['code'],
            'name' => $row['name'],
            'description' => self::DESCRIPTIONS[$row['code']] ?? null,
            'data_type' => $dataType,
            'unit' => $row['unit_ucum'] === null ? null : $this->displayForHl7Code('units_of_measure_ucum', $row['unit_ucum']),
            'decimal_precision' => $row['decimal_precision'],
            'is_active' => true,
            'display_order' => $row['display_order'],
        ]);
    }

    /** @param list<array<string, mixed>> $rows */
    private function seedOptions(LaboratoryTestParameter $parameter, array $rows): void
    {
        if ($rows === []) {
            throw new RuntimeException("{$parameter->code}: its option set has no values.");
        }

        foreach ($rows as $row) {
            $option = LaboratoryTestParameterOption::query()
                ->where('laboratory_test_parameter_id', $parameter->getKey())
                ->where('value', $row['value'])
                ->first();

            $interface = ['snomed_code' => $row['snomed_code'], 'hl7_flag' => $row['hl7_flag']];

            if ($option !== null) {
                $option->update($interface);

                continue;
            }

            LaboratoryTestParameterOption::query()->create([
                ...$interface,
                'laboratory_test_parameter_id' => $parameter->getKey(),
                'value' => $row['value'],
                'label' => $row['label'],
                'is_abnormal' => $row['is_abnormal'],
                'is_active' => true,
                'display_order' => $row['display_order'],
            ]);
            $this->created['options']++;
        }
    }
}
