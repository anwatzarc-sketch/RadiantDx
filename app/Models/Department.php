<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An organisational department.
 *
 * A reference entity rather than an enum: departments are facility specific and
 * an administrator must be able to add one without a code change.
 *
 * @property int $id
 * @property string $name
 */
class Department extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'hl7_service_code',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<Unit, $this> */
    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    /** @return HasMany<Staff, $this> */
    public function staff(): HasMany
    {
        return $this->hasMany(Staff::class);
    }

    /** @param Builder<$this> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
