<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StaffStatus;
use App\Exceptions\StaffIdentityException;
use App\Exceptions\WorkflowViolationException;
use App\Models\Staff;
use App\Models\User;
use App\Services\AuthenticatedStaffResolver;
use App\Support\ActorIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesStaffUsers;
use Tests\TestCase;

/**
 * Phase 2 acceptance: staff records, the staff/user link, and the single
 * authoritative actor resolver.
 */
class StaffIdentityTest extends TestCase
{
    use CreatesStaffUsers, RefreshDatabase;

    // ------------------------------------------------------- staff identifier

    #[Test]
    public function creating_staff_issues_a_sequential_identifier(): void
    {
        $this->actingAs($this->superAdmin());

        foreach (['Amina Hassan', 'Bekele Tadesse'] as $name) {
            $this->post(route('administration.staff.store'), $this->staffPayload(['full_name' => $name]))
                ->assertRedirect();
        }

        $issued = Staff::query()
            ->whereIn('full_name', ['Amina Hassan', 'Bekele Tadesse'])
            ->orderBy('id')
            ->pluck('staff_id');

        $this->assertCount(2, $issued);

        foreach ($issued as $staffId) {
            $this->assertMatchesRegularExpression('/^STF-\d{6}$/', $staffId);
        }

        $this->assertNotSame($issued[0], $issued[1]);
    }

    #[Test]
    public function a_staff_identifier_supplied_in_the_request_is_ignored(): void
    {
        $this->actingAs($this->superAdmin());

        $this->post(route('administration.staff.store'), $this->staffPayload([
            'full_name' => 'Chosen Identifier',
            'staff_id' => 'STF-999999',
        ]))->assertRedirect();

        $staff = Staff::query()->where('full_name', 'Chosen Identifier')->firstOrFail();

        $this->assertNotSame('STF-999999', $staff->staff_id);
        $this->assertDatabaseMissing('staff', ['staff_id' => 'STF-999999']);
    }

    #[Test]
    public function a_staff_identifier_cannot_be_changed_once_issued(): void
    {
        $staff = Staff::factory()->create();
        $original = $staff->staff_id;

        $staff->staff_id = 'STF-000999';

        $this->expectException(WorkflowViolationException::class);

        try {
            $staff->save();
        } finally {
            $this->assertDatabaseHas('staff', ['id' => $staff->getKey(), 'staff_id' => $original]);
        }
    }

    #[Test]
    public function staff_identifiers_are_unique(): void
    {
        $staff = Staff::factory()->create();

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        Staff::factory()->create(['staff_id' => $staff->staff_id]);
    }

    // ------------------------------------------------------------ user ↔ staff

    #[Test]
    public function an_account_resolves_to_its_staff_record(): void
    {
        $staff = Staff::factory()->create(['full_name' => 'Amina Hassan']);
        $user = $this->superAdmin($staff);

        $this->assertTrue($user->staff->is($staff));
        $this->assertTrue($staff->fresh()->user->is($user));
    }

    #[Test]
    public function an_account_cannot_be_moved_to_another_staff_record(): void
    {
        $user = $this->superAdmin();
        $other = Staff::factory()->create();

        $user->staff_id = $other->getKey();

        $this->expectException(WorkflowViolationException::class);
        $user->save();
    }

    #[Test]
    public function staff_id_is_not_mass_assignable_on_a_user(): void
    {
        $this->assertNotContains('staff_id', (new User)->getFillable());
    }

    #[Test]
    public function staff_id_is_not_mass_assignable_on_a_staff_record(): void
    {
        $this->assertNotContains('staff_id', (new Staff)->getFillable());
    }

    // --------------------------------------------------------------- resolver

