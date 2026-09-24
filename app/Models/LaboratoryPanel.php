<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A named grouping of laboratory tests that can be requested as one item.
 *
 * @property int $id
 * @property string $name
 * @property string $code
 * @property bool $is_active
 * @property-read Collection<int, LaboratoryTest> $tests
 */
class LaboratoryPanel extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'loinc_code',
        'description',
        'category',
        'is_active',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'display_order' => 'integer',
        ];
    }

    /** @return BelongsToMany<LaboratoryTest, $this> */
    public function tests(): BelongsToMany
    {
        return $this->belongsToMany(LaboratoryTest::class, 'laboratory_panel_tests')
            ->withPivot('display_order')
            ->withTimestamps()
            ->orderBy('laboratory_panel_tests.display_order');
    }

    /** @return BelongsToMany<LaboratoryTest, $this> */
    public function activeTests(): BelongsToMany
    {
        return $this->tests()->where('laboratory_tests.is_active', true);
    }

    /** @return HasMany<LaboratoryRequisitionItem, $this> */
    public function requisitionItems(): HasMany
    {
        return $this->hasMany(LaboratoryRequisitionItem::class);
    }

    /** @param Builder<$this> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @param Builder<$this> $query */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('display_order')->orderBy('name');
    }

    /** @param Builder<$this> $query */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $query->where(function (Builder $builder) use ($term): void {
            $builder->where('name', 'like', "%{$term}%")
                ->orWhere('code', 'like', "%{$term}%")
                ->orWhere('category', 'like', "%{$term}%");
        });
    }

    /** An empty panel cannot be requested because it expands to no work. */
    public function isReadyForRequisition(): bool
    {
        return $this->is_active && $this->activeTests()->exists();
    }

    public function isReferencedByLaboratoryRecords(): bool
    {
        return $this->requisitionItems()->exists();
    }
}
