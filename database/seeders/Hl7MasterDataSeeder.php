<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * The HL7 master data, in dependency order.
 *
 * Safe to run on production, and to run again:
 *
 *     php artisan db:seed --class=Hl7MasterDataSeeder --force
 *
 * It never truncates, never touches roles, permissions, users or staff, and
 * writes no audit entries (it bypasses the services that audit). A range the
 * laboratory has verified is never overwritten.
 */
class Hl7MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info(config('seeding.placeholder_ranges')
            ? 'HL7 master data (placeholder ranges ACTIVE, adult defaults filled).'
            : 'HL7 master data (placeholder ranges INACTIVE, no adult defaults).');

        $this->call([
            Hl7CodeSetSeeder::class,
            DepartmentSeeder::class,
            LaboratoryCatalogSeeder::class,
            LaboratoryReferenceRangeSeeder::class,
            LaboratoryPanelSeeder::class,
        ]);
    }
}
