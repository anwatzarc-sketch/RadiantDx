<?php

declare(strict_types=1);

namespace Database\Seeders\Concerns;

use RuntimeException;

/**
 * Reads the committed HL7 master data (database/seeders/data/hl7/*.json).
 *
 * The seeders never read the workbook; the JSON is produced from it by
 * database/seeders/data/tools/convert_hl7_workbook.py and reviewed as a diff.
 */
trait ReadsHl7MasterData
{
    /** @return array<string, mixed> */
    protected function hl7Data(string $file): array
    {
        $path = database_path("seeders/data/hl7/{$file}");

        if (! is_file($path)) {
            throw new RuntimeException("HL7 master data file is missing: {$path}");
        }

        return json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    }

    /** @return list<array<string, mixed>> */
    protected function valueSet(string $valueSet): array
    {
        return array_values(array_filter(
            $this->hl7Data('value_sets.json')['rows'],
            fn (array $row): bool => $row['value_set'] === $valueSet,
        ));
    }

    /**
     * The local display for an HL7 code in a value set, e.g. HM in
     * test_category_section is "Hematology". Fails loudly on a code the set
     * does not contain, so a typo in the data cannot seed a blank.
     */
    protected function displayForHl7Code(string $valueSet, string $hl7Code): string
    {
        foreach ($this->valueSet($valueSet) as $row) {
            if ($row['hl7_code'] === $hl7Code) {
                return $row['display'];
            }
        }

        throw new RuntimeException("HL7 code '{$hl7Code}' is not in the value set '{$valueSet}'.");
    }

    protected function placeholderRangesEnabled(): bool
    {
        return (bool) config('seeding.placeholder_ranges');
    }
}
