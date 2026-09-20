<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RequisitionPriority;
use App\Models\LaboratoryRequisition;
use App\Models\LaboratoryResult;
use App\Models\LaboratoryTest;
use App\Models\Staff;
use App\Services\Laboratory\RequisitionService;
use App\Services\Laboratory\ResultService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesStaffUsers;
use Tests\TestCase;

/**
 * Phase 5: one complete workflow, end to end, with different people at each
 * step — and nobody ever typing or choosing who they are.
 */
class LaboratoryWorkflowIdentityTest extends TestCase
{
    use CreatesStaffUsers, RefreshDatabase;

    #[Test]
    public function a_whole_requisition_runs_without_anyone_naming_themselves(): void
    {
        // Three different people, each with their own professional identity.
        $clinicianStaff = Staff::factory()->create([
            'full_name' => 'Amina Hassan', 'title' => 'Dr',
            'speciality' => 'internal_medicine', 'profession' => 'physician',
        ]);
        $technologistStaff = Staff::factory()->create([
            'full_name' => 'Bekele Tadesse', 'title' => 'Mr',
            'speciality' => 'laboratory_medicine', 'profession' => 'laboratory_scientist',
        ]);
        $pathologistStaff = Staff::factory()->create([
            'full_name' => 'Chaltu Roba', 'title' => 'Dr',
            'speciality' => 'pathology', 'profession' => 'physician',
        ]);

        $clinician = $this->superAdmin($clinicianStaff);
        $technologist = $this->superAdmin($technologistStaff);
        $pathologist = $this->superAdmin($pathologistStaff);

        $test = LaboratoryTest::query()->create([
            'code' => 'FBC', 'name' => 'Full Blood Count',
            'result_type' => 'single', 'is_active' => true,
        ]);

        // --- 1. The clinician raises a requisition ---------------------------
        $requisition = app(RequisitionService::class)->create([
            'patient_identifier' => 'P-5001',
            'patient_name' => 'Workflow Patient',
            'patient_age_years' => 44,
            'requested_date' => now()->toDateString(),
            'priority' => RequisitionPriority::Routine->value,
        ], [['type' => 'test', 'id' => $test->getKey()]], $clinician);

        $this->assertSame('Amina Hassan', $requisition->requested_by_actor_name);
        $this->assertSame('internal_medicine', $requisition->requested_by_actor_speciality);
        $this->assertSame('Dr Amina Hassan', $requisition->requesting_clinician);

        // --- 2. Submitted and collected --------------------------------------
        app(RequisitionService::class)->submit($requisition, $clinician);
        app(RequisitionService::class)->transitionTo(
            $requisition->fresh(),
            \App\Enums\RequisitionStatus::Collected,
            $clinician,
        );

        // --- 3. The technologist enters the result ---------------------------
        $result = LaboratoryResult::query()->where('laboratory_requisition_id', $requisition->getKey())->firstOrFail();

        // performed_by is stamped when the result becomes complete, so every
        // parameter on it gets a value.
        $values = $result->parameters->mapWithKeys(
            fn ($parameter) => [$parameter->getKey() => ['value' => '5.0']],
        )->all();

        app(ResultService::class)->recordValues($result, $values, ['comments' => 'Within range.'], $technologist);

        $result->refresh();

        $this->assertSame('Bekele Tadesse', $result->performed_by_actor_name);
        $this->assertSame('laboratory_medicine', $result->performed_by_actor_speciality);

        // --- 4. The pathologist validates ------------------------------------
        app(ResultService::class)->validate($result->fresh(), $pathologist);
        $result->refresh();

        $this->assertSame('Chaltu Roba', $result->validated_by_actor_name);
        $this->assertSame('pathology', $result->validated_by_actor_speciality);
        $this->assertSame('Dr Chaltu Roba', $result->actorDisplayName('validated_by'));

        // --- 5. Printing -----------------------------------------------------
        app(ResultService::class)->recordPrint($result->fresh(), $technologist);
        $result->refresh();

        $this->assertSame('Bekele Tadesse', $result->printed_by_actor_name);
        $this->assertSame($technologist->getKey(), $result->printed_by);

        // --- 6. Each step names a different person ---------------------------
        $this->assertNotSame($result->performed_by_actor_name, $result->validated_by_actor_name);
        $this->assertSame('Amina Hassan', $requisition->fresh()->requested_by_actor_name);

        // --- 7. The report renders the frozen identities ---------------------
        $html = $this->actingAs($pathologist)
            ->get(route('laboratory.results.print', $result))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Dr Chaltu Roba', $html);
        $this->assertStringContainsString('Pathology', $html);
        $this->assertStringContainsString('Bekele Tadesse', $html);

        // --- 8. Everyone changes speciality; the report does not -------------
        foreach ([$clinicianStaff, $technologistStaff, $pathologistStaff] as $staff) {
            $staff->forceFill(['speciality' => 'dermatology', 'full_name' => 'Renamed Person'])->save();
        }

        $reprint = $this->actingAs($pathologist)
            ->get(route('laboratory.results.print', $result->fresh()))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Dr Chaltu Roba', $reprint);
        $this->assertStringContainsString('Pathology', $reprint);
        $this->assertStringNotContainsString('Renamed Person', $reprint);
        $this->assertStringNotContainsString('Dermatology', $reprint);
    }

    #[Test]
    public function the_requisition_screen_no_longer_offers_a_clinician_field(): void
    {
        $staff = Staff::factory()->create(['full_name' => 'Amina Hassan']);

        $html = $this->actingAs($this->superAdmin($staff))
            ->get(route('laboratory.requisitions.create'))
            ->assertOk()
            ->getContent();

        // No editable control for the actor...
        $this->assertStringNotContainsString('name="requesting_clinician"', $html);

        // ...and the resolved identity is shown instead.
        $this->assertStringContainsString('Automatically identified from your account', $html);
        $this->assertStringContainsString('Amina Hassan', $html);
    }
}
