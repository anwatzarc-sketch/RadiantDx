<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RequisitionPriority;
use App\Exceptions\StaffIdentityException;
use App\Exceptions\WorkflowViolationException;
use App\Http\Requests\Concerns\ClientActorFields;
use App\Models\LaboratoryRequisition;
use App\Models\LaboratoryResult;
use App\Models\Staff;
use App\Models\User;
use App\Services\Laboratory\RequisitionService;
use App\Support\ActorIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesStaffUsers;
use Tests\TestCase;

/**
 * Phase 4 acceptance: the server decides who acted, and what it recorded never
 * changes afterwards.
 */
class ActorIdentityTest extends TestCase
{
    use CreatesStaffUsers, RefreshDatabase;

    // ------------------------------------------------------- actor spoofing

    /**
     * The headline security test.
     *
     * Every field a client might use to nominate somebody else is sent at once,
     * naming a real second staff record. The requisition must still be recorded
     * against the signed-in person, and the impostor must appear nowhere.
     */
    #[Test]
    public function no_client_field_can_redirect_the_recorded_actor(): void
    {
        $mine = Staff::factory()->create(['full_name' => 'Real Requestor', 'title' => 'Dr']);
        $impostor = Staff::factory()->create(['full_name' => 'Impostor Person']);
        $impostorUser = $this->superAdmin($impostor);

        $me = $this->superAdmin($mine);

        $spoof = [];
        foreach (ClientActorFields::all() as $field) {
            // Plausible values of both shapes: an id and a name.
            $spoof[$field] = str_contains($field, 'name')
                ? 'Impostor Person'
                : $impostor->getKey();
        }

        $this->actingAs($me)->post(route('laboratory.requisitions.store'), array_merge([
            'patient_identifier' => 'P-900',
            'patient_name' => 'Spoof Patient',
            'patient_age_years' => 40,
            'requested_date' => now()->toDateString(),
            'priority' => RequisitionPriority::Routine->value,
            'action' => 'draft',
            'investigations' => [],
        ], $spoof))->assertRedirect();

        $requisition = LaboratoryRequisition::query()->where('patient_identifier', 'P-900')->firstOrFail();

        // Recorded as the signed-in person.
        $this->assertSame($mine->getKey(), $requisition->requested_by_staff_id);
        $this->assertSame('Real Requestor', $requisition->requested_by_actor_name);
        $this->assertSame($me->getKey(), $requisition->created_by);
        $this->assertSame('Dr Real Requestor', $requisition->requesting_clinician);

        // The impostor appears nowhere on the row.
        $row = (array) $requisition->getAttributes();

        foreach ($row as $column => $value) {
            $this->assertNotSame('Impostor Person', $value, "{$column} carries the impostor's name.");
        }

        $this->assertNotSame($impostor->getKey(), $requisition->requested_by_staff_id);
        $this->assertNotSame($impostorUser->getKey(), $requisition->created_by);
    }

    #[Test]
    public function the_requesting_clinician_is_no_longer_accepted_from_the_request(): void
    {
        $staff = Staff::factory()->create(['full_name' => 'Actual Person']);

        $this->actingAs($this->superAdmin($staff))
            ->post(route('laboratory.requisitions.store'), [
                'patient_identifier' => 'P-901',
                'patient_name' => 'Patient',
                'patient_age_years' => 40,
                'requested_date' => now()->toDateString(),
                'priority' => RequisitionPriority::Routine->value,
                'action' => 'draft',
                'investigations' => [],
                'requesting_clinician' => 'Dr Somebody Else',
            ])->assertRedirect();

        $requisition = LaboratoryRequisition::query()->where('patient_identifier', 'P-901')->firstOrFail();

        // Resolved from the session, so it names the signed-in person (with
        // their title) and not the value that was posted.
        $this->assertStringContainsString('Actual Person', (string) $requisition->requesting_clinician);
        $this->assertStringNotContainsString('Somebody Else', (string) $requisition->requesting_clinician);
        $this->assertSame('Actual Person', $requisition->requested_by_actor_name);
    }

    #[Test]
    public function every_client_actor_field_is_stripped_before_validation(): void
    {
        // The list is the contract; assert the ones the specification names.
        $required = [
            'user_id', 'staff_id', 'requestor_name', 'requestor_staff_id',
            'entered_by', 'entered_by_staff_id', 'reviewed_by', 'reviewed_by_staff_id',
            'approved_by', 'approved_by_staff_id', 'validated_by', 'validated_by_staff_id',
            'collected_by', 'collected_by_staff_id', 'printed_by', 'printed_by_staff_id',
            'released_by', 'released_by_staff_id', 'modified_by', 'modified_by_staff_id',
        ];

        foreach ($required as $field) {
            $this->assertContains($field, ClientActorFields::all());
        }
    }

    // --------------------------------------------------- historical snapshot

