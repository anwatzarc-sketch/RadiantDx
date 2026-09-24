<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\LaboratoryReferenceRange;
use App\Models\LaboratoryTest;
use App\Models\LaboratoryTestParameter;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesStaffUsers;
use Tests\TestCase;

/**
 * Maintaining reference ranges from the parameter screens.
 *
 * Ranges arrive as placeholders. What is pinned here is what makes them
 * trustworthy afterwards: an overlapping range is refused, every way of
 * approving one records who approved it, and each step is audited.
 */
class ReferenceRangeAdminTest extends TestCase
{
    use CreatesStaffUsers, RefreshDatabase;

    private LaboratoryTestParameter $hgb;

    protected function setUp(): void
    {
        parent::setUp();

        $test = LaboratoryTest::query()->create([
            'code' => 'CBC', 'name' => 'Complete Blood Count', 'result_type' => 'parameterised', 'is_active' => true,
        ]);

        $this->hgb = LaboratoryTestParameter::query()->create([
            'laboratory_test_id' => $test->getKey(),
            'code' => 'HGB', 'name' => 'Hemoglobin', 'data_type' => 'numeric', 'unit' => 'g/dL', 'is_active' => true,
        ]);
    }

    // ------------------------------------------------------------- listing

    #[Test]
    public function the_parameter_page_lists_ranges_and_counts_those_awaiting_verification(): void
    {
        $this->placeholder(['sex' => 'F', 'age_min' => 18, 'age_min_unit' => 'a', 'reference_low' => 12, 'reference_high' => 15.5]);
        $this->placeholder(['sex' => 'M', 'age_min' => 18, 'age_min_unit' => 'a', 'reference_low' => 13.5, 'reference_high' => 17.5, 'is_active' => false]);

        $this->actingAs($this->userWithPermissions(['laboratory.parameter.view']))
            ->get(route('laboratory.parameters.show', $this->hgb))
            ->assertOk()
            ->assertSee('Reference ranges')
            ->assertSee('2 ranges awaiting verification')
            ->assertSee('Placeholder')
            ->assertSee('12–15.5')
            // A viewer is shown the ranges, not the controls.
            ->assertDontSee('Verify all for this parameter')
            ->assertDontSee('Add range');
    }

    // ---------------------------------------------------- the form verifies

    #[Test]
    public function saving_a_new_range_through_the_form_verifies_and_activates_it(): void
    {
        $director = $this->director();

        $this->actingAs($director)
            ->post(route('laboratory.parameters.ranges.store', $this->hgb), $this->form([
                'sex' => 'F', 'age_category' => 'adult_all', 'age_min' => 18, 'age_min_unit' => 'a',
                'reference_low' => 12, 'reference_high' => 15.5, 'critical_low' => 7, 'critical_high' => 20,
            ]))
            ->assertRedirect(route('laboratory.parameters.show', $this->hgb))
            ->assertSessionHasNoErrors();

        $range = LaboratoryReferenceRange::query()->sole();

        $this->assertFalse($range->is_placeholder);
        $this->assertTrue($range->is_active);
        $this->assertNotNull($range->verified_at);
        $this->assertSame($director->getKey(), $range->verified_by);
        $this->assertSame($director->staff_id, $range->verified_by_staff_id);
        $this->assertSame('Director Of Laboratory', $range->verified_by_actor_name);

        $this->assertAudited(AuditAction::ReferenceRangeCreated, $range);
        $this->assertAudited(AuditAction::ReferenceRangeVerified, $range);
    }

    #[Test]
    public function editing_a_placeholder_verifies_it_and_editing_again_re_records_the_verifier(): void
    {
        $range = $this->placeholder(['sex' => 'M', 'age_min' => 18, 'age_min_unit' => 'a', 'reference_low' => 13.5, 'reference_high' => 17.5, 'is_active' => false]);

        $first = $this->director();
        $this->actingAs($first)
            ->put(route('laboratory.parameters.ranges.update', [$this->hgb, $range]), $this->form([
                'sex' => 'M', 'age_min' => 18, 'age_min_unit' => 'a', 'reference_low' => 13.2, 'reference_high' => 17.5,
            ]))
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('error');

        $range->refresh();
        $this->assertFalse($range->is_placeholder);
        $this->assertTrue($range->is_active);
        $this->assertSame('13.200000', $range->reference_low);
        $this->assertSame($first->getKey(), $range->verified_by);

        $update = AuditLog::query()->where('action', AuditAction::ReferenceRangeUpdated)->sole();
        $this->assertSame(['from' => '13.500000', 'to' => '13.200000'], $update->metadata['changes']['reference_low']);

        // A second director re-approves: the snapshot is replaced, the first
        // approval stays in the audit trail.
        $second = $this->director('Second Reviewer');
        $this->actingAs($second)
            ->put(route('laboratory.parameters.ranges.update', [$this->hgb, $range]), $this->form([
                'sex' => 'M', 'age_min' => 18, 'age_min_unit' => 'a', 'reference_low' => 13.2, 'reference_high' => 17.5,
                'notes' => 'Reviewed against analyser insert.',
            ]))
            ->assertSessionHasNoErrors();

        $range->refresh();
        $this->assertSame($second->getKey(), $range->verified_by);
        $this->assertSame('Second Reviewer', $range->verified_by_actor_name);
        $this->assertSame(2, AuditLog::query()->where('action', AuditAction::ReferenceRangeVerified)->count());
    }