    #[Test]
    public function the_resolver_returns_the_current_professional_identity(): void
    {
        $staff = Staff::factory()->create([
            'full_name' => 'Amina Hassan',
            'title' => 'Dr',
            'speciality' => 'cardiology',
        ]);

        $identity = $this->resolverFor($this->superAdmin($staff));

        $this->assertSame('Amina Hassan', $identity->name);
        $this->assertSame('Dr', $identity->title);
        $this->assertSame('cardiology', $identity->speciality);
        $this->assertSame('Cardiology', $identity->specialityLabel);
        $this->assertSame($staff->staff_id, $identity->staffNumber);
        $this->assertSame(ActorIdentity::PROVENANCE_AUTHENTICATED, $identity->provenance);
        $this->assertSame('Dr Amina Hassan', $identity->displayName());
    }

    #[Test]
    public function the_resolver_refuses_a_suspended_staff_member(): void
    {
        $user = $this->superAdmin(Staff::factory()->suspended()->create());

        $this->expectException(StaffIdentityException::class);
        $this->resolverFor($user);
    }

    #[Test]
    public function the_resolver_refuses_a_former_staff_member(): void
    {
        $user = $this->superAdmin(Staff::factory()->former()->create());

        $this->expectException(StaffIdentityException::class);
        $this->resolverFor($user);
    }

    #[Test]
    public function the_resolver_never_falls_back_to_the_account_name(): void
    {
        $staff = Staff::factory()->suspended()->create(['full_name' => 'Real Name']);
        $user = $this->superAdmin($staff);
        $user->forceFill(['name' => 'Account Name'])->save();

        $resolver = app(AuthenticatedStaffResolver::class);

        // Rejected outright rather than degraded to users.name.
        $this->assertFalse($resolver->canAct($user));

        try {
            $resolver->resolve($user);
            $this->fail('Expected the resolver to refuse a suspended staff member.');
        } catch (StaffIdentityException $e) {
            $this->assertStringNotContainsString('Account Name', $e->getMessage());
        }
    }

    #[Test]
    public function the_system_actor_is_well_formed_and_never_null(): void
    {
        $system = ActorIdentity::system('scheduler');

        $this->assertTrue($system->isSystem());
        $this->assertSame(ActorIdentity::PROVENANCE_SYSTEM, $system->provenance);
        $this->assertNotSame('', $system->name);
        $this->assertNull($system->staffId);
    }

    #[Test]
    public function console_context_resolves_to_the_system_actor(): void
    {
        $identity = app(AuthenticatedStaffResolver::class)->resolveOrSystem('queue');

        $this->assertTrue($identity->isSystem());
    }

    #[Test]
    public function a_pre_migration_actor_is_never_blank(): void
    {
        $this->assertSame('Unknown — pre-migration', ActorIdentity::preMigration(null)->name);
        $this->assertSame('Unknown — pre-migration', ActorIdentity::preMigration('  ')->name);
        $this->assertSame('Old Name', ActorIdentity::preMigration('Old Name')->name);
    }

    // ------------------------------------------------------- status and access

    #[Test]
    public function only_active_staff_may_work(): void
    {
        $this->assertTrue(StaffStatus::Active->permitsSystemAccess());

        foreach ([StaffStatus::Inactive, StaffStatus::Suspended, StaffStatus::Former] as $status) {
            $this->assertFalse($status->permitsSystemAccess(), "{$status->value} should not permit access.");
        }
    }

    #[Test]
    public function retiring_staff_also_disables_their_account(): void
    {
        $actor = $this->superAdmin();
        $staff = Staff::factory()->create();
        $target = $this->superAdmin($staff);

        $this->assertTrue($target->is_active);

        $this->actingAs($actor)
            ->patch(route('administration.staff.status', $staff), ['status' => StaffStatus::Former->value])
            ->assertRedirect();

        $this->assertSame(StaffStatus::Former, $staff->fresh()->status);
        $this->assertFalse($target->fresh()->is_active, 'Retiring staff must close their account.');
    }

