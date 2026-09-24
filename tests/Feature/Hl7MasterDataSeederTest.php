<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CodeSetValue;
use App\Models\Department;
use App\Models\LaboratoryPanel;
use App\Models\LaboratoryReferenceRange;
use App\Models\LaboratoryTest;
use App\Models\LaboratoryTestParameter;
use App\Models\LaboratoryTestParameterOption;
use Database\Seeders\Hl7MasterDataSeeder;
use Database\Seeders\LaboratoryPanelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * The HL7 master-data seed is run against production and may be run again,
 * so what is pinned here is that a second run changes nothing it should not.
 */
class Hl7MasterDataSeederTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function seeding_twice_loads_the_expected_rows_once(): void
    {
        config(['seeding.placeholder_ranges' => true]);

        $this->seed(Hl7MasterDataSeeder::class);
        $this->seed(Hl7MasterDataSeeder::class);

        $this->assertSame(228, CodeSetValue::query()->count());
        $this->assertSame(10, Department::query()->count());
        $this->assertSame(32, LaboratoryTest::query()->count());
        $this->assertSame(61, LaboratoryTestParameter::query()->count());
        $this->assertSame(109, LaboratoryReferenceRange::query()->count());
        $this->assertSame(7, LaboratoryPanel::query()->count());
        $this->assertSame(36, DB::table('laboratory_panel_tests')->count());

        $this->assertNoDuplicates('code_set_values', ['value_set', 'code']);
        $this->assertNoDuplicates('laboratory_test_parameters', ['laboratory_test_id', 'code']);
        $this->assertNoDuplicates('laboratory_test_parameter_options', ['laboratory_test_parameter_id', 'value']);
        $this->assertNoDuplicates('laboratory_reference_ranges', [
            'laboratory_test_parameter_id', 'sex', 'age_min', 'age_min_unit', 'age_max', 'age_max_unit',
        ]);

        // Seeding bypasses the services, so it leaves no audit trail.
        $this->assertSame(0, AuditLog::query()->count());
    }

    #[Test]
    public function every_test_is_parameterised_and_single_tests_carry_their_own_code(): void
    {
        $this->seed(Hl7MasterDataSeeder::class);

        $this->assertSame(0, LaboratoryTest::query()->where('result_type', '!=', 'parameterised')->count());

        foreach (LaboratoryTest::query()->whereNotIn('code', ['CBC', 'UA', 'STOOL'])->with('parameters')->get() as $test) {
            $this->assertSame([$test->code], $test->parameters->pluck('code')->all(), "{$test->code} should have one parameter of its own code.");
        }

        $hbsag = LaboratoryTestParameter::query()->where('code', 'HBSAG')->with('options')->sole();
        $this->assertSame('dropdown', $hbsag->data_type->value);
        $this->assertSame(['positive', 'negative'], $hbsag->options->pluck('value')->all());
        $this->assertSame('POS', $hbsag->options->first()->hl7_flag);
        $this->assertSame('10828004', $hbsag->options->first()->snomed_code);

        $neutrophils = LaboratoryTestParameter::query()->where('code', 'NEUT-PCT')->sole();
        $this->assertSame('Neutrophils %', $neutrophils->name);
        $this->assertSame('770-8', $neutrophils->loinc_code);

        $test = LaboratoryTest::query()->where('code', 'CBC')->sole();
        $this->assertSame('Hematology', $test->category);
        $this->assertSame('Whole blood', $test->specimen_type);
        $this->assertSame('HM', $test->hl7_section_code);
        $this->assertSame('×10³/µL', LaboratoryTestParameter::query()->where('code', 'WBC')->value('unit'));

        $this->assertNull(LaboratoryTest::query()->where('code', 'HIV')->value('loinc_code'));
        $this->assertSame('24325-3', LaboratoryPanel::query()->where('code', 'LFT')->value('loinc_code'));
        $this->assertNull(LaboratoryPanel::query()->where('code', 'RFT')->value('loinc_code'));
        $this->assertSame(
            'Calculated (Friedewald) – enter manually.',
            LaboratoryTestParameter::query()->where('code', 'LDL')->value('description'),
        );
    }

    #[Test]
    public function a_verified_range_survives_a_re_seed_and_a_placeholder_keeps_its_active_state(): void
    {
        config(['seeding.placeholder_ranges' => true]);
        $this->seed(Hl7MasterDataSeeder::class);

        $hgb = LaboratoryTestParameter::query()->where('code', 'HGB')->sole();
        $verified = $hgb->referenceRanges()->where('sex', 'M')->where('age_category', 'adult_all')->sole();
        $verified->forceFill(['reference_low' => 13.0, 'is_placeholder' => false])->save();

        $deactivated = $hgb->referenceRanges()->where('age_category', 'neonate')->sole();
        $deactivated->forceFill(['is_active' => false])->save();

        $this->seed(Hl7MasterDataSeeder::class);

        $this->assertSame('13.000000', $verified->refresh()->reference_low);
        $this->assertFalse($verified->is_placeholder);
        $this->assertFalse($deactivated->refresh()->is_active);
        $this->assertTrue($deactivated->is_placeholder);
    }

    #[Test]
    public function the_laboratorys_own_edits_survive_a_re_seed_while_hl7_codes_are_refreshed(): void
    {
        $this->seed(Hl7MasterDataSeeder::class);

        $test = LaboratoryTest::query()->where('code', 'CBC')->sole();
        $test->update(['name' => 'Full Blood Count', 'loinc_code' => null]);

        $hgb = LaboratoryTestParameter::query()->where('code', 'HGB')->sole();
        $hgb->update(['reference_low' => 11.5, 'unit' => 'g/dl']);

        LaboratoryPanel::query()->where('code', 'LYTES')->sole()->delete();

        $this->seed(Hl7MasterDataSeeder::class);

        $test->refresh();
        $this->assertSame('Full Blood Count', $test->name);
        $this->assertSame('58410-2', $test->loinc_code);

        $hgb->refresh();
        $this->assertSame('11.500000', $hgb->reference_low);
        $this->assertSame('g/dl', $hgb->unit);

        $this->assertSoftDeleted('laboratory_panels', ['code' => 'LYTES']);
        $this->assertSame(6, LaboratoryPanel::query()->count());
    }

    #[Test]
    public function in_production_mode_ranges_are_seeded_inactive_and_parameters_get_no_adult_default(): void
    {
        config(['seeding.placeholder_ranges' => false]);

        $this->seed(Hl7MasterDataSeeder::class);

        $this->assertSame(109, LaboratoryReferenceRange::query()->count());
        $this->assertSame(0, LaboratoryReferenceRange::query()->where('is_active', true)->count());
        $this->assertSame(109, LaboratoryReferenceRange::query()->where('is_placeholder', true)->count());

        $this->assertSame(0, LaboratoryTestParameter::query()
            ->where(fn ($query) => $query->whereNotNull('reference_low')->orWhereNotNull('reference_high')
                ->orWhereNotNull('critical_low')->orWhereNotNull('critical_high'))
            ->count());
    }

    #[Test]
    public function the_default_follows_app_env(): void
    {
        $this->assertTrue((bool) config('seeding.placeholder_ranges'), 'On outside production.');
    }

    #[Test]
    public function a_panel_member_missing_from_the_catalogue_stops_the_seed(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Panel members not in the catalogue: LFT → ALB/');

        $this->seed(LaboratoryPanelSeeder::class);
    }

    #[Test]
    public function seeding_does_not_touch_roles_permissions_or_users(): void
    {
        $before = [
            DB::table('roles')->count(),
            DB::table('permissions')->count(),
            DB::table('users')->count(),
            DB::table('staff')->count(),
        ];

        $this->seed(Hl7MasterDataSeeder::class);

        $this->assertSame($before, [
            DB::table('roles')->count(),
            DB::table('permissions')->count(),
            DB::table('users')->count(),
            DB::table('staff')->count(),
        ]);
        $this->assertSame(0, LaboratoryTestParameterOption::query()->whereNull('laboratory_test_parameter_id')->count());
    }

    /** @param list<string> $columns */
    private function assertNoDuplicates(string $table, array $columns): void
    {
        $duplicates = DB::table($table)
            ->select($columns)
            ->when(in_array('deleted_at', DB::getSchemaBuilder()->getColumnListing($table), true), fn ($query) => $query->whereNull('deleted_at'))
            ->groupBy($columns)
            ->havingRaw('count(*) > 1')
            ->get();

        $this->assertCount(0, $duplicates, "{$table} has duplicate rows on ".implode(', ', $columns).'.');
    }
}
