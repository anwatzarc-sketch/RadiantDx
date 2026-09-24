<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\Gender;
use App\Enums\PatientAgeBasis;
use App\Enums\ReferenceRangeBasis;
use App\Models\LaboratoryReferenceRange;
use App\Models\LaboratoryResultParameter;
use App\Models\LaboratoryTestParameter;
use App\Services\Laboratory\PatientContext;
use App\Services\Laboratory\ReferenceRangeResolver;
use App\Services\Laboratory\ResolvedReferenceRange;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Reference-range selection by age and sex.
 *
 * The ranges are the real seed data, read from the committed JSON and built
 * in memory, so these cases prove the selection against what production will
 * actually load, with no database in the way.
 *
 * Every age is measured on AS_OF, the specimen's collection date.
 */
class ReferenceRangeResolverTest extends TestCase
{
    private const AS_OF = '2026-06-15 09:30:00';

    private ReferenceRangeResolver $resolver;

    private CarbonImmutable $asOf;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new ReferenceRangeResolver;
        $this->asOf = CarbonImmutable::parse(self::AS_OF);
    }

    // --------------------------------------------------- date of birth known

    #[Test]
    public function a_newborn_on_day_0_is_a_neonate(): void
    {
        $this->assertBand('HGB', $this->born(days: 0), 'neonate', low: '14', high: '24');
    }

    #[Test]
    public function day_27_is_still_a_neonate(): void
    {
        $this->assertBand('HGB', $this->born(days: 27), 'neonate');
    }

    #[Test]
    public function exactly_28_days_is_an_infant_because_the_upper_bound_is_exclusive(): void
    {
        $this->assertBand('HGB', $this->born(days: 28), 'infant', low: '9.5', high: '13.5');
    }

    #[Test]
    public function eleven_months_is_an_infant(): void
    {
        $this->assertBand('HGB', $this->born(months: 11), 'infant');
    }

    #[Test]
    public function the_first_birthday_is_a_child(): void
    {
        $this->assertBand('HGB', $this->born(years: 1), 'child', low: '11', high: '15');
    }

    #[Test]
    public function the_day_before_the_18th_birthday_is_an_adolescent(): void
    {
        $dob = $this->asOf->subYears(18)->addDay();

        $resolved = $this->assertBand('HGB', $this->patient(Gender::Female, $dob), 'adolescent', low: '12', high: '16');
        $this->assertSame('F', $resolved->range->sex->value);
    }

    #[Test]
    public function the_18th_birthday_selects_the_adult_male_range(): void
    {
        $resolved = $this->assertBand('HGB', $this->patient(Gender::Male, $this->asOf->subYears(18)), 'adult_all', low: '13.5', high: '17.5');

        $this->assertSame('M', $resolved->range->sex->value);
        $this->assertSame('13.5–17.5 (M, 18+ a)', $resolved->label);
    }

    #[Test]
    public function the_18th_birthday_selects_the_adult_female_range(): void
    {
        $resolved = $this->assertBand('HGB', $this->patient(Gender::Female, $this->asOf->subYears(18)), 'adult_all', low: '12', high: '15.5');

        $this->assertSame('F', $resolved->range->sex->value);
    }

    #[Test]
    public function an_adult_of_unknown_sex_falls_back_to_the_adult_default(): void
    {
        $resolved = $this->resolve('HGB', $this->patient(Gender::Unknown, $this->asOf->subYears(18)));

        $this->assertSame(ReferenceRangeBasis::Default, $resolved->basis);
        $this->assertNull($resolved->range);
        $this->assertSame('12.000000', $resolved->referenceLow);
        $this->assertSame('17.500000', $resolved->referenceHigh);
    }

    #[Test]
    public function a_minor_of_unknown_sex_gets_no_range_rather_than_the_adult_one(): void
    {
        $resolved = $this->resolve('HGB', $this->patient(Gender::Unknown, $this->asOf->subYears(15)));

        $this->assertNoRange($resolved);
    }

    #[Test]
    public function an_elderly_woman_gets_the_elderly_female_esr_range(): void
    {
        $resolved = $this->assertBand('ESR', $this->patient(Gender::Female, $this->asOf->subYears(70)), 'elderly', low: '0', high: '30');

        $this->assertSame('F', $resolved->range->sex->value);
    }

    #[Test]
    public function a_six_month_old_has_no_esr_range_and_is_not_given_the_adult_one(): void
    {
        $resolved = $this->resolve('ESR', $this->patient(Gender::Male, $this->asOf->subMonths(6)));

        $this->assertNoRange($resolved);
    }

    // ------------------------------------------------ only age in years known

    #[Test]
    public function age_in_years_of_0_selects_the_infant_band_never_the_neonatal_one(): void
    {
        $resolved = $this->assertBand('HGB', $this->patient(Gender::Male, ageYears: 0), 'infant');

        $this->assertSame(PatientAgeBasis::AgeYears, $resolved->ageBasis);
        $this->assertStringEndsWith('– age from age_years', $resolved->label);
    }

    #[Test]
    public function age_in_years_of_40_selects_the_adult_male_range(): void
    {
        $resolved = $this->assertBand('HGB', $this->patient(Gender::Male, ageYears: 40), 'adult_all', low: '13.5', high: '17.5');

        $this->assertSame(PatientAgeBasis::AgeYears, $resolved->ageBasis);
    }

    #[Test]
    public function age_in_years_of_17_selects_the_adolescent_band(): void
    {
        $this->assertBand('HGB', $this->patient(Gender::Female, ageYears: 17), 'adolescent', low: '12', high: '16');
    }

    // ----------------------------------------------------------- age unknown

    #[Test]
    public function with_no_age_an_age_banded_parameter_falls_back_to_the_adult_default(): void
    {
        $resolved = $this->resolve('HGB', $this->patient(Gender::Male));

        $this->assertSame(ReferenceRangeBasis::Default, $resolved->basis);
        $this->assertSame(PatientAgeBasis::Unknown, $resolved->ageBasis);
    }

    #[Test]
    public function sodium_uses_its_all_ages_row_for_any_patient(): void
    {
        foreach ([
            $this->patient(Gender::Male),
            $this->patient(Gender::Unknown, $this->asOf->subDays(3)),
            $this->patient(Gender::Female, ageYears: 70),
        ] as $patient) {
            $resolved = $this->assertBand('NA', $patient, 'all', low: '135', high: '145');
            $this->assertSame('120.000000', $resolved->criticalLow);
            $this->assertSame('160.000000', $resolved->criticalHigh);
        }
    }

    // ------------------------------------------------------ special behaviour

    #[Test]
    public function neonatal_total_bilirubin_selects_its_limitless_row_and_carries_no_flagging_rule(): void
    {
        $resolved = $this->assertBand('TBIL', $this->born(days: 3), 'neonate');

        $this->assertNull($resolved->referenceLow);
        $this->assertNull($resolved->referenceHigh);
        $this->assertSame('never', $resolved->rule->value);
        $this->assertSame('no fixed range (0–28 d)', $resolved->label);
    }

    #[Test]
    public function a_sex_specific_range_beats_an_any_sex_range_that_also_matches(): void
    {
        $parameter = $this->parameter('HGB', extra: [[
            'sex' => 'any', 'age_category' => 'adult_all', 'age_min' => 18, 'age_min_unit' => 'a',
            'reference_low' => 1, 'reference_high' => 2, 'display_order' => 0,
        ]]);

        $resolved = $this->resolver->resolve($parameter, $this->patient(Gender::Male, $this->asOf->subYears(30)));

        $this->assertSame('M', $resolved->range->sex->value);
        $this->assertSame('13.500000', $resolved->referenceLow);
    }

    #[Test]
    public function between_matching_ranges_of_the_same_sex_the_narrowest_wins_then_display_order(): void
    {
        $parameter = $this->bareParameter([
            ['sex' => 'any', 'age_category' => 'all', 'reference_low' => 1, 'display_order' => 1],
            ['sex' => 'any', 'age_category' => 'adult_all', 'age_min' => 18, 'age_min_unit' => 'a', 'reference_low' => 2, 'display_order' => 9],
            ['sex' => 'any', 'age_category' => 'adult', 'age_min' => 18, 'age_min_unit' => 'a', 'age_max' => 65, 'age_max_unit' => 'a', 'reference_low' => 3, 'display_order' => 5],
            ['sex' => 'any', 'age_category' => 'adult', 'age_min' => 18, 'age_min_unit' => 'a', 'age_max' => 65, 'age_max_unit' => 'a', 'reference_low' => 4, 'display_order' => 2],
        ]);

        $resolved = $this->resolver->resolve($parameter, $this->patient(Gender::Male, $this->asOf->subYears(40)));

        $this->assertSame('4.000000', $resolved->referenceLow);
    }

    #[Test]
    public function inactive_ranges_are_never_selected(): void
    {
        $parameter = $this->parameter('HGB');
        foreach ($parameter->referenceRanges as $range) {
            $range->is_active = false;
        }

        $adult = $this->resolver->resolve($parameter, $this->patient(Gender::Male, $this->asOf->subYears(30)));
        $this->assertSame(ReferenceRangeBasis::Default, $adult->basis);

        $parameter->reference_low = $parameter->reference_high = null;
        $parameter->critical_low = $parameter->critical_high = null;

        $this->assertSame(
            ReferenceRangeBasis::None,
            $this->resolver->resolve($parameter, $this->patient(Gender::Male, $this->asOf->subYears(30)))->basis,
        );
    }

    #[Test]
    public function age_is_measured_on_the_collection_date_not_today(): void
    {
        $dob = $this->asOf->subDays(27);

        // Resolved 13 days later the baby is 40 days old, but the specimen
        // was taken on day 27.
        $this->travelTo($this->asOf->addDays(13));

        $resolved = $this->resolve('HGB', PatientContext::make(Gender::Male, $dob, null, $this->asOf));

        $this->assertSame('neonate', $resolved->range->age_category);
    }

    // --------------------------------------------------------------- helpers

    private function assertBand(string $code, PatientContext $patient, string $band, ?string $low = null, ?string $high = null): ResolvedReferenceRange
    {
        $resolved = $this->resolve($code, $patient);

        $this->assertSame(ReferenceRangeBasis::Stratified, $resolved->basis, "{$code}: expected a stratified range.");
        $this->assertSame($band, $resolved->range?->age_category, "{$code}: expected the {$band} band.");

        if ($low !== null) {
            $this->assertEquals((float) $low, (float) $resolved->referenceLow);
        }

        if ($high !== null) {
            $this->assertEquals((float) $high, (float) $resolved->referenceHigh);
        }

        return $resolved;
    }

    private function assertNoRange(ResolvedReferenceRange $resolved): void
    {
        $this->assertSame(ReferenceRangeBasis::None, $resolved->basis);
        $this->assertNull($resolved->range);
        $this->assertNull($resolved->referenceLow);
        $this->assertNull($resolved->referenceHigh);
        $this->assertNull($resolved->criticalLow);
        $this->assertNull($resolved->criticalHigh);
        $this->assertSame(LaboratoryResultParameter::NO_AGE_APPROPRIATE_RANGE, $resolved->label);
    }

    private function resolve(string $code, PatientContext $patient): ResolvedReferenceRange
    {
        return $this->resolver->resolve($this->parameter($code), $patient);
    }

    private function born(int $days = 0, int $months = 0, int $years = 0): PatientContext
    {
        return $this->patient(Gender::Male, $this->asOf->subYears($years)->subMonths($months)->subDays($days));
    }

    private function patient(Gender $gender, ?CarbonImmutable $dob = null, ?int $ageYears = null): PatientContext
    {
        return PatientContext::make($gender, $dob, $ageYears, $this->asOf);
    }

    /**
     * The catalogue parameter with its adult default and every seeded range,
     * active, as the local seed loads them.
     *
     * @param  list<array<string, mixed>>  $extra
     */
    private function parameter(string $code, array $extra = []): LaboratoryTestParameter
    {
        $catalogue = collect(self::data('lab_catalog.json')['tests'])
            ->flatMap(fn (array $test): array => $test['parameters'])
            ->firstWhere('code', $code);

        $this->assertNotNull($catalogue, "{$code} is not in the catalogue data.");

        $rows = collect(self::data('reference_ranges.json')['rows'])
            ->where('parameter_code', $code)
            ->map(fn (array $row): array => [...$row, 'display_order' => $row['range_number']])
            ->values()
            ->all();

        $parameter = $this->bareParameter([...$rows, ...$extra]);
        $parameter->fill([
            'reference_low' => $catalogue['reference_low'],
            'reference_high' => $catalogue['reference_high'],
            'critical_low' => $catalogue['critical_low'],
            'critical_high' => $catalogue['critical_high'],
        ]);

        return $parameter;
    }

    /** @param list<array<string, mixed>> $rows */
    private function bareParameter(array $rows): LaboratoryTestParameter
    {
        $parameter = new LaboratoryTestParameter(['name' => 'Test parameter', 'code' => 'TEST', 'data_type' => 'numeric']);

        $ranges = collect($rows)->map(function (array $row): LaboratoryReferenceRange {
            $range = new LaboratoryReferenceRange;
            $range->forceFill([
                'sex' => $row['sex'],
                'age_category' => $row['age_category'] ?? null,
                'age_min' => $row['age_min'] ?? null,
                'age_min_unit' => $row['age_min_unit'] ?? null,
                'age_max' => $row['age_max'] ?? null,
                'age_max_unit' => $row['age_max_unit'] ?? null,
                'reference_low' => $row['reference_low'] ?? null,
                'reference_high' => $row['reference_high'] ?? null,
                'critical_low' => $row['critical_low'] ?? null,
                'critical_high' => $row['critical_high'] ?? null,
                'abnormal_when' => $row['abnormal_when'] ?? null,
                'is_active' => true,
                'display_order' => $row['display_order'] ?? 0,
            ]);

            return $range;
        });

        $parameter->setRelation('referenceRanges', new Collection($ranges->all()));

        return $parameter;
    }

    /** @return array<string, mixed> */
    private static function data(string $file): array
    {
        return json_decode((string) file_get_contents(database_path("seeders/data/hl7/{$file}")), true, flags: JSON_THROW_ON_ERROR);
    }
}
