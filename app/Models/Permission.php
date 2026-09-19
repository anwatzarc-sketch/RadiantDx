<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A single protected operation. The catalogue is owned by
 * database/seeders/permissions-seed.php and mirrored into this table.
 *
 * @property int $id
 * @property string $name
 * @property string $label
 * @property string $module
 * @property string|null $description
 * @property int $module_order
 * @property int $display_order
 * @property-read Collection<int, Role> $roles
 */
class Permission extends Model
{
    protected $fillable = [
        'name',
        'label',
        'module',
        'description',
        'module_order',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'module_order' => 'integer',
            'display_order' => 'integer',
        ];
    }

    /** @return BelongsToMany<Role, $this> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permissions')->withTimestamps();
    }

    /** @param Builder<$this> $query */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('module_order')->orderBy('display_order')->orderBy('name');
    }

    /**
     * Permissions grouped by module, in catalogue order, for the role editor.
     *
     * @return \Illuminate\Support\Collection<string, Collection<int, self>>
     */
    public static function groupedByModule(): \Illuminate\Support\Collection
    {
        return self::query()->ordered()->get()->groupBy('module');
    }
}
