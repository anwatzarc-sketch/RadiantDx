<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Speciality;
use App\Enums\StaffStatus;
use App\Enums\SubSpeciality;
use App\Models\Role;
use App\Models\User;
use App\Rules\SharedEnumValue;
use App\Rules\ValidSubSpeciality;
use App\Support\Enums\EnumRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesStaffUsers;
use Tests\TestCase;

/**
 * Phase 1 acceptance: the shared controlled-vocabulary layer.
 */
class SharedEnumTest extends TestCase
{
    use CreatesStaffUsers, RefreshDatabase;

    private function signIn(): User
    {
        $user = $this->superAdmin();
        $this->actingAs($user);

        return $user;
    }

    // ---------------------------------------------------------------- catalog

    #[Test]
    public function speciality_and_subspeciality_are_available_centrally(): void
    {
        $this->assertTrue(EnumRegistry::has('Speciality'));
        $this->assertTrue(EnumRegistry::has('SubSpeciality'));
        $this->assertNotEmpty(Speciality::selectable());
        $this->assertNotEmpty(SubSpeciality::selectable());
    }

    #[Test]
    public function every_subspeciality_names_a_real_parent_speciality(): void
    {
        foreach (SubSpeciality::cases() as $case) {
            $parent = $case->parent();

            $this->assertNotNull($parent, "{$case->name} has no parent speciality.");
            $this->assertNotNull(
                Speciality::tryFrom($parent),
                "{$case->name} names parent '{$parent}', which is not a Speciality.",
            );
        }
    }

    #[Test]
    public function machine_values_are_lower_snake_case_matching_house_style(): void
    {
        foreach ([Speciality::cases(), SubSpeciality::cases(), StaffStatus::cases()] as $group) {
            foreach ($group as $case) {
                $this->assertMatchesRegularExpression(
                    '/^[a-z0-9]+(_[a-z0-9]+)*$/',
                    (string) $case->value,
                    "{$case->name} value '{$case->value}' is not lower_snake_case.",
                );
            }
        }
    }

    #[Test]
    public function display_labels_are_never_machine_values(): void
    {
        foreach (Speciality::cases() as $case) {
            $this->assertNotSame($case->value, $case->label());
        }
    }

    // -------------------------------------------------------------------- api

    #[Test]
    public function the_enum_index_lists_published_vocabularies(): void
    {
        $this->signIn();

        $this->get('/enums')
            ->assertOk()
            ->assertJsonFragment(['Speciality'])
            ->assertJsonFragment(['SubSpeciality']);
    }

    #[Test]
    public function a_vocabulary_serves_its_values(): void
    {
        $this->signIn();

        $response = $this->getJson('/enums/Speciality')->assertOk();

        $response->assertJsonPath('name', 'Speciality');
        $response->assertJsonFragment([
            'value' => 'internal_medicine',
            'text' => 'Internal Medicine',
        ]);
    }

    #[Test]
    public function an_unknown_vocabulary_is_a_clean_404(): void
    {
        $this->signIn();

        $this->getJson('/enums/NotARealEnum')->assertNotFound();
    }

    #[Test]
    public function a_vocabulary_name_cannot_be_used_to_reach_an_arbitrary_class(): void
    {
        $this->signIn();

        foreach (['App\Models\User', 'AuditAction', '../../Models/User', 'Illuminate\Support\Str'] as $attempt) {
            $this->getJson('/enums/'.urlencode($attempt))->assertNotFound();
        }
    }

    #[Test]
    public function subspeciality_can_be_narrowed_to_its_parent(): void
    {
        $this->signIn();

        $values = $this->getJson('/enums/SubSpeciality?parent=cardiology')
            ->assertOk()
            ->json('values');

        $this->assertNotEmpty($values);

        foreach ($values as $entry) {
            $this->assertSame('cardiology', $entry['parent']);
        }
    }

    #[Test]
    public function the_enum_api_requires_authentication(): void
    {
        $this->get('/enums/Speciality')->assertRedirect('/login');
    }

    // ------------------------------------------------------------- validation

    #[Test]
    public function a_valid_value_passes(): void
    {
        $validator = Validator::make(
            ['speciality' => 'cardiology'],
            ['speciality' => [new SharedEnumValue('Speciality')]],
        );

        $this->assertTrue($validator->passes());
    }

