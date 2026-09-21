<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Gender;
use App\Enums\Profession;
use App\Enums\Speciality;
use App\Enums\StaffStatus;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Staff>
 */
class StaffFactory extends Factory
{
    protected $model = Staff::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            // Factories bypass the service, so the identifier is generated
            // here. Production code never does this: it goes through
            // StaffService, which issues the number inside a transaction.
            'staff_code' => 'STF-'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'full_name' => fake()->name(),
            'gender' => fake()->randomElement(Gender::cases())->value,
            'title' => fake()->randomElement(['Dr', 'Mr', 'Ms', null]),
            'profession' => fake()->randomElement(Profession::cases())->value,
            'speciality' => fake()->randomElement(Speciality::cases())->value,
            'status' => StaffStatus::Active->value,
            'needs_review' => false,
        ];
    }

    public function status(StaffStatus $status): static
    {
        return $this->state(fn (): array => ['status' => $status->value]);
    }

    public function suspended(): static
    {
        return $this->status(StaffStatus::Suspended);
    }

    public function former(): static
    {
        return $this->status(StaffStatus::Former);
    }

    /** A record the backfill or an import left for someone to complete. */
    public function needingReview(): static
    {
        return $this->state(fn (): array => [
            'needs_review' => true,
            'profession' => null,
            'speciality' => null,
        ]);
    }
}
