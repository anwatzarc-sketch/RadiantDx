<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CodeSetValue;
use Database\Seeders\Concerns\ReadsHl7MasterData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The coded value sets with their HL7 and FHIR mappings.
 *
 * Keyed on (value_set, code). These rows describe standards rather than the
 * laboratory, so re-seeding refreshes them in place.
 */
class Hl7CodeSetSeeder extends Seeder
{
    use ReadsHl7MasterData;

    public function run(): void
    {
        $rows = $this->hl7Data('value_sets.json')['rows'];

        DB::transaction(function () use ($rows): void {
            foreach ($rows as $row) {
                CodeSetValue::query()->updateOrCreate(
                    ['value_set' => $row['value_set'], 'code' => $row['code']],
                    [
                        'display' => $row['display'],
                        'hl7_code' => $row['hl7_code'],
                        'hl7_display' => $row['hl7_display'],
                        'hl7_table' => $row['hl7_table'],
                        'fhir_code' => $row['fhir_code'],
                        'fhir_system' => $row['fhir_system'],
                        'notes' => $row['notes'],
                        'is_active' => true,
                        'display_order' => $row['display_order'],
                    ],
                );
            }
        });

        $this->command?->info(sprintf('  Code sets: %d values.', count($rows)));
    }
}
