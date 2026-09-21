<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\LicenseStatus;
use App\Enums\RegistrationStatus;
use App\Enums\StaffStatus;
use App\Http\Requests\Profile\UpdateOwnStaffProfileRequest;
use App\Models\Staff;
use App\Models\StaffQualification;
use App\Services\Administration\LicenseStatusDeriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesStaffUsers;
use Tests\TestCase;

/**
 * Phase 3 acceptance: physician profile, licensing, qualifications and the
 * self-service boundary on My Profile.
 */
class PhysicianProfileTest extends TestCase
{
    use CreatesStaffUsers, RefreshDatabase;

    // ------------------------------------------------- physician is not a 2nd identity

    #[Test]
    public function a_physician_profile_lives_on_the_staff_record(): void
    {
        $staff = Staff::factory()->create(['profession' => 'physician']);

        $this->actingAs($this->superAdmin())
            ->put(route('administration.physicians.update', $staff), $this->licencePayload([
                'professional_license' => 'MD-12345',
            ]))->assertRedirect();

        $this->assertSame('MD-12345', $staff->fresh()->professional_license);

        // No second identity table anywhere.
        $this->assertFalse(Schema::hasTable('physicians'));
        $this->assertFalse(Schema::hasTable('doctors'));
    }

    #[Test]
    public function the_directory_lists_only_professions_that_require_a_licence(): void
    {
        Staff::factory()->create(['full_name' => 'Doctor Person', 'profession' => 'physician']);
        Staff::factory()->create(['full_name' => 'Porter Person', 'profession' => 'support_staff']);

        $this->actingAs($this->superAdmin())
            ->get(route('administration.physicians.index'))
            ->assertOk()
            ->assertSee('Doctor Person')
            ->assertDontSee('Porter Person');
    }

    // -------------------------------------------------------------- licensing

    #[Test]
    public function an_expired_licence_is_derived_from_its_date(): void
    {
        $staff = Staff::factory()->create([
            'profession' => 'physician',
            'professional_license' => 'MD-1',
            'license_expiry' => Carbon::now()->subDay(),
            'license_status' => LicenseStatus::Active->value,
        ]);

        $this->assertSame(LicenseStatus::Expired, app(LicenseStatusDeriver::class)->derive($staff));
    }

    #[Test]
    public function a_licence_inside_the_configured_window_is_expiring_soon(): void
    {
        config(['laboratory.licensing.expiring_soon_days' => 30]);

        $staff = Staff::factory()->create([
            'professional_license' => 'MD-1',
            'license_expiry' => Carbon::now()->addDays(10),
            'license_status' => LicenseStatus::Active->value,
        ]);

        $this->assertSame(LicenseStatus::ExpiringSoon, app(LicenseStatusDeriver::class)->derive($staff));

        // Outside the window it is simply active.
        $staff->license_expiry = Carbon::now()->addDays(90);
        $this->assertSame(LicenseStatus::Active, app(LicenseStatusDeriver::class)->derive($staff));
    }

    #[Test]
    public function the_expiring_soon_window_is_configuration_not_a_magic_number(): void
    {
        $staff = Staff::factory()->create([
            'professional_license' => 'MD-1',
            'license_expiry' => Carbon::now()->addDays(45),
            'license_status' => LicenseStatus::Active->value,
        ]);

        config(['laboratory.licensing.expiring_soon_days' => 10]);
        $this->assertSame(LicenseStatus::Active, app(LicenseStatusDeriver::class)->derive($staff));

        config(['laboratory.licensing.expiring_soon_days' => 90]);
        $this->assertSame(LicenseStatus::ExpiringSoon, app(LicenseStatusDeriver::class)->derive($staff));
    }