    #[Test]
    public function an_unknown_value_is_rejected_with_the_exact_message(): void
    {
        $validator = Validator::make(
            ['speciality' => 'wizardry'],
            ['speciality' => [new SharedEnumValue('Speciality')]],
        );

        $this->assertTrue($validator->fails());
        $this->assertSame(
            'Unknown Speciality value',
            $validator->errors()->first('speciality'),
        );
    }

    #[Test]
    public function validation_is_case_sensitive(): void
    {
        foreach (['Cardiology', 'CARDIOLOGY', 'CardioLogy'] as $attempt) {
            $validator = Validator::make(
                ['speciality' => $attempt],
                ['speciality' => [new SharedEnumValue('Speciality')]],
            );

            $this->assertTrue($validator->fails(), "'{$attempt}' should not validate.");
            $this->assertSame('Unknown Speciality value', $validator->errors()->first('speciality'));
        }
    }

    #[Test]
    public function a_display_label_is_not_accepted_as_a_machine_value(): void
    {
        $validator = Validator::make(
            ['speciality' => 'Internal Medicine'],
            ['speciality' => [new SharedEnumValue('Speciality')]],
        );

        $this->assertTrue($validator->fails());
        $this->assertSame('Unknown Speciality value', $validator->errors()->first('speciality'));
    }

    #[Test]
    public function whitespace_is_not_trimmed_into_validity(): void
    {
        foreach ([' cardiology', 'cardiology ', " cardiology\t"] as $attempt) {
            $validator = Validator::make(
                ['speciality' => $attempt],
                ['speciality' => [new SharedEnumValue('Speciality')]],
            );

            $this->assertTrue($validator->fails(), "'{$attempt}' should not validate.");
        }
    }

    #[Test]
    public function the_message_names_the_vocabulary_that_failed(): void
    {
        $validator = Validator::make(
            ['status' => 'nonsense'],
            ['status' => [new SharedEnumValue('StaffStatus')]],
        );

        $this->assertTrue($validator->fails());
        $this->assertSame('Unknown StaffStatus value', $validator->errors()->first('status'));
    }

    // ------------------------------------------------- dependent vocabularies

    #[Test]
    public function a_subspeciality_matching_its_speciality_passes(): void
    {
        $validator = Validator::make(
            ['sub_speciality' => 'echocardiography'],
            ['sub_speciality' => [new ValidSubSpeciality('cardiology')]],
        );

        $this->assertTrue($validator->passes());
    }

    #[Test]
    public function a_subspeciality_from_another_speciality_is_rejected(): void
    {
        $validator = Validator::make(
            ['sub_speciality' => 'echocardiography'],
            ['sub_speciality' => [new ValidSubSpeciality('general_surgery')]],
        );

        $this->assertTrue($validator->fails());
    }

    #[Test]
    public function a_subspeciality_cannot_stand_without_a_speciality(): void
    {
        $validator = Validator::make(
            ['sub_speciality' => 'echocardiography'],
            ['sub_speciality' => [new ValidSubSpeciality(null)]],
        );

        $this->assertTrue($validator->fails());
    }

    // ------------------------------------------------------------- retirement

    #[Test]
    public function retired_values_stay_resolvable_for_historical_display(): void
    {
        // labelFor() must resolve any stored value, including one the catalogue
        // would no longer offer, so historical records keep rendering.
        $this->assertSame('Cardiology', Speciality::labelFor('cardiology'));
        $this->assertNull(Speciality::labelFor(null));

        // An unrecognised stored value degrades to itself rather than throwing.
        $this->assertSame('retired_value', Speciality::labelFor('retired_value'));
    }

    #[Test]
    public function options_offer_only_active_values(): void
    {
        foreach (array_keys(Speciality::options()) as $value) {
            $this->assertTrue(Speciality::tryFrom($value)?->isActive());
        }
    }

    // ------------------------------------------------------------------ order

    #[Test]
    public function vocabularies_are_served_in_display_order(): void
    {
        $orders = array_column(Speciality::catalogue(), 'sortOrder');
        $sorted = $orders;
        sort($sorted);

        $this->assertSame($sorted, $orders);
    }

    #[Test]
    public function searching_card_finds_both_cardiology_specialities(): void
    {
        // The behaviour the specification calls out by name.
        $matches = array_filter(
            Speciality::catalogue(),
            static fn (array $entry): bool => str_contains(strtolower($entry['text']), 'card')
                || in_array('card', array_map('strtolower', $entry['searchKeywords']), true),
        );

        $values = array_column($matches, 'value');

        $this->assertContains('cardiology', $values);
        $this->assertContains('cardiothoracic_surgery', $values);
    }
}