    #[Test]
    public function you_cannot_change_the_status_of_your_own_staff_record(): void
    {
        $staff = Staff::factory()->create();
        $user = $this->superAdmin($staff);

        $this->actingAs($user)
            ->patch(route('administration.staff.status', $staff), ['status' => StaffStatus::Suspended->value])
            ->assertForbidden();
    }

    // ----------------------------------------------------- account association

    #[Test]
    public function an_account_is_created_against_the_staff_record_in_the_url(): void
    {
        $staff = Staff::factory()->create(['full_name' => 'Amina Hassan']);

        $this->actingAs($this->superAdmin())
            ->post(route('administration.staff.account.store', $staff), [
                'email' => 'amina@example.test',
                'password' => 'Str0ng!Passw0rd',
                'password_confirmation' => 'Str0ng!Passw0rd',
                'is_active' => true,
            ])->assertRedirect();

        $user = User::query()->where('email', 'amina@example.test')->firstOrFail();

        $this->assertSame($staff->getKey(), $user->staff_id);
        $this->assertSame('Amina Hassan', $user->name);
        $this->assertTrue($user->must_change_password);
    }

    #[Test]
    public function a_staff_id_in_the_account_payload_cannot_redirect_the_association(): void
    {
        $intended = Staff::factory()->create(['full_name' => 'Intended Person']);
        $victim = Staff::factory()->create(['full_name' => 'Someone Else']);

        $this->actingAs($this->superAdmin())
            ->post(route('administration.staff.account.store', $intended), [
                'email' => 'spoof@example.test',
                'password' => 'Str0ng!Passw0rd',
                'password_confirmation' => 'Str0ng!Passw0rd',
                // The attack: name a different staff record in the body.
                'staff_id' => $victim->getKey(),
                'name' => 'Someone Else',
            ])->assertRedirect();

        $user = User::query()->where('email', 'spoof@example.test')->firstOrFail();

        $this->assertSame($intended->getKey(), $user->staff_id, 'The URL must decide the association.');
        $this->assertNotSame($victim->getKey(), $user->staff_id);
        $this->assertSame('Intended Person', $user->name);
    }

    #[Test]
    public function a_staff_record_cannot_have_two_accounts(): void
    {
        $staff = Staff::factory()->create();
        $this->superAdmin($staff);

        $this->actingAs($this->superAdmin())
            ->post(route('administration.staff.account.store', $staff), [
                'email' => 'second@example.test',
                'password' => 'Str0ng!Passw0rd',
                'password_confirmation' => 'Str0ng!Passw0rd',
            ])->assertSessionHas('error');

        $this->assertDatabaseMissing('users', ['email' => 'second@example.test']);
    }

    #[Test]
    public function a_suspended_staff_member_cannot_be_given_an_account(): void
    {
        $staff = Staff::factory()->suspended()->create();

        $this->actingAs($this->superAdmin())
            ->post(route('administration.staff.account.store', $staff), [
                'email' => 'suspended@example.test',
                'password' => 'Str0ng!Passw0rd',
                'password_confirmation' => 'Str0ng!Passw0rd',
            ])->assertSessionHas('error');

        $this->assertDatabaseMissing('users', ['email' => 'suspended@example.test']);
    }

    // ------------------------------------------------------------ name cascade

    #[Test]
    public function correcting_a_staff_name_reaches_the_laboratory_records(): void
    {
        $staff = Staff::factory()->create(['full_name' => 'Wrong Name']);
        $user = $this->superAdmin($staff);

        // A requisition names its actor through the account, as every
        // laboratory screen and the printed report do.
        $requisition = new \App\Models\LaboratoryRequisition([
            'patient_identifier' => 'P-1',
            'patient_name' => 'Test Patient',
            'requested_date' => now()->toDateString(),
            'priority' => 'routine',
            'status' => 'submitted',
        ]);
        $requisition->requisition_number = 'REQ-CASCADE-1';
        $requisition->created_by = $user->getKey();
        $requisition->save();

        $this->assertSame('Wrong Name', $requisition->fresh()->createdBy->name);

        $this->actingAs($this->superAdmin())
            ->put(route('administration.staff.update', $staff), $this->staffPayload([
                'full_name' => 'Correct Name',
            ]))->assertRedirect();

        $this->assertSame('Correct Name', $staff->fresh()->full_name);
        $this->assertSame('Correct Name', $user->fresh()->name);
        $this->assertSame(
            'Correct Name',
            $requisition->fresh()->createdBy->name,
            'The laboratory record must resolve to the corrected name.',
        );
    }

