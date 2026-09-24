<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\LaboratoryPanel;
use App\Models\LaboratoryTest;
use Database\Seeders\Concerns\ReadsHl7MasterData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The starter panels and their member tests.
 *
 * Every member code is checked before anything is written, and a missing one
 * stops the seed: a panel silently missing a test would be ordered, and
 * reported, incomplete.
 *
 * A panel the laboratory already has keeps its name and its membership,
 * which it may have changed on purpose; only its LOINC code is refreshed.
 */
class LaboratoryPanelSeeder extends Seeder
{
    use ReadsHl7MasterData;

    public function run(): void
    {
        $rows = $this->hl7Data('panels.json')['rows'];

        $tests = LaboratoryTest::query()->pluck('id', 'code');
        $missing = [];

        foreach ($rows as $row) {
            foreach ($row['members'] as $code) {
                if (! $tests->has($code)) {
                    $missing[] = "{$row['code']} → {$code}";
                }
            }
        }

        if ($missing !== []) {
            throw new RuntimeException('Panel members not in the catalogue: '.implode(', ', $missing).'. Seed the catalogue first.');
        }

        $created = 0;

        DB::transaction(function () use ($rows, $tests, &$created): void {
            foreach ($rows as $row) {
                $panel = LaboratoryPanel::withTrashed()->where('code', $row['code'])->first();

                if ($panel?->trashed()) {
                    $this->command?->warn("  Panel {$row['code']} was deleted here; left deleted.");

                    continue;
                }

                if ($panel !== null) {
                    $panel->update(['loinc_code' => $row['loinc_code']]);

                    continue;
                }

                $panel = LaboratoryPanel::query()->create([
                    'code' => $row['code'],
                    'name' => $row['name'],
                    'loinc_code' => $row['loinc_code'],
                    'category' => $this->category($row['hl7_section_code']),
                    'is_active' => true,
                    'display_order' => $row['display_order'],
                ]);

                $members = [];

                foreach ($row['members'] as $order => $code) {
                    $members[$tests[$code]] = ['display_order' => $order + 1];
                }

                $panel->tests()->sync($members);
                $created++;
            }
        });

        $this->command?->info(sprintf('  Panels: %d in the data, %d created.', count($rows), $created));
    }

    /**
     * A section from HL7 Table 0074, or a hospital service (Table 0069) for a
     * panel that spans sections: "LAB" is a laboratory-wide profile.
     */
    private function category(string $code): string
    {
        foreach ($this->valueSet('test_category_section') as $row) {
            if ($row['hl7_code'] === $code) {
                return $row['display'];
            }
        }

        foreach ($this->valueSet('hospital_service_department') as $row) {
            if ($row['code'] === $code) {
                return $row['display'];
            }
        }

        throw new RuntimeException("Panel category '{$code}' is in neither HL7 0074 nor the hospital services.");
    }
}
