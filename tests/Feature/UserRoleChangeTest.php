<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesStaffUsers;
use Tests\TestCase;

/**
 * Assigning a role to an account that did not have one.
 *
 * This is the ordinary sequence when a role is created after the account that
 * needs it: the account is made with no role, the role is added, and the two
 * are joined afterwards. Nothing covered the update path before, so the types
 * the service is handed on that path were never exercised.
 */
class UserRoleChangeTest extends TestCase
{
    use CreatesStaffUsers, RefreshDatabase;

    private function accountWithoutRole(): User
    {
        $staff = Staff::factory()->create();

        $user = new User([
            'name' => $staff->full_name,
            'email' => 'unassigned@example.test',
            'password' => 'password',
            'role_id' => null,
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $user->staff_id = $staff->getKey();
        $user->save();

        return $user;
    }

    private function role(string $name): Role
    {
        return Role::query()->create([
            'name' => $name,
            'slug' => str($name)->slug()->value(),
            'is_active' => true,
        ]);
    }

    #[Test]
    public function a_role_can_be_assigned_to_an_account_that_had_none(): void
    {
        $subject = $this->accountWithoutRole();
        $nurse = $this->role('Nurse');

        $this->actingAs($this->superAdmin())
            ->put(route('administration.users.update', $subject), [
                'name' => $subject->name,
                'email' => $subject->email,
                'role_id' => (string) $nurse->getKey(),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame($nurse->getKey(), $subject->fresh()->role_id);
    }

    #[Test]
    public function a_role_can_be_changed_to_a_different_one(): void
    {
        $subject = $this->accountWithoutRole();
        $subject->role_id = $this->role('Phlebotomist')->getKey();
        $subject->save();

        $nurse = $this->role('Nurse');

        $this->actingAs($this->superAdmin())
            ->put(route('administration.users.update', $subject), [
                'name' => $subject->name,
                'email' => $subject->email,
                'role_id' => (string) $nurse->getKey(),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame($nurse->getKey(), $subject->fresh()->role_id);
    }

    #[Test]
    public function a_role_can_be_removed(): void
    {
        $subject = $this->accountWithoutRole();
        $subject->role_id = $this->role('Nurse')->getKey();
        $subject->save();

        $this->actingAs($this->superAdmin())
            ->put(route('administration.users.update', $subject), [
                'name' => $subject->name,
                'email' => $subject->email,
                'role_id' => '',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertNull($subject->fresh()->role_id);
    }

    #[Test]
    public function resubmitting_the_same_role_is_not_treated_as_a_change(): void
    {
        // The guard compares the submitted role with the current one before
        // deciding whether a change is happening at all, so the two have to be
        // the same type or an unchanged form counts as a move.
        $subject = $this->accountWithoutRole();
        $nurse = $this->role('Nurse');
        $subject->role_id = $nurse->getKey();
        $subject->save();

        $this->actingAs($this->superAdmin())
            ->put(route('administration.users.update', $subject), [
                'name' => $subject->name,
                'email' => $subject->email,
                'role_id' => (string) $nurse->getKey(),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame($nurse->getKey(), $subject->fresh()->role_id);
    }
}