    // ------------------------------------------------------------- overlaps

    #[Test]
    public function the_form_rejects_a_range_overlapping_an_active_one_of_the_same_sex(): void
    {
        $this->verified(['sex' => 'F', 'age_min' => 18, 'age_min_unit' => 'a', 'reference_low' => 12, 'reference_high' => 15.5]);

        // 65 and over sits inside "18 and over".
        $this->actingAs($this->director())
            ->from(route('laboratory.parameters.ranges.create', $this->hgb))
            ->post(route('laboratory.parameters.ranges.store', $this->hgb), $this->form([
                'sex' => 'F', 'age_min' => 65, 'age_min_unit' => 'a', 'reference_low' => 11, 'reference_high' => 15,
            ]))
            ->assertRedirect(route('laboratory.parameters.ranges.create', $this->hgb))
            ->assertSessionHasErrors(['age_min' => 'These ages overlap the Female range 18+ a (12–15.5). Adjust the ages, or deactivate that range first.']);

        $this->assertSame(1, LaboratoryReferenceRange::query()->count());
    }

    #[Test]
    public function overlap_is_measured_in_days_across_units(): void
    {
        // 0–28 d, then 27 d–1 a: one day of overlap once both are in days.
        $this->verified(['sex' => 'any', 'age_min' => 0, 'age_min_unit' => 'd', 'age_max' => 28, 'age_max_unit' => 'd', 'reference_low' => 14]);

        $this->actingAs($this->director())
            ->post(route('laboratory.parameters.ranges.store', $this->hgb), $this->form([
                'sex' => 'any', 'age_min' => 27, 'age_min_unit' => 'd', 'age_max' => 1, 'age_max_unit' => 'a', 'reference_low' => 9.5,
            ]))
            ->assertSessionHasErrors('age_min');

        // Starting exactly where the first ends is not an overlap.
        $this->actingAs($this->director())
            ->post(route('laboratory.parameters.ranges.store', $this->hgb), $this->form([
                'sex' => 'any', 'age_min' => 28, 'age_min_unit' => 'd', 'age_max' => 1, 'age_max_unit' => 'a', 'reference_low' => 9.5,
            ]))
            ->assertSessionHasNoErrors();
    }

    #[Test]
    public function any_sex_and_sex_specific_ranges_may_cover_the_same_ages(): void
    {
        $this->verified(['sex' => 'any', 'age_min' => 18, 'age_min_unit' => 'a', 'reference_low' => 12]);

        $this->actingAs($this->director())
            ->post(route('laboratory.parameters.ranges.store', $this->hgb), $this->form([
                'sex' => 'M', 'age_min' => 18, 'age_min_unit' => 'a', 'reference_low' => 13.5,
            ]))
            ->assertSessionHasNoErrors();
    }

    #[Test]
    public function an_inactive_range_does_not_block_a_new_one(): void
    {
        $this->placeholder(['sex' => 'F', 'age_min' => 18, 'age_min_unit' => 'a', 'is_active' => false]);

        $this->actingAs($this->director())
            ->post(route('laboratory.parameters.ranges.store', $this->hgb), $this->form([
                'sex' => 'F', 'age_min' => 18, 'age_min_unit' => 'a', 'reference_low' => 12,
            ]))
            ->assertSessionHasNoErrors();
    }

    #[Test]
    public function the_form_checks_units_order_and_critical_placement(): void
    {
        $director = $this->director();
        $store = route('laboratory.parameters.ranges.store', $this->hgb);

        $this->actingAs($director)->post($store, $this->form(['age_min' => 1, 'age_min_unit' => 'y']))
            ->assertSessionHasErrors('age_min_unit');
        $this->actingAs($director)->post($store, $this->form(['age_min' => 1]))
            ->assertSessionHasErrors('age_min_unit');
        $this->actingAs($director)->post($store, $this->form(['reference_low' => 15, 'reference_high' => 12]))
            ->assertSessionHasErrors('reference_high');
        $this->actingAs($director)->post($store, $this->form(['reference_low' => 12, 'reference_high' => 15, 'critical_low' => 13]))
            ->assertSessionHasErrors('critical_low');
        $this->actingAs($director)->post($store, $this->form(['reference_low' => 12, 'reference_high' => 15, 'critical_high' => 14]))
            ->assertSessionHasErrors('critical_high');
        $this->actingAs($director)->post($store, $this->form(['age_min' => 1, 'age_min_unit' => 'a', 'age_max' => 6, 'age_max_unit' => 'mo']))
            ->assertSessionHasErrors('age_max');

        $this->assertSame(0, LaboratoryReferenceRange::query()->count());
    }

