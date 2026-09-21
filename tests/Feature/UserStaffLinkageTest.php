<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesStaffUsers;
use Tests\TestCase;

/**
 * Binding a new account to a staff record from the users create form.
 *
 * `users.staff_id` is NOT NULL, so this form could not create anything until it
 * carried the association. The rules it has to respect are the same two the
 * staff-side flow enforces -- the record must be Active, and it must not
 * already have an account -- and they are asserted here at both layers: the
 * list the form offers, and the write that follows.
 *
 * The soft-delete case has its own test because it is the one that fails as a
 * database error rather than a validation message if it is missed: the unique
 * index on staff_id does not know about deleted_at.
 */
class UserStaffLinkageTest extends TestCase
{
    use CreatesStaffUsers, RefreshDatabase;

    private const PASSWORD = 'Str0ng!Passw0rd';

    /** @return array<string, mixed> */
    private function payload(Staff $staff, array $overrides = []): array
    {
        return array_merge([
            'staff_id' => $staff->getKey(),
            'email' => 'new.account@example.test',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
            'is_active' => '1',
        ], $overrides);
    }

    // ------------------------------------------------------------ the list

    #[Test]
    public function the_form_lists_active_staff_without_an_account(): void
    {
        $waiting = Staff::factory()->create();

        $this->actingAs($this->superAdmin())
            ->get(route('administration.users.create'))
            ->assertOk()
            ->assertSee($waiting->staff_code)
            ->assertSee($waiting->full_name);
    }

    #[Test]
    public function staff_who_already_have_an_account_are_not_offered(): void
    {
        $taken = Staff::factory()->create();
        $admin = $this->superAdmin($taken);

        $this->actingAs($admin)
            ->get(route('administration.users.create'))
            ->assertOk()
            ->assertDontSee($taken->staff_code);
    }

    #[Test]
    public function staff_who_are_not_active_are_not_offered(): void
    {
        $suspended = Staff::factory()->suspended()->create();
        $former = Staff::factory()->former()->create();

        $this->actingAs($this->superAdmin())
            ->get(route('administration.users.create'))
            ->assertOk()
            ->assertDontSee($suspended->staff_code)
            ->assertDontSee($former->staff_code);
    }

    #[Test]
    public function staff_whose_account_was_soft_deleted_are_not_offered(): void
    {
        // The row still holds the staff_id, so offering this record would
        // produce a duplicate key error rather than a validation message.
        $staff = Staff::factory()->create();
        $account = $this->superAdmin($staff);
        $account->delete();

        $this->actingAs($this->superAdmin())
            ->get(route('administration.users.create'))
            ->assertOk()
            ->assertDontSee($staff->staff_code);
    }

    #[Test]
    public function the_form_explains_itself_when_nothing_is_eligible(): void
    {
        // The signed-in administrator's own staff record is already taken, so
        // with no other staff there is nothing to offer.
        $this->actingAs($this->superAdmin())
            ->get(route('administration.users.create'))
            ->assertOk()
            ->assertSee('No staff member is waiting for an account.')
            ->assertDontSee('Create user');
    }

    // ----------------------------------------------------------- the write

    #[Test]
    public function creating_an_account_binds_it_to_the_chosen_staff_record(): void
    {
        $staff = Staff::factory()->create();

        $this->actingAs($this->superAdmin())
            ->post(route('administration.users.store'), $this->payload($staff))
            ->assertRedirect();

        $user = User::query()->where('email', 'new.account@example.test')->firstOrFail();

        $this->assertSame($staff->getKey(), $user->staff_id);
        $this->assertTrue($user->must_change_password);
    }

    #[Test]
    public function the_account_name_comes_from_the_staff_record_not_the_form(): void
    {
        $staff = Staff::factory()->create(['full_name' => 'Amina Yusuf']);

        $this->actingAs($this->superAdmin())
            ->post(route('administration.users.store'), $this->payload($staff, [
                'name' => 'Somebody Else Entirely',
            ]))
            ->assertRedirect();

        $this->assertSame(
            'Amina Yusuf',
            User::query()->where('email', 'new.account@example.test')->value('name'),
        );
    }

    #[Test]
    public function a_staff_record_must_be_chosen(): void
    {
        $this->actingAs($this->superAdmin())
            ->post(route('administration.users.store'), [
                'email' => 'new.account@example.test',
                'password' => self::PASSWORD,
                'password_confirmation' => self::PASSWORD,
            ])
            ->assertSessionHasErrors('staff_id');

        $this->assertDatabaseMissing('users', ['email' => 'new.account@example.test']);
    }

    #[Test]
    public function a_suspended_staff_record_is_refused(): void
    {
        $suspended = Staff::factory()->suspended()->create();

        $this->actingAs($this->superAdmin())
            ->post(route('administration.users.store'), $this->payload($suspended))
            ->assertSessionHasErrors('staff_id');

        $this->assertDatabaseMissing('users', ['email' => 'new.account@example.test']);
    }

    #[Test]
    public function a_staff_record_that_already_has_an_account_is_refused(): void
    {
        $taken = Staff::factory()->create();
        $admin = $this->superAdmin($taken);

        $this->actingAs($admin)
            ->post(route('administration.users.store'), $this->payload($taken))
            ->assertSessionHasErrors('staff_id');

        $this->assertDatabaseMissing('users', ['email' => 'new.account@example.test']);
    }

    // ------------------------------------------------------- moving is not

    #[Test]
    public function an_existing_account_cannot_be_moved_to_another_staff_record(): void
    {
        $admin = $this->superAdmin();
        $subject = $this->superAdmin();
        $elsewhere = Staff::factory()->create();

        $this->actingAs($admin)
            ->put(route('administration.users.update', $subject), [
                'staff_id' => $elsewhere->getKey(),
                'name' => $subject->name,
                'email' => $subject->email,
            ])
            ->assertSessionHasErrors('staff_id');

        $this->assertNotSame($elsewhere->getKey(), $subject->fresh()->staff_id);
    }
}
