<?php

declare(strict_types=1);

namespace App\Services\Laboratory;

use App\Enums\AbnormalWhen;
use App\Enums\AgeUnit;
use App\Enums\PatientAgeBasis;
use App\Enums\RangeSex;
use App\Enums\ReferenceRangeBasis;
use App\Models\LaboratoryReferenceRange;
use App\Models\LaboratoryResultParameter;
use App\Models\LaboratoryTestParameter;
use Illuminate\Support\Collection;

/**
 * Chooses the reference range a numeric result is judged against.
 *
 * Only active ranges are candidates. Among those that match the patient's sex
 * and age, a sex-specific range beats an any-sex one, then the narrowest age
 * interval wins, then the lowest display_order.
 *
 * When nothing matches:
 *   - an adult, or a patient whose age is unknown, gets the parameter's own
 *     range (the adult default), if it has one;
 *   - a patient known to be under 18 gets no range at all, and so no
 *     automatic flag. Judging a child against adult limits is the error this
 *     rule exists to prevent: it is better for the report to say plainly that
 *     no age-appropriate range exists.
 */
class ReferenceRangeResolver
{
    /**
     * The age a patient recorded only as "0 years" is assumed to be.
     *
     * Such a patient is somewhere in their first year. They are placed at the
     * start of the infant band rather than in the neonatal one: a neonate is
     * almost always recorded with a date of birth, and neonatal ranges are
     * the most extreme, so they are the wrong guess to fall into.
     */
    private const INFANT_AGE_DAYS = 28;

    public function resolve(LaboratoryTestParameter $parameter, PatientContext $patient): ResolvedReferenceRange
    {
        $ranges = $parameter->relationLoaded('referenceRanges')
            ? $parameter->referenceRanges
            : $parameter->referenceRanges()->get();

        $chosen = $this->choose($ranges->where('is_active', true), $patient);

        if ($chosen !== null) {
            return new ResolvedReferenceRange(
                ReferenceRangeBasis::Stratified,
                $patient->ageBasis(),
                $chosen,
                $chosen->reference_low,
                $chosen->reference_high,
                $chosen->critical_low,
                $chosen->critical_high,
                $chosen->abnormal_when ?? AbnormalWhen::OutsideRange,
                $this->label($chosen->resultLabel(), $patient),
            );
        }

        if ($patient->isKnownMinor()) {
            return $this->none($patient, LaboratoryResultParameter::NO_AGE_APPROPRIATE_RANGE);
        }

        $hasDefault = $parameter->reference_low !== null
            || $parameter->reference_high !== null
            || $parameter->critical_low !== null
            || $parameter->critical_high !== null;

        if (! $hasDefault) {
            return $this->none($patient, $parameter->reference_range ?: null);
        }

        return new ResolvedReferenceRange(
            ReferenceRangeBasis::Default,
            $patient->ageBasis(),
            null,
            $parameter->reference_low,
            $parameter->reference_high,
            $parameter->critical_low,
            $parameter->critical_high,
            AbnormalWhen::OutsideRange,
            $parameter->referenceSummary() ?: null,
        );
    }

    /**
     * Whether one range applies to the patient. Public so the admin screen
     * and the tests can ask the same question the selection does.
     */
    public function matches(LaboratoryReferenceRange $range, PatientContext $patient): bool
    {
        return $this->matchesSex($range, $patient) && $this->matchesAge($range, $patient);
    }

    /** @param Collection<int, LaboratoryReferenceRange> $ranges */
    private function choose(Collection $ranges, PatientContext $patient): ?LaboratoryReferenceRange
    {
        return $ranges
            ->filter(fn (LaboratoryReferenceRange $range): bool => $this->matches($range, $patient))
            ->sort(function (LaboratoryReferenceRange $a, LaboratoryReferenceRange $b): int {
                return [$a->sex === RangeSex::Any, $a->ageSpanInDays(), $a->display_order, $a->getKey()]
                    <=> [$b->sex === RangeSex::Any, $b->ageSpanInDays(), $b->display_order, $b->getKey()];
            })
            ->first();
    }

    private function matchesSex(LaboratoryReferenceRange $range, PatientContext $patient): bool
    {
        return $range->sex === RangeSex::Any
            || $range->sex === RangeSex::forPatient($patient->gender);
    }

    private function matchesAge(LaboratoryReferenceRange $range, PatientContext $patient): bool
    {
        if (! $range->hasAgeLimits()) {
            return true;
        }

        return match ($patient->ageBasis()) {
            PatientAgeBasis::DateOfBirth => $this->withinBounds($range, fn (AgeUnit $unit): int => $patient->ageIn($unit)),
            PatientAgeBasis::AgeYears => $this->matchesAgeYears($range, (int) $patient->ageYears),

            // With no age at all, only a range that does not depend on age
            // can honestly be said to apply.
            PatientAgeBasis::Unknown => false,
        };
    }

    /**
     * Age known only as a whole number of years.
     *
     * From 1 year up, a bound in days or months lies inside the first year:
     * a lower bound there is always met, an upper bound there never is. Year
     * bounds compare directly. A patient recorded as 0 years is placed at
     * INFANT_AGE_DAYS.
     */
    private function matchesAgeYears(LaboratoryReferenceRange $range, int $years): bool
    {
        if ($years === 0) {
            return $this->withinBounds($range, fn (AgeUnit $unit): int => (int) floor(self::INFANT_AGE_DAYS / $unit->inDays()));
        }

        $days = $years * AgeUnit::Years->inDays();

        $minMet = $range->age_min === null
            || ($range->age_min_unit === AgeUnit::Years
                ? $years >= (float) $range->age_min
                : $days >= $range->ageMinInDays());

        $maxMet = $range->age_max === null
            || ($range->age_max_unit === AgeUnit::Years
                ? $years < (float) $range->age_max
                : $days < $range->ageMaxInDays());

        return $minMet && $maxMet;
    }

    /** @param \Closure(AgeUnit): int $ageIn */
    private function withinBounds(LaboratoryReferenceRange $range, \Closure $ageIn): bool
    {
        if ($range->age_min !== null && $range->age_min_unit !== null
            && $ageIn($range->age_min_unit) < (float) $range->age_min) {
            return false;
        }

        if ($range->age_max !== null && $range->age_max_unit !== null
            && $ageIn($range->age_max_unit) >= (float) $range->age_max) {
            return false;
        }

        return true;
    }

    private function none(PatientContext $patient, ?string $label): ResolvedReferenceRange
    {
        return new ResolvedReferenceRange(
            ReferenceRangeBasis::None,
            $patient->ageBasis(),
            null,
            null,
            null,
            null,
            null,
            null,
            $label,
        );
    }

    private function label(string $label, PatientContext $patient): string
    {
        return $patient->ageBasis() === PatientAgeBasis::AgeYears
            ? $label.' – age from age_years'
            : $label;
    }
}
