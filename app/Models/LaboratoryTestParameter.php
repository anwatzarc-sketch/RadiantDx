<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ParameterDataType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One reportable component of a laboratory test.
 *
 * @property int $id
 * @property int $laboratory_test_id
 * @property string $name
 * @property string $code
 * @property ParameterDataType $data_type
 * @property string|null $unit
 * @property string|null $reference_range
 * @property string|null $reference_low
 * @property string|null $reference_high
 * @property bool $is_active
 * @property-read LaboratoryTest $test
 * @property-read Collection<int, LaboratoryTestParameterOption> $options
 */
class LaboratoryTestParameter extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'laboratory_test_id',
        'name',
        'code',
        'description',
        'data_type',
        'unit',
        'reference_range',
        'reference_low',
        'reference_high',
        'critical_low',
        'critical_high',
        'decimal_precision',
        'abnormal_when',
        'is_active',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'data_type' => ParameterDataType::class,
            'reference_low' => 'decimal:6',
            'reference_high' => 'decimal:6',
            'critical_low' => 'decimal:6',
            'critical_high' => 'decimal:6',
            'decimal_precision' => 'integer',
            'is_active' => 'boolean',
            'display_order' => 'integer',
        ];
    }

    /** @return BelongsTo<LaboratoryTest, $this> */
    public function test(): BelongsTo
    {
        return $this->belongsTo(LaboratoryTest::class, 'laboratory_test_id');
    }

    /** @return HasMany<LaboratoryTestParameterOption, $this> */
    public function options(): HasMany
    {
        return $this->hasMany(LaboratoryTestParameterOption::class)
            ->orderBy('display_order')
            ->orderBy('label');
    }

    /** @return HasMany<LaboratoryResultParameter, $this> */
    public function resultParameters(): HasMany
    {
        return $this->hasMany(LaboratoryResultParameter::class);
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
                ->orWhereHas('test', function (Builder $test) use ($term): void {
                    $test->where('name', 'like', "%{$term}%")->orWhere('code', 'like', "%{$term}%");
                });
        });
    }

    public function isReferencedByLaboratoryRecords(): bool
    {
        return $this->resultParameters()->exists();
    }

    /**
     * Values the entry form should offer, keyed by stored value.
     *
     * @return array<string, string>
     */
    public function selectableValues(): array
    {
        if ($this->data_type === ParameterDataType::Dropdown) {
            return $this->options
                ->where('is_active', true)
                ->mapWithKeys(fn (LaboratoryTestParameterOption $option): array => [$option->value => $option->label])
                ->all();
        }

        return $this->data_type->builtInValues();
    }

    public function referenceSummary(): string
    {
        if ($this->reference_range) {
            return $this->reference_range;
        }

        $low = $this->reference_low;
        $high = $this->reference_high;

        return match (true) {
            $low !== null && $high !== null => $this->formatBound($low).' - '.$this->formatBound($high),
            $low !== null => '>= '.$this->formatBound($low),
            $high !== null => '<= '.$this->formatBound($high),
            default => '',
        };
    }

    private function formatBound(string|float|null $value): string
    {
        return $value === null ? '' : rtrim(rtrim(number_format((float) $value, 6, '.', ''), '0'), '.');
    }
}