    /**
     * The scenario the specification spells out: a speciality change must not
     * rewrite a report that has already been issued.
     */
    #[Test]
    public function changing_a_speciality_does_not_rewrite_an_issued_report(): void
    {
        $staff = Staff::factory()->create([
            'full_name' => 'Amina Hassan',
            'title' => 'Dr',
            'speciality' => 'cardiology',
        ]);

        $requisition = $this->requisitionFor($this->superAdmin($staff));

        $this->assertSame('cardiology', $requisition->requested_by_actor_speciality);
        $this->assertSame('Dr Amina Hassan', $requisition->actorDisplayName('requested_by'));

        // Later: she moves to neurology and her name is corrected.
        $staff->forceFill(['speciality' => 'neurology', 'full_name' => 'Amina Hassan-Bekele'])->save();

        $requisition->refresh();

        $this->assertSame('cardiology', $requisition->requested_by_actor_speciality);
        $this->assertSame('Amina Hassan', $requisition->requested_by_actor_name);
        $this->assertSame('Cardiology', $requisition->actorSpecialityLabel('requested_by'));

        // The staff record itself has of course moved on.
        $this->assertSame('neurology', $staff->fresh()->speciality->value);
    }

    #[Test]
    public function a_name_correction_does_not_reach_an_existing_snapshot(): void
    {
        $staff = Staff::factory()->create(['full_name' => 'Original Name']);
        $user = $this->superAdmin($staff);
        $requisition = $this->requisitionFor($user);

        // Through the service, which cascades the name to the account.
        $this->actingAs($this->superAdmin())
            ->put(route('administration.staff.update', $staff), [
                'full_name' => 'Corrected Name',
                'status' => 'active',
            ])->assertRedirect();

        $this->assertSame('Corrected Name', $user->fresh()->name);
        $this->assertSame(
            'Original Name',
            $requisition->fresh()->requested_by_actor_name,
            'A snapshot is a statement about a moment and must not be rewritten.',
        );
    }

    // ------------------------------------------------------- immutability

    #[Test]
    public function a_recorded_actor_cannot_be_edited(): void
    {
        $requisition = $this->requisitionFor($this->superAdmin());

        $requisition->requested_by_actor_name = 'Somebody Else';

        $this->expectException(WorkflowViolationException::class);
        $requisition->save();
    }

    #[Test]
    public function a_recorded_actor_cannot_be_repointed_to_another_staff_record(): void
    {
        $requisition = $this->requisitionFor($this->superAdmin());
        $other = Staff::factory()->create();

        $requisition->requested_by_staff_id = $other->getKey();

        $this->expectException(WorkflowViolationException::class);
        $requisition->save();
    }

    #[Test]
    public function an_actor_cannot_be_recorded_twice_for_the_same_role(): void
    {
        $requisition = $this->requisitionFor($this->superAdmin());

        $this->expectException(WorkflowViolationException::class);
        $requisition->recordActor('requested_by', ActorIdentity::system());
    }

    #[Test]
    public function snapshot_columns_are_not_mass_assignable(): void
    {
        foreach ([new LaboratoryRequisition, new LaboratoryResult] as $model) {
            foreach ($model->getFillable() as $field) {
                $this->assertDoesNotMatchRegularExpression(
                    '/_actor_(name|title|speciality|provenance)$|_by_staff_id$/',
                    $field,
                    get_class($model)." exposes {$field} to mass assignment.",
                );
            }
        }
    }

    #[Test]
    public function an_unknown_role_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new LaboratoryRequisition)->recordActor('approved_by', ActorIdentity::system());
    }

    // ------------------------------------------------------- suspended staff

    #[Test]
    public function suspended_staff_cannot_perform_laboratory_work(): void
    {
        $staff = Staff::factory()->create();
        $user = $this->superAdmin($staff);

        // Suspended after signing in — the check must be at the action, not
        // only at the door.
        $staff->forceFill(['status' => 'suspended'])->save();

        $this->expectException(StaffIdentityException::class);

        app(RequisitionService::class)->create([
            'patient_identifier' => 'P-902',
            'patient_name' => 'Patient',
            'patient_age_years' => 40,
            'requested_date' => now()->toDateString(),
            'priority' => RequisitionPriority::Routine->value,
        ], [], $user->fresh());
    }

    // ---------------------------------------------------------- system actor

    #[Test]
    public function work_without_a_session_records_the_system_actor(): void
    {
        $identity = app(\App\Services\AuthenticatedStaffResolver::class)->resolveOrSystem('scheduler');

        $this->assertTrue($identity->isSystem());
        $this->assertSame(ActorIdentity::PROVENANCE_SYSTEM, $identity->provenance);

        // A snapshot built from it is well formed rather than null.
        $columns = $identity->snapshotColumns('validated_by');

        $this->assertNotNull($columns['validated_by_actor_name']);
        $this->assertSame('system', $columns['validated_by_actor_provenance']);
    }

    // ------------------------------------------------------------------ helper

    private function requisitionFor(User $user): LaboratoryRequisition
    {
        return app(RequisitionService::class)->create([
            'patient_identifier' => 'P-'.fake()->unique()->numberBetween(1000, 9999),
            'patient_name' => 'Test Patient',
            'patient_age_years' => 40,
            'requested_date' => now()->toDateString(),
            'priority' => RequisitionPriority::Routine->value,
        ], [], $user);
    }
}
