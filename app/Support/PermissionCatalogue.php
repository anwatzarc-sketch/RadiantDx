<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Read-only view over database/seeders/permissions-seed.php.
 *
 * Everything that needs to know which permissions exist reads them through
 * here, so the seed file stays the single source of truth.
 */
final class PermissionCatalogue
{
    /** @var array<int, array{module: string, permissions: array<string, string>}>|null */
    private static ?array $modules = null;

    public static function path(): string
    {
        return database_path('seeders/permissions-seed.php');
    }

    /**
     * Modules in declaration order, each with its permissions.
     *
     * @return array<int, array{module: string, permissions: array<string, string>}>
     */
    public static function modules(): array
    {
        if (self::$modules === null) {
            /** @var array<int, array{module: string, permissions: array<string, string>}> $modules */
            $modules = require self::path();
            self::$modules = $modules;
        }

        return self::$modules;
    }

    /**
     * Every permission as a flat row ready for persistence.
     *
     * @return array<int, array{name: string, label: string, module: string, module_order: int, display_order: int}>
     */
    public static function rows(): array
    {
        $rows = [];

        foreach (self::modules() as $moduleIndex => $module) {
            $displayOrder = 0;

            foreach ($module['permissions'] as $name => $label) {
                $rows[] = [
                    'name' => $name,
                    'label' => $label,
                    'module' => $module['module'],
                    'module_order' => $moduleIndex + 1,
                    'display_order' => ++$displayOrder,
                ];
            }
        }

        return $rows;
    }

    /** @return array<int, string> */
    public static function names(): array
    {
        return array_column(self::rows(), 'name');
    }

    public static function has(string $permission): bool
    {
        return in_array($permission, self::names(), true);
    }

    /** Clears the memoised catalogue. Used by tests that rewrite the seed file. */
    public static function flush(): void
    {
        self::$modules = null;
    }
}