    // ---------------------------------------------------------- verification

    #[Test]
    public function verify_approves_a_placeholder_as_it_stands(): void
    {
        $range = $this->placeholder(['sex' => 'F', 'age_min' => 18, 'age_min_unit' => 'a', 'reference_low' => 12, 'is_active' => false]);
        $director = $this->director();

        $this->actingAs($director)
            ->post(route('laboratory.parameters.ranges.verify', [$this->hgb, $range]))
            ->assertSessionHas('success');

        $range->refresh();
        $this->assertFalse($range->is_placeholder);
        $this->assertTrue($range->is_active);
        $this->assertSame($director->getKey(), $range->verified_by);
        $this->assertSame('12.000000', $range->reference_low);
        $this->assertAudited(AuditAction::ReferenceRangeVerified, $range);
    }

    #[Test]
    public function verify_selected_approves_only_the_ticked_ranges(): void
    {
        $neonate = $this->placeholder(['age_min' => 0, 'age_min_unit' => 'd', 'age_max' => 28, 'age_max_unit' => 'd', 'is_active' => false]);
        $infant = $this->placeholder(['age_min' => 28, 'age_min_unit' => 'd', 'age_max' => 1, 'age_max_unit' => 'a', 'is_active' => false]);
        $child = $this->placeholder(['age_min' => 1, 'age_min_unit' => 'a', 'age_max' => 12, 'age_max_unit' => 'a', 'is_active' => false]);

        $this->actingAs($this->director())
            ->post(route('laboratory.parameters.ranges.verify-many', $this->hgb), [
                'scope' => 'selected', 'ranges' => [$neonate->getKey(), $child->getKey()],
            ])
            ->assertSessionHas('success', '2 ranges verified.');

        $this->assertFalse($neonate->refresh()->is_placeholder);
        $this->assertTrue($infant->refresh()->is_placeholder);
        $this->assertFalse($child->refresh()->is_placeholder);
    }

    #[Test]
    public function verify_all_approves_every_placeholder_on_the_parameter_and_no_other(): void
    {
        $this->placeholder(['age_min' => 0, 'age_min_unit' => 'd', 'age_max' => 28, 'age_max_unit' => 'd', 'is_active' => false]);
        $this->placeholder(['age_min' => 28, 'age_min_unit' => 'd', 'age_max' => 1, 'age_max_unit' => 'a', 'is_active' => false]);

        $elsewhere = LaboratoryTestParameter::query()->create([
            'laboratory_test_id' => $this->hgb->laboratory_test_id, 'code' => 'WBC', 'name' => 'WBC', 'data_type' => 'numeric',
        ]);
        $untouched = LaboratoryReferenceRange::query()->create([
            'laboratory_test_parameter_id' => $elsewhere->getKey(), 'sex' => 'any', 'is_placeholder' => true, 'is_active' => false,
        ]);

        $this->actingAs($this->director())
            ->post(route('laboratory.parameters.ranges.verify-many', [$this->hgb, 'scope' => 'all']))
            ->assertSessionHas('success', '2 ranges verified.');

        $this->assertSame(0, $this->hgb->referenceRanges()->placeholder()->count());
        $this->assertTrue($untouched->refresh()->is_placeholder);
        $this->assertSame(2, AuditLog::query()->where('action', AuditAction::ReferenceRangeVerified)->count());
    }

    #[Test]
    public function bulk_verify_is_all_or_nothing_when_the_selection_would_overlap(): void
    {
        $a = $this->placeholder(['sex' => 'F', 'age_min' => 18, 'age_min_unit' => 'a', 'is_active' => false]);
        $b = $this->placeholder(['sex' => 'F', 'age_min' => 65, 'age_min_unit' => 'a', 'is_active' => false]);

        $this->actingAs($this->director())
            ->post(route('laboratory.parameters.ranges.verify-many', [$this->hgb, 'scope' => 'all']))
            ->assertSessionHas('error');

        $this->assertTrue($a->refresh()->is_placeholder);
        $this->assertTrue($b->refresh()->is_placeholder);
        $this->assertSame(0, AuditLog::query()->where('action', AuditAction::ReferenceRangeVerified)->count());
    }

