<?php

declare(strict_types=1);

namespace App\Services\Laboratory;

use App\Enums\AgeUnit;
use App\Enums\Gender;
use App\Enums\PatientAgeBasis;
use App\Models\LaboratoryRequisition;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * What reference-range selection needs to know about a patient, at one
 * moment.
 *
 * The moment is the specimen's: a baby collected at 27 days old is a neonate
 * for that sample even if the result is entered on day 30. So the as-of date
 * is collected_at, then the requested date, then now.
 */
final class PatientContext
{
    public const ADULT_AGE_YEARS = 18;

    private function __construct(
        public readonly ?Gender $gender,
        public readonly ?CarbonImmutable $dateOfBirth,
        public readonly ?int $ageYears,
        public readonly CarbonImmutable $asOf,
    ) {}

    public static function fromRequisition(LaboratoryRequisition $requisition): self
    {
        $asOf = $requisition->collected_at ?? $requisition->requested_date ?? now();

        return self::make(
            $requisition->patient_gender,
            $requisition->patient_date_of_birth,
            $requisition->patient_age_years,
            $asOf,
        );
    }

    public static function make(
        ?Gender $gender,
        ?CarbonInterface $dateOfBirth,
        ?int $ageYears,
        ?CarbonInterface $asOf = null,
    ): self {
        $asOf = CarbonImmutable::instance($asOf ?? now());
        $dateOfBirth = $dateOfBirth === null ? null : CarbonImmutable::instance($dateOfBirth)->startOfDay();

        // A birth date after the specimen was taken is a data-entry error.
        // Treat it as unknown rather than as a negative age.
        if ($dateOfBirth !== null && $dateOfBirth->greaterThan($asOf)) {
            $dateOfBirth = null;
        }

        return new self($gender, $dateOfBirth, $ageYears, $asOf);
    }

    public function ageBasis(): PatientAgeBasis
    {
        return match (true) {
            $this->dateOfBirth !== null => PatientAgeBasis::DateOfBirth,
            $this->ageYears !== null => PatientAgeBasis::AgeYears,
            default => PatientAgeBasis::Unknown,
        };
    }

    /**
     * The patient's age in whole units of the given one, floored.
     *
     * Each bound is compared in its own unit, so an 11-month-old is 0 a and
     * 11 mo and 334 d, and matches "28 d to 1 a" on both sides.
     */
    public function ageIn(AgeUnit $unit): int
    {
        if ($this->dateOfBirth === null) {
            throw new \LogicException('ageIn() needs a date of birth.');
        }

        $difference = match ($unit) {
            AgeUnit::Days => $this->dateOfBirth->diffInDays($this->asOf),
            AgeUnit::Months => $this->dateOfBirth->diffInMonths($this->asOf),
            AgeUnit::Years => $this->dateOfBirth->diffInYears($this->asOf),
        };

        return (int) floor($difference);
    }

    /** Known to be under 18. An unknown age is not. */
    public function isKnownMinor(): bool
    {
        return match ($this->ageBasis()) {
            PatientAgeBasis::DateOfBirth => $this->ageIn(AgeUnit::Years) < self::ADULT_AGE_YEARS,
            PatientAgeBasis::AgeYears => $this->ageYears < self::ADULT_AGE_YEARS,
            PatientAgeBasis::Unknown => false,
        };
    }
}