    #[Test]
    public function a_name_cascade_is_audited(): void
    {
        $staff = Staff::factory()->create(['full_name' => 'Wrong Name']);
        $this->superAdmin($staff);

        $this->actingAs($this->superAdmin())
            ->put(route('administration.staff.update', $staff), $this->staffPayload([
                'full_name' => 'Correct Name',
            ]))->assertRedirect();

        $cascade = \App\Models\AuditLog::query()
            ->where('entity_type', Staff::class)
            ->where('entity_id', $staff->getKey())
            ->get()
            ->first(fn ($entry) => ($entry->metadata['cascade'] ?? null) === 'account_name');

        $this->assertNotNull($cascade, 'The cascade should leave an audit entry.');
        $this->assertSame('Wrong Name', $cascade->metadata['from']);
        $this->assertSame('Correct Name', $cascade->metadata['to']);
    }

    #[Test]
    public function a_staff_record_without_an_account_cascades_nothing(): void
    {
        $staff = Staff::factory()->create(['full_name' => 'No Account']);

        $this->actingAs($this->superAdmin())
            ->put(route('administration.staff.update', $staff), $this->staffPayload([
                'full_name' => 'Still No Account',
            ]))->assertRedirect();

        $this->assertSame('Still No Account', $staff->fresh()->full_name);
        $this->assertNull($staff->fresh()->user);
    }

    #[Test]
    public function reseeding_does_not_overwrite_a_corrected_administrator_name(): void
    {
        // The seeder is idempotent and re-asserts configuration on every run.
        // The name must not be part of that, or correcting it in Staff
        // Management would be silently undone by the next deploy.
        $this->seed(\Database\Seeders\SuperAdminSeeder::class);

        $email = mb_strtolower(trim((string) config('laboratory.super_admin.email')));
        $user = User::query()->where('email', $email)->firstOrFail();

        $user->staff->forceFill(['full_name' => 'Corrected Name'])->save();
        $user->forceFill(['name' => 'Corrected Name'])->save();

        $this->seed(\Database\Seeders\SuperAdminSeeder::class);

        $this->assertSame('Corrected Name', $user->fresh()->name);
        $this->assertSame('Corrected Name', $user->fresh()->staff->full_name);
    }

    // -------------------------------------------------------------- deletion

    #[Test]
    public function staff_with_laboratory_history_cannot_be_deleted(): void
    {
        $staff = Staff::factory()->create();
        $user = $this->superAdmin($staff);

        // A requisition is enough to establish history: created_by is one of
        // the actor columns hasLaboratoryHistory() checks, and it needs no
        // result/item chain to exist.
        $requisition = new \App\Models\LaboratoryRequisition([
            'patient_identifier' => 'P-1',
            'patient_name' => 'Test Patient',
            'requested_date' => now()->toDateString(),
            'priority' => 'routine',
            'status' => 'completed',
        ]);
        $requisition->requisition_number = 'REQ-TEST-00001';
        // Actor columns are not mass assignable, exactly as in production.
        $requisition->created_by = $user->getKey();
        $requisition->save();

        $this->assertTrue($staff->fresh()->hasLaboratoryHistory());

        $this->actingAs($this->superAdmin())
            ->delete(route('administration.staff.destroy', $staff))
            ->assertForbidden();

        $this->assertDatabaseHas('staff', ['id' => $staff->getKey(), 'deleted_at' => null]);
    }

    // ----------------------------------------------------------- permissions

