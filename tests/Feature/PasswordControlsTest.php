<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesStaffUsers;
use Tests\TestCase;

/**
 * The reveal toggle and the generated-password offer.
 *
 * Two properties are worth pinning down. The eye belongs to the input
 * component rather than to any call site, so the guarantee under test is that
 * *every* password control has one — a later module adding a password field
 * must not be able to ship without it. And the generator is scoped to the
 * screens where one person sets a credential for another; offering it on the
 * profile page would only encourage writing the result down.
 *
 * What the toggle and the generator actually do is Alpine's work and is not
 * asserted here. What is asserted is that the markup Alpine binds to is
 * present, and that the field still arrives as type="password" so a browser
 * without JavaScript masks it as before.
 */
class PasswordControlsTest extends TestCase
{
    use CreatesStaffUsers, RefreshDatabase;

    // ------------------------------------------------------- the reveal eye

    #[Test]
    public function every_password_control_renders_a_reveal_toggle(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('x-data="passwordField"', false)
            ->assertSee("revealed ? 'Hide password' : 'Show password'", false);
    }

    #[Test]
    public function the_confirmation_field_gets_its_own_toggle(): void
    {
        $response = $this->actingAs($this->superAdmin())
            ->get(route('administration.users.create'))
            ->assertOk();

        // One scope per control: password and password_confirmation reveal
        // independently, so a mistyped confirmation can be checked against a
        // password that stays hidden.
        $this->assertSame(2, substr_count($response->getContent(), 'x-data="passwordField"'));
    }

    #[Test]
    public function the_control_is_still_masked_without_javascript(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('type="password"', false);
    }

    #[Test]
    public function the_toggle_is_hidden_until_alpine_can_drive_it(): void
    {
        // x-cloak, or a browser with no JavaScript shows an eye that does
        // nothing when pressed.
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('x-cloak', false);
    }

    // ------------------------------------------------------- the generator

    #[Test]
    public function account_creation_offers_a_generated_password(): void
    {
        $this->actingAs($this->superAdmin())
            ->get(route('administration.users.create'))
            ->assertOk()
            ->assertSee('Generate strong password')
            ->assertSee('passwordGenerator(', false);
    }

    #[Test]
    public function resetting_another_persons_password_offers_one_too(): void
    {
        $user = $this->userWithPermissions([]);

        $this->actingAs($this->superAdmin())
            ->get(route('administration.users.edit', $user))
            ->assertOk()
            ->assertSee('Generate strong password');
    }

    #[Test]
    public function creating_an_account_for_a_staff_member_offers_one_too(): void
    {
        $staff = Staff::factory()->create();

        $this->actingAs($this->superAdmin())
            ->get(route('administration.staff.show', $staff))
            ->assertOk()
            ->assertSee('Generate strong password');
    }

    #[Test]
    public function changing_your_own_password_does_not(): void
    {
        // Deliberate: a password you must remember is not one to generate.
        $this->actingAs($this->superAdmin())
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertDontSee('Generate strong password');
    }

    #[Test]
    public function signing_in_does_not(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('Generate strong password');
    }
}
