<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\Interpretation;
use App\Enums\ReferenceRangeBasis;
use App\Enums\RequisitionPriority;
use App\Enums\RequisitionStatus;
use App\Models\AuditLog;
use App\Models\LaboratoryReferenceRange;
use App\Models\LaboratoryRequisition;
use App\Models\LaboratoryResult;
use App\Models\LaboratoryResultParameter;
use App\Models\LaboratoryTest;
use App\Models\LaboratoryTestParameter;
use App\Models\User;
use App\Services\Laboratory\RequisitionService;
use App\Services\Laboratory\ResultService;
use Database\Seeders\Hl7MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesStaffUsers;
use Tests\TestCase;

/**
 * Reference ranges through the real workflow: requisition, collection,
 * result entry, validation, correction of the patient's details, printing.
 * Everything runs on the seeded catalogue with its placeholder ranges active.
 */
class ReferenceRangeWorkflowTest extends TestCase
{
    use CreatesStaffUsers, RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config(['seeding.placeholder_ranges' => true]);
        $this->seed(Hl7MasterDataSeeder::class);

        $this->user = $this->superAdmin();
    }

    #[Test]
    public function a_cbc_for_a_ten_day_old_is_judged_against_neonatal_ranges(): void
    {
        $result = $this->collectedResult('CBC', ['patient_gender' => 'male', 'patient_date_of_birth' => now()->subDays(10)->toDateString()]);

        $this->enter($result, ['WBC' => '7', 'HGB' => '13', 'HCT' => '50', 'PLT' => '100', 'NEUT-PCT' => '50']);
        $rows = $this->rows($result);

        foreach ($rows as $row) {
            $this->assertSame(ReferenceRangeBasis::Stratified, $row->reference_range_basis, "{$row->parameter_code} basis");
            $this->assertNotNull($row->laboratory_reference_range_id, "{$row->parameter_code} range");
        }

        // WBC 7 is normal for an adult (4–11) but low for a neonate (9–30).
        $this->assertSame('9.000000', $rows['WBC']->reference_low);
        $this->assertSame(Interpretation::Low, $rows['WBC']->auto_interpretation);
        $this->assertSame('9–30 (0–28 d)', $rows['WBC']->reference_range);

        $this->assertSame('14.000000', $rows['HGB']->reference_low);
        $this->assertSame(Interpretation::Low, $rows['HGB']->auto_interpretation);

        $this->assertSame(Interpretation::Normal, $rows['HCT']->auto_interpretation);
        $this->assertSame(Interpretation::Low, $rows['PLT']->auto_interpretation);
        $this->assertSame('dob', $rows['PLT']->patient_age_basis->value);
        $this->assertSame(Interpretation::Normal, $rows['NEUT-PCT']->auto_interpretation);
    }

    #[Test]
    public function a_cbc_for_a_thirty_year_old_woman_uses_the_adult_female_ranges(): void
    {
        $result = $this->collectedResult('CBC', ['patient_gender' => 'female', 'patient_date_of_birth' => now()->subYears(30)->toDateString()]);

        $this->enter($result, ['HGB' => '16', 'RBC' => '6', 'WBC' => '7', 'HCT' => '61']);
        $rows = $this->rows($result);

        // HGB 16 is inside the adult default (12–17.5) but above the female range.
        $this->assertSame('12–15.5 (F, 18+ a)', $rows['HGB']->reference_range);
        $this->assertSame(Interpretation::High, $rows['HGB']->auto_interpretation);
        $this->assertSame(Interpretation::High, $rows['RBC']->auto_interpretation);
        $this->assertSame(Interpretation::Normal, $rows['WBC']->auto_interpretation);
        $this->assertSame(Interpretation::CriticalHigh, $rows['HCT']->auto_interpretation);
        $this->assertSame('F', $rows['HGB']->referenceRange->sex->value);
    }

    #[Test]
    public function a_child_with_no_matching_range_gets_none_and_the_report_says_so(): void
    {
        $result = $this->collectedResult('ESR', ['patient_gender' => 'female', 'patient_date_of_birth' => now()->subMonths(6)->toDateString()]);

        $this->enter($result, ['ESR' => '45']);
        $row = $this->rows($result)['ESR'];

        $this->assertSame(ReferenceRangeBasis::None, $row->reference_range_basis);
        $this->assertNull($row->reference_low);
        $this->assertNull($row->reference_high);
        $this->assertNull($row->auto_interpretation);
        $this->assertNull($row->interpretation);

        app(ResultService::class)->validate($result->fresh(), $this->user);

        $this->actingAs($this->user)
            ->get(route('laboratory.results.print', $result))
            ->assertOk()
            ->assertSee('No age-appropriate reference range established.');
    }

    #[Test]
    public function an_adult_with_age_in_years_only_gets_a_footnote_on_the_report(): void
    {
        $result = $this->collectedResult('FBS', ['patient_gender' => 'male', 'patient_age_years' => 40]);

        $this->enter($result, ['FBS' => '130']);
        $row = $this->rows($result)['FBS'];

        $this->assertSame(ReferenceRangeBasis::Stratified, $row->reference_range_basis);
        $this->assertSame('70–100 (18+ a) – age from age_years', $row->reference_range);
        $this->assertSame(Interpretation::High, $row->auto_interpretation);

        app(ResultService::class)->validate($result->fresh(), $this->user);

        $this->actingAs($this->user)
            ->get(route('laboratory.results.print', $result))
            ->assertOk()
            ->assertSee('Range selected from age in years; date of birth not recorded.');
    }

    #[Test]
    public function correcting_the_patients_date_of_birth_re_selects_ranges_on_unvalidated_results_only(): void
    {
        $requisition = $this->collectedRequisition(['CBC', 'FBS'], [
            'patient_gender' => 'female',
            'patient_date_of_birth' => now()->subYears(30)->toDateString(),
        ]);
        [$cbc, $fbs] = $this->resultsFor($requisition, ['CBC', 'FBS']);

        // CBC entered and validated against the adult female ranges.
        $this->enter($cbc, $this->rows($cbc)->map(fn () => '14')->all());
        app(ResultService::class)->validate($cbc->fresh(), $this->user);
        $validatedBefore = $this->rows($cbc)->map->only(['reference_low', 'reference_high', 'reference_range', 'laboratory_reference_range_id', 'auto_interpretation']);

        // FBS entered, not validated: 95 is normal for an adult (70–100).
        $this->enter($fbs, ['FBS' => '95']);
        $this->assertSame(Interpretation::Normal, $this->rows($fbs)['FBS']->interpretation);

        // The birth date was mistyped: the patient is ten days old.
        $requisition->refresh()->update(['patient_date_of_birth' => now()->subDays(10)->toDateString()]);

        // The unvalidated FBS moves to the neonatal range (45–90) and its
        // system-suggested flag follows.
        $fbsRow = $this->rows($fbs)['FBS'];
        $this->assertSame('45.000000', $fbsRow->reference_low);
        $this->assertSame('90.000000', $fbsRow->reference_high);
        $this->assertSame(Interpretation::High, $fbsRow->auto_interpretation);
        $this->assertSame(Interpretation::High, $fbsRow->interpretation);

        // The validated CBC is exactly as it was signed.
        $this->assertEquals($validatedBefore, $this->rows($cbc)->map->only(['reference_low', 'reference_high', 'reference_range', 'laboratory_reference_range_id', 'auto_interpretation']));

        $this->assertTrue(AuditLog::query()
            ->where('action', AuditAction::ResultUpdated)
            ->where('entity_id', $fbs->getKey())
            ->where('description', 'like', '%re-selected%')
            ->exists());
        $this->assertFalse(AuditLog::query()
            ->where('action', AuditAction::ResultUpdated)
            ->where('entity_id', $cbc->getKey())
            ->where('description', 'like', '%re-selected%')
            ->exists());
    }

    #[Test]
    public function a_flag_the_laboratory_chose_by_hand_is_kept_when_the_range_is_re_selected(): void
    {
        $requisition = $this->collectedRequisition(['FBS'], ['patient_gender' => 'male', 'patient_age_years' => 40]);
        [$fbs] = $this->resultsFor($requisition, ['FBS']);

        $row = $this->rows($fbs)['FBS'];
        app(ResultService::class)->recordValues($fbs->fresh(), [$row->getKey() => ['value' => '95', 'interpretation' => 'abnormal']], [], $this->user);

        $requisition->refresh()->update(['patient_age_years' => 0]);

        $row = $this->rows($fbs)['FBS'];
        $this->assertSame('60.000000', $row->reference_low, 'Infant range now applies.');
        $this->assertSame(Interpretation::Abnormal, $row->interpretation, 'The hand-chosen flag stays.');
    }

    #[Test]
    public function a_range_verified_after_the_result_opened_is_used_at_entry(): void
    {
        config(['seeding.placeholder_ranges' => false]);
        LaboratoryReferenceRange::query()->update(['is_active' => false]);
        LaboratoryTestParameter::query()->update(['reference_low' => null, 'reference_high' => null, 'critical_low' => null, 'critical_high' => null]);

        $result = $this->collectedResult('NA', ['patient_gender' => 'male', 'patient_age_years' => 40]);
        $this->assertSame(ReferenceRangeBasis::None, $this->rows($result)['NA']->reference_range_basis);

        // The director verifies the sodium range, then the value is entered.
        $range = LaboratoryReferenceRange::query()
            ->whereHas('parameter', fn ($query) => $query->where('code', 'NA'))
            ->sole();
        $this->actingAs($this->user)
            ->post(route('laboratory.parameters.ranges.verify', [$range->laboratory_test_parameter_id, $range]))
            ->assertSessionHas('success');

        $this->enter($result, ['NA' => '118']);
        $row = $this->rows($result)['NA'];

        $this->assertSame(ReferenceRangeBasis::Stratified, $row->reference_range_basis);
        $this->assertSame(Interpretation::CriticalLow, $row->auto_interpretation);
    }

    // --------------------------------------------------------------- helpers

    /** @param array<string, mixed> $patient */
    private function collectedResult(string $testCode, array $patient): LaboratoryResult
    {
        $requisition = $this->collectedRequisition([$testCode], $patient);

        return $this->resultsFor($requisition, [$testCode])[0];
    }

    /**
     * @param  list<string>  $testCodes
     * @param  array<string, mixed>  $patient
     */
    private function collectedRequisition(array $testCodes, array $patient): LaboratoryRequisition
    {
        $service = app(RequisitionService::class);

        $selections = LaboratoryTest::query()
            ->whereIn('code', $testCodes)
            ->get()
            ->map(fn (LaboratoryTest $test): array => ['type' => 'test', 'id' => $test->getKey()])
            ->all();

        $requisition = $service->create([
            'patient_identifier' => 'P-'.fake()->unique()->numberBetween(1000, 9999),
            'patient_name' => 'Range Patient',
            'requested_date' => now()->toDateString(),
            'priority' => RequisitionPriority::Routine->value,
            ...$patient,
        ], $selections, $this->user);

        $service->submit($requisition, $this->user);
        $service->transitionTo($requisition->fresh(), RequisitionStatus::Collected, $this->user);

        return $requisition->fresh();
    }

    /**
     * @param  list<string>  $testCodes
     * @return list<LaboratoryResult>
     */
    private function resultsFor(LaboratoryRequisition $requisition, array $testCodes): array
    {
        return array_map(
            fn (string $code): LaboratoryResult => LaboratoryResult::query()
                ->where('laboratory_requisition_id', $requisition->getKey())
                ->where('test_code', $code)
                ->sole(),
            $testCodes,
        );
    }

    /** @param array<string, string> $valuesByCode */
    private function enter(LaboratoryResult $result, array $valuesByCode): void
    {
        $rows = $this->rows($result);

        $values = collect($valuesByCode)
            ->mapWithKeys(fn (string $value, string $code): array => [$rows[$code]->getKey() => ['value' => $value]])
            ->all();

        app(ResultService::class)->recordValues($result->fresh(), $values, [], $this->user);
    }

    /** @return Collection<string, LaboratoryResultParameter> */
    private function rows(LaboratoryResult $result): Collection
    {
        return LaboratoryResultParameter::query()
            ->where('laboratory_result_id', $result->getKey())
            ->with('referenceRange')
            ->get()
            ->keyBy('parameter_code');
    }
}
