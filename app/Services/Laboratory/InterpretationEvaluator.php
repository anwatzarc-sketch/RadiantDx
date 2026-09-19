<?php

declare(strict_types=1);

namespace App\Services\Laboratory;

use App\Enums\Interpretation;
use App\Enums\ParameterDataType;
use App\Models\LaboratoryResultParameter;

/**
 * Suggests a flag for an entered value by comparing it with the configured
 * reference range.
 *
 * This is an aid, never an authority. The evaluator only ever produces a
 * suggestion; the caller stores it in auto_interpretation and applies it to the
 * reported interpretation only when the laboratory has not chosen one. The
 * entered value itself is never modified.
 */
class InterpretationEvaluator
{
    /**
     * Parse an entered value into the numeric form used for range comparison.
     *
     * Censored values such as "<0.5" or "> 200" keep their text form in
     * result_value; the bare number is what gets compared.
     */
    public function toNumeric(?string $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $normalised = str_replace([',', ' '], ['.', ''], trim($value));

        if ($normalised === '') {
            return null;
        }

        if (preg_match('/^[<>]=?(-?\d+(?:\.\d+)?)$/', $normalised, $matches) === 1) {
            return (float) $matches[1];
        }

        return is_numeric($normalised) ? (float) $normalised : null;
    }

    /**
     * The flag the reference range implies for this value, or null when the
     * parameter carries no rule the system can evaluate.
     */
    public function suggest(LaboratoryResultParameter $parameter): ?Interpretation
    {
        if (! $parameter->hasValue()) {
            return null;
        }

        return match ($parameter->data_type) {
            ParameterDataType::Numeric => $this->suggestNumeric($parameter),
            ParameterDataType::PositiveNegative => $this->suggestPositiveNegative($parameter),
            ParameterDataType::Boolean => $this->suggestBoolean($parameter),
            ParameterDataType::Dropdown => $this->suggestDropdown($parameter),
            ParameterDataType::Text => null,
        };
    }

    private function suggestNumeric(LaboratoryResultParameter $parameter): ?Interpretation
    {
        $value = $parameter->result_numeric !== null
            ? (float) $parameter->result_numeric
            : $this->toNumeric($parameter->result_value);

        if ($value === null) {
            return null;
        }

        $criticalLow = $this->bound($parameter->critical_low);
        $criticalHigh = $this->bound($parameter->critical_high);
        $low = $this->bound($parameter->reference_low);
        $high = $this->bound($parameter->reference_high);

        if ($criticalLow !== null && $value < $criticalLow) {
            return Interpretation::CriticalLow;
        }

        if ($criticalHigh !== null && $value > $criticalHigh) {
            return Interpretation::CriticalHigh;
        }

        if ($low !== null && $value < $low) {
            return Interpretation::Low;
        }

        if ($high !== null && $value > $high) {
            return Interpretation::High;
        }

        // Without any configured bound there is nothing to compare against.
        if ($low === null && $high === null && $criticalLow === null && $criticalHigh === null) {
            return null;
        }

        return Interpretation::Normal;
    }

    private function suggestPositiveNegative(LaboratoryResultParameter $parameter): Interpretation
    {
        $value = mb_strtolower(trim((string) $parameter->result_value));

        if ($parameter->abnormal_when !== null && $parameter->abnormal_when !== '') {
            return $value === mb_strtolower($parameter->abnormal_when)
                ? Interpretation::Abnormal
                : Interpretation::Normal;
        }

        return $value === 'positive' ? Interpretation::Positive : Interpretation::Negative;
    }

    private function suggestBoolean(LaboratoryResultParameter $parameter): ?Interpretation
    {
        if ($parameter->abnormal_when === null || $parameter->abnormal_when === '') {
            return null;
        }

        $value = mb_strtolower(trim((string) $parameter->result_value));

        return $value === mb_strtolower($parameter->abnormal_when)
            ? Interpretation::Abnormal
            : Interpretation::Normal;
    }

    private function suggestDropdown(LaboratoryResultParameter $parameter): ?Interpretation
    {
        $option = $parameter->parameter?->options
            ->firstWhere('value', trim((string) $parameter->result_value));

        if ($option === null) {
            return null;
        }

        return $option->is_abnormal ? Interpretation::Abnormal : Interpretation::Normal;
    }

    private function bound(string|float|null $value): ?float
    {
        return $value === null ? null : (float) $value;
    }
}
