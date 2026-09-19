<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Permission;
use App\Support\PermissionCatalogue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Mirrors the permission catalogue into the permissions table.
 *
 * Running this repeatedly is safe: existing rows are refreshed in place, new
 * ones are inserted, and permissions that no longer appear in the catalogue
 * are removed together with their role grants.
 */
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $rows = PermissionCatalogue::rows();

        DB::transaction(function () use ($rows): void {
            foreach ($rows as $row) {
                Permission::query()->updateOrCreate(
                    ['name' => $row['name']],
                    [
                        'label' => $row['label'],
                        'module' => $row['module'],
                        'module_order' => $row['module_order'],
                        'display_order' => $row['display_order'],
                    ],
                );
            }

            // Retire permissions dropped from the catalogue. The cascade on
            // role_permissions removes the corresponding grants.
            Permission::query()
                ->whereNotIn('name', array_column($rows, 'name'))
                ->delete();
        });

        $this->command?->info(sprintf('Permission catalogue synchronised (%d permissions).', count($rows)));
    }
}
