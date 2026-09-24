<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AbnormalWhen;
use App\Enums\AgeUnit;
use App\Enums\RangeSex;
use App\Models\Concerns\RecordsActorSnapshots;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A reference range for one parameter, for one sex and one age interval.
 *
 * Age bounds are [min, max): min inclusive, max exclusive, each in its own
 * UCUM unit; a null bound is unbounded. Which range a patient gets is decided
 * by ReferenceRangeResolver, never here.
 *
 * A placeholder is a range no one at the laboratory has approved. Verifying
 * one records who approved it, as they were at the time (the verified_by
 * actor snapshot), which is the record ISO 15189 asks for.
 *
 * @property int $id
 * @property int $laboratory_test_parameter_id
 * @property RangeSex $sex
 * @property string|null $age_category
 * @property string|null $age_min
 * @property AgeUnit|null $age_min_unit
 * @property string|null $age_max
 * @property AgeUnit|null $age_max_unit
 * @property string|null $reference_low
 * @property string|null $reference_high
 * @property string|null $critical_low
 * @property string|null $critical_high
 * @property string|null $reference_range_text
 * @property AbnormalWhen|null $abnormal_when
 * @property bool $is_placeholder
 * @property bool $is_active
 * @property int $display_order
 * @property \Illuminate\Support\Carbon|null $verified_at
 * @property-read LaboratoryTestParameter $parameter
 */
class LaboratoryReferenceRange extends Model
{
    use RecordsActorSnapshots, SoftDeletes;

    /** @var list<string> */
    protected array $actorSnapshotRoles = ['verified_by'];

    protected $fillable = [
        'laboratory_test_parameter_id',
        'sex',
        'age_category',
        'age_min',
        'age_min_unit',
        'age_max',
        'age_max_unit',
        'reference_low',
        'reference_high',
        'critical_low',
        'critical_high',
        'reference_range_text',
        'abnormal_when',
        'is_placeholder',
        'notes',
        'is_active',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'sex' => RangeSex::class,
            'age_min' => 'decimal:2',
            'age_min_unit' => AgeUnit::class,
            'age_max' => 'decimal:2',
            'age_max_unit' => AgeUnit::class,
            'reference_low' => 'decimal:6',
            'reference_high' => 'decimal:6',
            'critical_low' => 'decimal:6',
            'critical_high' => 'decimal:6',
            'abnormal_when' => AbnormalWhen::class,
            'is_placeholder' => 'boolean',
            'is_active' => 'boolean',
            'display_order' => 'integer',
            'verified_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<LaboratoryTestParameter, $this> */
    public function parameter(): BelongsTo
    {
        return $this->belongsTo(LaboratoryTestParameter::class, 'laboratory_test_parameter_id');
    }

    /** @return BelongsTo<User, $this> */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /** @param Builder<$this> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @param Builder<$this> $query */
    public function scopePlaceholder(Builder $query): void
    {
        $query->where('is_placeholder', true);
    }

    /**
     * The configured order. The seed numbers rows youngest band first, so a
     * list reads as the bands a patient moves through.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('display_order')->orderBy('id');
    }

    // ------------------------------------------------------------ intervals

    /** Lower bound in days, 0 when unbounded. For comparing intervals only. */
    public function ageMinInDays(): float
    {
        return $this->age_min === null || $this->age_min_unit === null
            ? 0.0
            : (float) $this->age_min * $this->age_min_unit->inDays();
    }

    /** Upper bound in days, INF when unbounded. For comparing intervals only. */
    public function ageMaxInDays(): float
    {
        return $this->age_max === null || $this->age_max_unit === null
            ? INF
            : (float) $this->age_max * $this->age_max_unit->inDays();
    }

    public function ageSpanInDays(): float
    {
        return $this->ageMaxInDays() - $this->ageMinInDays();
    }

    public function hasAgeLimits(): bool
    {
        return $this->age_min !== null || $this->age_max !== null;
    }

    /** Whether the two [min, max) intervals share any age. */
    public function overlapsAgeOf(self $other): bool
    {
        return $this->ageMinInDays() < $other->ageMaxInDays()
            && $other->ageMinInDays() < $this->ageMaxInDays();
    }

    // -------------------------------------------------------------- display

    /** "0–28 d", "28 d–1 a", "18+ a", "all ages". */
    public function ageLabel(): string
    {
        $min = $this->age_min === null ? null : self::number($this->age_min);
        $max = $this->age_max === null ? null : self::number($this->age_max);
        $minUnit = $this->age_min_unit?->value;
        $maxUnit = $this->age_max_unit?->value;

        return match (true) {
            $min === null && $max === null => 'all ages',
            $max === null => "{$min}+ {$minUnit}",
            $min === null => "< {$max} {$maxUnit}",
            $minUnit === $maxUnit => "{$min}–{$max} {$maxUnit}",
            default => "{$min} {$minUnit}–{$max} {$maxUnit}",
        };
    }

    /** "13.5–17.5", "< 200", "> 40", or the configured text. */
    public function valuesLabel(): string
    {
        if ($this->reference_range_text) {
            return $this->reference_range_text;
        }

        $low = $this->reference_low === null ? null : self::number($this->reference_low);
        $high = $this->reference_high === null ? null : self::number($this->reference_high);

        return match (true) {
            $low !== null && $high !== null => "{$low}–{$high}",
            $low !== null => "> {$low}",
            $high !== null => "< {$high}",
            default => 'no limits',
        };
    }

    /** "13.5–17.5 (M, 18+ a)", the label a result row carries. */
    public function resultLabel(): string
    {
        $who = $this->sex === RangeSex::Any ? '' : $this->sex->value.', ';

        return $this->valuesLabel()." ({$who}{$this->ageLabel()})";
    }

    public static function number(string|float|int $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 6, '.', ''), '0'), '.');
    }
}