    #[Test]
    public function a_suspended_licence_is_never_overwritten_by_expiry_derivation(): void
    {
        // The rule that matters: a regulator's decision outranks a date.
        foreach ([LicenseStatus::Suspended, LicenseStatus::Revoked] as $asserted) {
            $staff = Staff::factory()->create([
                'professional_license' => 'MD-1',
                // An expiry far in the future would otherwise derive "Active".
                'license_expiry' => Carbon::now()->addYears(5),
                'license_status' => $asserted->value,
            ]);

            $this->assertSame(
                $asserted,
                app(LicenseStatusDeriver::class)->derive($staff),
                "{$asserted->value} must survive derivation.",
            );

            $this->assertFalse(app(LicenseStatusDeriver::class)->apply($staff));
        }
    }

    #[Test]
    public function saving_the_profile_does_not_clear_a_manually_asserted_status(): void
    {
        $staff = Staff::factory()->create(['profession' => 'physician']);

        $this->actingAs($this->superAdmin())
            ->put(route('administration.physicians.update', $staff), $this->licencePayload([
                'professional_license' => 'MD-9',
                'license_expiry' => Carbon::now()->addYears(3)->toDateString(),
                'license_status' => LicenseStatus::Revoked->value,
            ]))->assertRedirect();

        $this->assertSame(LicenseStatus::Revoked, $staff->fresh()->license_status);
    }

    #[Test]
    public function a_licence_with_no_number_is_not_provided(): void
    {
        $staff = Staff::factory()->create([
            'professional_license' => null,
            'license_status' => LicenseStatus::Active->value,
        ]);

        $this->assertSame(LicenseStatus::NotProvided, app(LicenseStatusDeriver::class)->derive($staff));
    }

    #[Test]
    public function an_expiry_before_the_issue_date_is_rejected(): void
    {
        $staff = Staff::factory()->create(['profession' => 'physician']);

        $this->actingAs($this->superAdmin())
            ->put(route('administration.physicians.update', $staff), $this->licencePayload([
                'license_issued_on' => '2026-06-01',
                'license_expiry' => '2026-01-01',
            ]))->assertSessionHasErrors('license_expiry');
    }

    // --------------------------------------------------------- qualifications

    #[Test]
    public function qualifications_can_be_added_and_removed(): void
    {
        $staff = Staff::factory()->create(['profession' => 'physician']);
        $admin = $this->superAdmin();

        $this->actingAs($admin)
            ->post(route('administration.physicians.qualifications.store', $staff), [
                'type' => 'medical_degree',
                'institution' => 'Addis Ababa University',
                'field' => 'Medicine',
                'awarded_year' => 2014,
            ])->assertRedirect();

        $qualification = StaffQualification::query()->firstOrFail();

        $this->assertSame($staff->getKey(), $qualification->staff_id);
        $this->assertStringContainsString('Addis Ababa University', $qualification->summary());

        $this->actingAs($admin)
            ->delete(route('administration.physicians.qualifications.destroy', [$staff, $qualification]))
            ->assertRedirect();

        $this->assertDatabaseCount('staff_qualifications', 0);
    }

    #[Test]
    public function a_qualification_cannot_be_removed_through_another_staff_record(): void
    {
        $owner = Staff::factory()->create();
        $other = Staff::factory()->create();

        $qualification = new StaffQualification(['type' => 'diploma', 'institution' => 'Somewhere']);
        $qualification->staff_id = $owner->getKey();
        $qualification->save();

        $this->actingAs($this->superAdmin())
            ->delete(route('administration.physicians.qualifications.destroy', [$other, $qualification]))
            ->assertNotFound();

        $this->assertDatabaseCount('staff_qualifications', 1);
    }

    #[Test]
    public function qualification_management_is_permission_gated(): void
    {
        $staff = Staff::factory()->create();
        $viewer = $this->userWithPermissions(['staff.view']);

        $this->actingAs($viewer)
            ->post(route('administration.physicians.qualifications.store', $staff), [
                'type' => 'diploma',
                'institution' => 'Somewhere',
            ])->assertForbidden();
    }