    // ---------------------------------------------------------- permissions

    #[Test]
    public function the_parameter_permissions_guard_every_range_action(): void
    {
        $range = $this->placeholder(['sex' => 'F', 'age_min' => 18, 'age_min_unit' => 'a', 'is_active' => false]);
        $viewer = $this->userWithPermissions(['laboratory.parameter.view']);

        $this->actingAs($viewer)->get(route('laboratory.parameters.ranges.create', $this->hgb))->assertForbidden();
        $this->actingAs($viewer)->post(route('laboratory.parameters.ranges.store', $this->hgb), $this->form())->assertForbidden();
        $this->actingAs($viewer)->post(route('laboratory.parameters.ranges.verify', [$this->hgb, $range]))->assertForbidden();
        $this->actingAs($viewer)->post(route('laboratory.parameters.ranges.verify-many', [$this->hgb, 'scope' => 'all']))->assertForbidden();
        $this->actingAs($viewer)->patch(route('laboratory.parameters.ranges.activate', [$this->hgb, $range]))->assertForbidden();
        $this->actingAs($viewer)->delete(route('laboratory.parameters.ranges.destroy', [$this->hgb, $range]))->assertForbidden();

        $this->assertTrue($range->refresh()->is_placeholder);
    }

    #[Test]
    public function a_range_is_reachable_only_through_its_own_parameter(): void
    {
        $other = LaboratoryTestParameter::query()->create([
            'laboratory_test_id' => $this->hgb->laboratory_test_id, 'code' => 'WBC', 'name' => 'WBC', 'data_type' => 'numeric',
        ]);
        $range = $this->placeholder(['sex' => 'F']);

        $this->actingAs($this->director())
            ->post(route('laboratory.parameters.ranges.verify', [$other, $range]))
            ->assertNotFound();
    }

    #[Test]
    public function activate_deactivate_and_delete_are_audited(): void
    {
        $range = $this->verified(['sex' => 'F', 'age_min' => 18, 'age_min_unit' => 'a']);
        $director = $this->director();

        $this->actingAs($director)->patch(route('laboratory.parameters.ranges.deactivate', [$this->hgb, $range]))->assertSessionHas('success');
        $this->assertFalse($range->refresh()->is_active);

        $this->actingAs($director)->patch(route('laboratory.parameters.ranges.activate', [$this->hgb, $range]))->assertSessionHas('success');
        $this->assertTrue($range->refresh()->is_active);

        $this->actingAs($director)->delete(route('laboratory.parameters.ranges.destroy', [$this->hgb, $range]))->assertRedirect();
        $this->assertSoftDeleted($range);

        $this->assertAudited(AuditAction::ReferenceRangeDeactivated, $range);
        $this->assertAudited(AuditAction::ReferenceRangeActivated, $range);
        $this->assertAudited(AuditAction::ReferenceRangeDeleted, $range);
    }

    // --------------------------------------------------------------- helpers

    private function director(string $name = 'Director Of Laboratory'): User
    {
        return $this->userWithPermissions([
            'laboratory.parameter.view',
            'laboratory.parameter.update',
            'laboratory.parameter.activate',
            'laboratory.parameter.deactivate',
            'laboratory.parameter.delete',
        ], Staff::factory()->create(['full_name' => $name, 'title' => null]));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        return [
            'sex' => 'any',
            'age_category' => '',
            'age_min' => '', 'age_min_unit' => '', 'age_max' => '', 'age_max_unit' => '',
            'reference_low' => '', 'reference_high' => '', 'critical_low' => '', 'critical_high' => '',
            'abnormal_when' => 'outside_range',
            'reference_range_text' => '', 'notes' => '', 'display_order' => '',
            ...$overrides,
        ];
    }

    /** @param array<string, mixed> $attributes */
    private function placeholder(array $attributes): LaboratoryReferenceRange
    {
        return LaboratoryReferenceRange::query()->create([
            'laboratory_test_parameter_id' => $this->hgb->getKey(),
            'sex' => 'any',
            'abnormal_when' => 'outside_range',
            'is_placeholder' => true,
            'is_active' => true,
            ...$attributes,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function verified(array $attributes): LaboratoryReferenceRange
    {
        return $this->placeholder([...$attributes, 'is_placeholder' => false]);
    }

    private function assertAudited(AuditAction $action, LaboratoryReferenceRange $range): void
    {
        $this->assertTrue(
            AuditLog::query()
                ->where('action', $action)
                ->where('entity_type', LaboratoryReferenceRange::class)
                ->where('entity_id', $range->getKey())
                ->exists(),
            "Expected an audit entry {$action->value} for range {$range->getKey()}.",
        );
    }
}