    #[Test]
    public function staff_screens_require_their_permissions(): void
    {
        $staff = Staff::factory()->create();
        $nobody = $this->userWithPermissions(['dashboard.view']);

        $this->actingAs($nobody)->get(route('administration.staff.index'))->assertForbidden();
        $this->actingAs($nobody)->get(route('administration.staff.create'))->assertForbidden();
        $this->actingAs($nobody)->get(route('administration.staff.show', $staff))->assertForbidden();
        $this->actingAs($nobody)->get(route('administration.staff.export'))->assertForbidden();
        $this->actingAs($nobody)->get(route('administration.staff.import.create'))->assertForbidden();
    }

    #[Test]
    public function view_permission_alone_does_not_allow_editing(): void
    {
        $staff = Staff::factory()->create();
        $viewer = $this->userWithPermissions(['staff.view']);

        $this->actingAs($viewer)->get(route('administration.staff.index'))->assertOk();
        $this->actingAs($viewer)->get(route('administration.staff.edit', $staff))->assertForbidden();
        $this->actingAs($viewer)
            ->put(route('administration.staff.update', $staff), $this->staffPayload())
            ->assertForbidden();
    }

    // ---------------------------------------------------------------- listing

    #[Test]
    public function the_staff_list_can_be_searched_and_filtered(): void
    {
        Staff::factory()->create(['full_name' => 'Amina Hassan', 'profession' => 'physician']);
        Staff::factory()->create(['full_name' => 'Bekele Tadesse', 'profession' => 'nurse']);

        $this->actingAs($this->superAdmin());

        $this->get(route('administration.staff.index', ['search' => 'Amina']))
            ->assertOk()
            ->assertSee('Amina Hassan')
            ->assertDontSee('Bekele Tadesse');

        $this->get(route('administration.staff.index', ['profession' => 'nurse']))
            ->assertOk()
            ->assertSee('Bekele Tadesse')
            ->assertDontSee('Amina Hassan');
    }

    #[Test]
    public function the_export_streams_the_filtered_set(): void
    {
        Staff::factory()->create(['full_name' => 'Amina Hassan']);
        Staff::factory()->create(['full_name' => 'Bekele Tadesse']);

        $response = $this->actingAs($this->superAdmin())
            ->get(route('administration.staff.export', ['search' => 'Amina']));

        $response->assertOk();

        $csv = $response->streamedContent();

        $this->assertStringContainsString('Amina Hassan', $csv);
        $this->assertStringNotContainsString('Bekele Tadesse', $csv);
    }

    // ------------------------------------------------------------------ audit

    #[Test]
    public function staff_events_capture_the_actor_snapshot(): void
    {
        $actorStaff = Staff::factory()->create([
            'full_name' => 'Auditing Admin',
            'title' => 'Dr',
            'speciality' => 'pathology',
        ]);

        $this->actingAs($this->superAdmin($actorStaff))
            ->post(route('administration.staff.store'), $this->staffPayload(['full_name' => 'New Person']))
            ->assertRedirect();

        $entry = \App\Models\AuditLog::query()->latest('id')->firstOrFail();

        $this->assertSame($actorStaff->getKey(), $entry->actor_staff_id);
        $this->assertSame('Auditing Admin', $entry->user_name);
        $this->assertSame('Dr', $entry->actor_title);
        $this->assertSame('pathology', $entry->actor_speciality);
        $this->assertSame(ActorIdentity::PROVENANCE_AUTHENTICATED, $entry->actor_provenance);
    }

    // ------------------------------------------------------------------ helper

    /** @param array<string, mixed> $overrides */
    private function staffPayload(array $overrides = []): array
    {
        return array_merge([
            'full_name' => 'Test Person',
            'status' => StaffStatus::Active->value,
        ], $overrides);
    }

    private function resolverFor(User $user): ActorIdentity
    {
        return app(AuthenticatedStaffResolver::class)->resolve($user);
    }
}
