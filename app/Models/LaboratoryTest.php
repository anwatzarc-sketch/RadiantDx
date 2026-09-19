<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TestResultType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An individual laboratory service. Tests are retired by deactivation rather
 * than deletion so historical requisitions and results keep their references.
 *
 * @property int $id
 * @property string $name
 * @property string $code
 * @property TestResultType $result_type
 * @property bool $is_active
 * @property-read Collection<int, LaboratoryTestParameter> $parameters
 * @property-read Collection<int, LaboratoryPanel> $panels
 */
class LaboratoryTest extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'description',
        'category',
        'specimen_type',
        'result_type',
        'unit',
        'reference_range',
        'reference_low',
        'reference_high',
        'decimal_precision',
        'turnaround_time_hours',
        'is_active',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'result_type' => TestResultType::class,
            'reference_low' => 'decimal:6',
            'reference_high' => 'decimal:6',
            'decimal_precision' => 'integer',
            'turnaround_time_hours' => 'integer',
            'is_active' => 'boolean',
            'display_order' => 'integer',
        ];
    }

    /** @return HasMany<LaboratoryTestParameter, $this> */
    public function parameters(): HasMany
    {
        return $this->hasMany(LaboratoryTestParameter::class)
            ->orderBy('display_order')
            ->orderBy('name');
    }

    /** @return HasMany<LaboratoryTestParameter, $this> */
    public function activeParameters(): HasMany
    {
        return $this->parameters()->where('is_active', true);
    }

    /** @return BelongsToMany<LaboratoryPanel, $this> */
    public function panels(): BelongsToMany
    {
        return $this->belongsToMany(LaboratoryPanel::class, 'laboratory_panel_tests')
            ->withPivot('display_order')
            ->withTimestamps();
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

    public function isParameterised(): bool
    {
        return $this->result_type === TestResultType::Parameterised;
    }

    /** A parameterised test cannot be requested until it has a reportable parameter. */
    public function isReadyForRequisition(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return ! $this->isParameterised() || $this->activeParameters()->exists();
    }

    /** Whether removing the test would orphan historical laboratory records. */
    public function isReferencedByLaboratoryRecords(): bool
    {
        return $this->requisitionItems()->exists();
    }

    public function referenceSummary(): string
    {
        if ($this->reference_range) {
            return $this->reference_range;
        }

        if ($this->reference_low !== null && $this->reference_high !== null) {
            return $this->formatBound($this->reference_low).' - '.$this->formatBound($this->reference_high);
        }

        return '';
    }

    private function formatBound(string|float|null $value): string
    {
        return $value === null ? '' : rtrim(rtrim(number_format((float) $value, 6, '.', ''), '0'), '.');
    }
}
