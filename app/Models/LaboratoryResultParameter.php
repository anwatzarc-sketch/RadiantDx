<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AbnormalWhen;
use App\Enums\Interpretation;
use App\Enums\ParameterDataType;
use App\Enums\PatientAgeBasis;
use App\Enums\ReferenceRangeBasis;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One reported value. Descriptive columns are snapshots taken when the result
 * was created, so a later catalogue change never rewrites history.
 *
 * @property int $id
 * @property int $laboratory_result_id
 * @property int|null $laboratory_test_parameter_id
 * @property string $parameter_name
 * @property ParameterDataType $data_type
 * @property string|null $result_value
 * @property string|null $result_numeric
 * @property Interpretation|null $auto_interpretation
 * @property Interpretation|null $interpretation
 */
class LaboratoryResultParameter extends Model
{
    /**
     * The range label written when a patient under 18 matched no stratified
     * range. An adult default is deliberately not used for a child, so the
     * row says why it carries no range, and the report footnotes it.
     */
    public const NO_AGE_APPROPRIATE_RANGE = 'No age-appropriate range';

    protected $fillable = [
        'laboratory_result_id',
        'laboratory_test_parameter_id',
        'laboratory_reference_range_id',
        'parameter_name',
        'parameter_code',
        'data_type',
        'unit',
        'reference_range',
        'reference_low',
        'reference_high',
        'critical_low',
        'critical_high',
        'decimal_precision',
        'abnormal_when',
        'reference_range_basis',
        'patient_age_basis',
        'result_value',
        'result_numeric',
        'auto_interpretation',
        'interpretation',
        'comment',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'data_type' => ParameterDataType::class,
            'auto_interpretation' => Interpretation::class,
            'interpretation' => Interpretation::class,
            'reference_range_basis' => ReferenceRangeBasis::class,
            'patient_age_basis' => PatientAgeBasis::class,
            'reference_low' => 'decimal:6',
            'reference_high' => 'decimal:6',
            'critical_low' => 'decimal:6',
            'critical_high' => 'decimal:6',
            'result_numeric' => 'decimal:6',
            'decimal_precision' => 'integer',
            'display_order' => 'integer',
        ];
    }

    /** @return BelongsTo<LaboratoryResult, $this> */
    public function result(): BelongsTo
    {
        return $this->belongsTo(LaboratoryResult::class, 'laboratory_result_id');
    }

    /** @return BelongsTo<LaboratoryTestParameter, $this> */
    public function parameter(): BelongsTo
    {
        return $this->belongsTo(LaboratoryTestParameter::class, 'laboratory_test_parameter_id');
    }

    /** @return BelongsTo<LaboratoryReferenceRange, $this> */
    public function referenceRange(): BelongsTo
    {
        return $this->belongsTo(LaboratoryReferenceRange::class, 'laboratory_reference_range_id')->withTrashed();
    }

    /**
     * The numeric flagging rule snapshotted from the selected range.
     *
     * Numeric rows only: on a yes/no or positive/negative row this column
     * holds the abnormal value instead, and means something else entirely.
     * Anything unrecognised reads as outside_range, the behaviour before
     * ranges carried a rule.
     */
    public function numericRule(): AbnormalWhen
    {
        return AbnormalWhen::tryFrom((string) $this->abnormal_when) ?? AbnormalWhen::OutsideRange;
    }

    /**
     * The footnote a printed report owes the reader for this row, or null.
     *
     * A range picked from the patient's recorded age in years, an adult
     * default standing in for a missing stratified range, and no range at all
     * are each worth saying out loud on a clinical document.
     */
    public function rangeFootnote(): ?string
    {
        return match (true) {
            $this->reference_range_basis === ReferenceRangeBasis::None
                && $this->reference_range === self::NO_AGE_APPROPRIATE_RANGE => 'No age-appropriate reference range established.',
            $this->reference_range_basis === ReferenceRangeBasis::None => 'No reference range applies; not flagged automatically.',
            $this->reference_range_basis === ReferenceRangeBasis::Default => 'Adult default range; no age- and sex-specific range matched.',
            $this->patient_age_basis === PatientAgeBasis::AgeYears => 'Range selected from age in years; date of birth not recorded.',
            default => null,
        };
    }

    public function hasValue(): bool
    {
        return $this->result_value !== null && trim($this->result_value) !== '';
    }

    public function isOutsideReference(): bool
    {
        return $this->interpretation?->isOutsideReference() ?? false;
    }

    /**
     * True when the laboratory reported a flag different from the one the
     * reference range suggests. Surfaced in the UI rather than silently applied.
     */
    public function divergesFromSuggestion(): bool
    {
        return $this->auto_interpretation !== null
            && $this->interpretation !== null
            && $this->auto_interpretation !== $this->interpretation;
    }

    /** The value formatted the way it should appear on screen and on the report. */
    public function displayValue(): string
    {
        if (! $this->hasValue()) {
            return '';
        }

        $value = trim((string) $this->result_value);

        if ($this->data_type === ParameterDataType::Numeric && $this->result_numeric !== null) {
            return number_format((float) $this->result_numeric, $this->decimal_precision, '.', '');
        }

        return match ($this->data_type) {
            ParameterDataType::Boolean => $value === 'yes' ? 'Yes' : 'No',
            ParameterDataType::PositiveNegative => $value === 'positive' ? 'Positive' : 'Negative',
            default => $value,
        };
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

    /**
     * Values the entry form should offer. Options are read from the catalogue
     * when the parameter still exists, otherwise from the built-in sets.
     *
     * @return array<string, string>
     */
    public function selectableValues(): array
    {
        if ($this->data_type === ParameterDataType::Dropdown) {
            return $this->parameter?->selectableValues() ?? [];
        }

        return $this->data_type->builtInValues();
    }

    private function formatBound(string|float|null $value): string
    {
        return $value === null ? '' : rtrim(rtrim(number_format((float) $value, 6, '.', ''), '0'), '.');
    }
}
