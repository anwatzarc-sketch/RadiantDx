<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ParameterDataType;
use App\Models\LaboratoryReferenceRange;
use App\Models\LaboratoryTestParameter;
use Database\Seeders\Concerns\ReadsHl7MasterData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The age- and sex-specific reference ranges, every one a placeholder.
 *
 * Always seeded, so the laboratory director has them to review. Whether they
 * start active depends on config('seeding.placeholder_ranges'): active
 * locally, inactive in production, where the resolver ignores them until a
 * director verifies them.
 *
 * Natural key: parameter, sex, and both age bounds with their units, matched
 * null-safely. On re-seeding:
 *   - a verified range (is_placeholder = false) is never touched: the
 *     laboratory has approved it;
 *   - a placeholder has its values refreshed, but keeps whatever active state
 *     the laboratory gave it;
 *   - a deleted range stays deleted.
 */
class LaboratoryReferenceRangeSeeder extends Seeder
{
    use ReadsHl7MasterData;

    public function run(): void
    {
        $rows = $this->hl7Data('reference_ranges.json')['rows'];
        $active = $this->placeholderRangesEnabled();
        $counts = ['created' => 0, 'refreshed' => 0, 'kept_verified' => 0, 'kept_deleted' => 0];

        DB::transaction(function () use ($rows, $active, &$counts): void {
            $parameters = $this->parameters();

            foreach ($rows as $row) {
                $key = "{$row['test_code']}/{$row['parameter_code']}";
                $parameter = $parameters->get($key)
                    ?? throw new RuntimeException("Reference range #{$row['range_number']}: parameter {$key} is not in the catalogue. Seed the catalogue first.");

                if ($parameter->data_type !== ParameterDataType::Numeric) {
                    throw new RuntimeException("Reference range #{$row['range_number']}: {$key} is not numeric.");
                }

                $existing = $this->find($parameter, $row);
                $values = $this->values($row);

                match (true) {
                    $existing === null => $this->create($parameter, $values, $active, $counts),
                    $existing->trashed() => $counts['kept_deleted']++,
                    ! $existing->is_placeholder => $counts['kept_verified']++,
                    default => $this->refresh($existing, $values, $counts),
                };
            }
        });

        $this->command?->info(sprintf(
            '  Reference ranges: %d in the data; %d created, %d placeholders refreshed, %d verified kept, %d deleted kept.',
            count($rows), $counts['created'], $counts['refreshed'], $counts['kept_verified'], $counts['kept_deleted'],
        ));

        if (! $active && $counts['created'] > 0) {
            $this->command?->warn(
                "  {$counts['created']} placeholder ranges were seeded INACTIVE (SEED_PLACEHOLDER_RANGES is off). "
                .'They are not used for any result until the laboratory director verifies them.'
            );
        }
    }

    /** @return Collection<string, LaboratoryTestParameter> */
    private function parameters(): Collection
    {
        return LaboratoryTestParameter::query()
            ->with(['test', 'referenceRanges' => fn ($query) => $query->withTrashed()])
            ->get()
            ->filter(fn (LaboratoryTestParameter $parameter): bool => $parameter->test !== null)
            ->keyBy(fn (LaboratoryTestParameter $parameter): string => "{$parameter->test->code}/{$parameter->code}");
    }

    /**
     * The existing range with the same sex and age bounds, deleted ones
     * included. Compared in PHP so null bounds match null bounds and 18
     * matches the stored "18.00" on every database driver.
     *
     * @param  array<string, mixed>  $row
     */
    private function find(LaboratoryTestParameter $parameter, array $row): ?LaboratoryReferenceRange
    {
        $same = fn (mixed $stored, mixed $wanted): bool => $stored === null || $wanted === null
            ? $stored === $wanted
            : abs((float) $stored - (float) $wanted) < 0.001;

        return $parameter->referenceRanges->first(
            fn (LaboratoryReferenceRange $range): bool => $range->sex->value === $row['sex']
                && $same($range->age_min, $row['age_min'])
                && $range->age_min_unit?->value === $row['age_min_unit']
                && $same($range->age_max, $row['age_max'])
                && $range->age_max_unit?->value === $row['age_max_unit']
        );
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function values(array $row): array
    {
        return [
            'sex' => $row['sex'],
            'age_category' => $row['age_category'],
            'age_min' => $row['age_min'],
            'age_min_unit' => $row['age_min_unit'],
            'age_max' => $row['age_max'],
            'age_max_unit' => $row['age_max_unit'],
            'reference_low' => $row['reference_low'],
            'reference_high' => $row['reference_high'],
            'critical_low' => $row['critical_low'],
            'critical_high' => $row['critical_high'],
            'reference_range_text' => $row['reference_range_text'],
            'abnormal_when' => $row['abnormal_when'],
            'notes' => $row['notes'],
            'display_order' => $row['range_number'],
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  array<string, int>  $counts
     */
    private function create(LaboratoryTestParameter $parameter, array $values, bool $active, array &$counts): void
    {
        $range = new LaboratoryReferenceRange($values);
        $range->laboratory_test_parameter_id = $parameter->getKey();
        $range->is_placeholder = true;
        $range->is_active = $active;
        $range->save();

        $parameter->referenceRanges->push($range);
        $counts['created']++;
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  array<string, int>  $counts
     */
    private function refresh(LaboratoryReferenceRange $range, array $values, array &$counts): void
    {
        $range->fill($values);

        if ($range->isDirty()) {
            $range->save();
        }

        $counts['refreshed']++;
    }
}
