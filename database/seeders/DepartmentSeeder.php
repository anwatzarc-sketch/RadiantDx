<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Department;
use Database\Seeders\Concerns\ReadsHl7MasterData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Hospital services (HL7 Table 0069) as departments.
 *
 * A department the laboratory has already created or renamed keeps its name;
 * only its HL7 mapping is refreshed. One it has deleted stays deleted.
 */
class DepartmentSeeder extends Seeder
{
    use ReadsHl7MasterData;

    public function run(): void
    {
        $rows = $this->valueSet('hospital_service_department');
        $created = 0;

        DB::transaction(function () use ($rows, &$created): void {
            foreach ($rows as $row) {
                $department = Department::withTrashed()->where('code', $row['code'])->first();

                if ($department?->trashed()) {
                    $this->command?->warn("  Department {$row['code']} was deleted here; left deleted.");

                    continue;
                }

                if ($department === null) {
                    Department::query()->create([
                        'code' => $row['code'],
                        'name' => $row['display'],
                        'hl7_service_code' => $row['hl7_code'],
                        'is_active' => true,
                    ]);
                    $created++;

                    continue;
                }

                $department->update(['hl7_service_code' => $row['hl7_code']]);
            }
        });

        $this->command?->info(sprintf('  Departments: %d in the value set, %d created.', count($rows), $created));
    }
}