    #[Test]
    public function physician_licensing_is_permission_gated(): void
    {
        $staff = Staff::factory()->create();
        $viewer = $this->userWithPermissions(['staff.view']);

        $this->actingAs($viewer)
            ->get(route('administration.physicians.edit', $staff))
            ->assertForbidden();

        $this->actingAs($viewer)
            ->put(route('administration.physicians.update', $staff), $this->licencePayload())
            ->assertForbidden();
    }

    // -------------------------------------------------------- self service

    #[Test]
    public function you_can_update_your_own_contact_details(): void
    {
        $staff = Staff::factory()->create();
        $user = $this->userWithPermissions(['dashboard.view'], $staff);

        $this->actingAs($user)
            ->put(route('profile.staff.update'), [
                'phone' => '+251900000000',
                'professional_bio' => 'Consultant haematologist.',
            ])->assertRedirect();

        $staff->refresh();

        $this->assertSame('+251900000000', $staff->phone);
        $this->assertSame('Consultant haematologist.', $staff->professional_bio);
    }

    /**
     * The headline self-service security test: a crafted post naming every
     * administrative field must change none of them.
     */
    #[Test]
    public function administrative_fields_cannot_be_changed_through_self_service(): void
    {
        $staff = Staff::factory()->create([
            'profession' => 'phlebotomist',
            'speciality' => 'general_practice',
            'position' => 'officer',
            'employee_id' => 'EMP-1',
            'status' => StaffStatus::Active->value,
            'license_status' => LicenseStatus::NotProvided->value,
        ]);

        $original = $staff->only([
            'staff_code', 'full_name', 'profession', 'speciality', 'position',
            'department_id', 'employee_id', 'status', 'license_status',
        ]);

        $user = $this->userWithPermissions(['dashboard.view'], $staff);

        $this->actingAs($user)
            ->put(route('profile.staff.update'), [
                // The one legitimate field.
                'phone' => '+251911111111',

                // Everything a crafted request might try.
                'staff_code' => 'STF-999999',
                'full_name' => 'Promoted Person',
                'profession' => 'physician',
                'speciality' => 'cardiology',
                'sub_speciality' => 'echocardiography',
                'position' => 'head_of_department',
                'department_id' => 999,
                'employee_id' => 'EMP-HACKED',
                'status' => StaffStatus::Suspended->value,
                'license_status' => LicenseStatus::Active->value,
                'registration_status' => RegistrationStatus::Registered->value,
                'needs_review' => false,
                'supervisor_id' => 1,
            ])->assertRedirect();

        $staff->refresh();

        // The legitimate change landed.
        $this->assertSame('+251911111111', $staff->phone);

        // Nothing else moved.
        foreach ($original as $field => $value) {
            $this->assertSame(
                $value instanceof \BackedEnum ? $value->value : $value,
                $staff->{$field} instanceof \BackedEnum ? $staff->{$field}->value : $staff->{$field},
                "{$field} must not be changeable through self service.",
            );
        }
    }

    #[Test]
    public function the_self_editable_allow_list_holds_only_personal_fields(): void
    {
        $allowed = UpdateOwnStaffProfileRequest::SELF_EDITABLE;

        foreach (['staff_code', 'full_name', 'profession', 'speciality', 'status',
            'department_id', 'position', 'employee_id', 'license_status', 'needs_review'] as $administrative) {
            $this->assertNotContains($administrative, $allowed);
        }
    }

    #[Test]
    public function the_profile_screen_shows_the_resolved_identity(): void
    {
        $staff = Staff::factory()->create([
            'full_name' => 'Amina Hassan',
            'title' => 'Dr',
            'speciality' => 'cardiology',
        ]);

        $this->actingAs($this->userWithPermissions(['dashboard.view'], $staff))
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Dr Amina Hassan')
            ->assertSee('Cardiology')
            ->assertSee($staff->staff_code);
    }

    // ------------------------------------------------------------------ helper

    /** @param array<string, mixed> $overrides */
    private function licencePayload(array $overrides = []): array
    {
        return array_merge([
            'license_status' => LicenseStatus::NotProvided->value,
            'registration_status' => RegistrationStatus::NotRegistered->value,
        ], $overrides);
    }
}
