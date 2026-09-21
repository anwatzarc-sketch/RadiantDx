<?php

declare(strict_types=1);

namespace App\Services\Administration;

use App\Enums\AuditAction;
use App\Models\Department;
use App\Models\Staff;
use App\Models\User;
use App\Rules\SharedEnumValue;
use App\Rules\ValidSubSpeciality;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * CSV import of staff records.
 *
 * Two things this deliberately does not do:
 *
 *  - it never creates system accounts. An import file is a spreadsheet that has
 *    been emailed around; it must not be able to mint sign-in credentials.
 *    Accounts are created one at a time from a staff profile.
 *  - it never accepts a staff identifier from the file. Identifiers are issued
 *    by the server, so a column called `staff_code` in the upload is ignored
 *    rather than honoured.
 *
 * Every run is analysed first and reported back before anything is written, so
 * an administrator sees exactly what would be created, updated and rejected —
 * and why — before committing.
 */
class StaffImporter
{
    /** Columns understood in the upload. Anything else is ignored. */
    public const COLUMNS = [
        'full_name', 'gender', 'date_of_birth', 'phone', 'email', 'address',
        'title', 'profession', 'speciality', 'sub_speciality', 'department',
        'position', 'professional_license', 'license_expiry',
        'employee_id', 'employment_type', 'status', 'joined_on',
    ];

    /** An existing record is matched on this column, when it is supplied. */
    private const MATCH_COLUMN = 'employee_id';

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly StaffService $staff,
    ) {}

    /**
     * Reads and validates the file without writing anything.
     *
     * @return array{create: list<array<string,mixed>>, update: list<array<string,mixed>>, rejected: list<array<string,mixed>>}
     */
    public function analyse(string $path): array
    {
        $rows = $this->readCsv($path);

        $plan = ['create' => [], 'update' => [], 'rejected' => []];

        foreach ($rows as $index => $row) {
            $lineNumber = $index + 2; // header is line 1

            $attributes = $this->mapRow($row);
            $failure = $this->validationFailure($attributes);

            if ($failure !== null) {
                $plan['rejected'][] = [
                    'line' => $lineNumber,
                    'name' => $attributes['full_name'] ?? '(no name)',
                    'reason' => $failure,
                ];

                continue;
            }

            $existing = $this->matchExisting($attributes);

            $entry = [
                'line' => $lineNumber,
                'attributes' => $attributes,
                'existing_id' => $existing?->getKey(),
                'name' => $attributes['full_name'],
                'staff_code' => $existing?->staff_code,
            ];

            $plan[$existing === null ? 'create' : 'update'][] = $entry;
        }

        return $plan;
    }

    /**
     * Applies a previously analysed plan.
     *
     * Re-analysed rather than trusting a plan round-tripped through the
     * browser: the file is the input, not the preview.
     *
     * @return array{created: int, updated: int, rejected: int}
     */
    public function commit(string $path, User $actor): array
    {
        $plan = $this->analyse($path);

        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($plan, $actor, &$created, &$updated): void {
            foreach ($plan['create'] as $entry) {
                $this->staff->create($entry['attributes'], $actor);
                $created++;
            }

            foreach ($plan['update'] as $entry) {
                $staff = Staff::query()->find($entry['existing_id']);

                if ($staff === null) {
                    continue;
                }

                $this->staff->update($staff, $entry['attributes'], $actor);
                $updated++;
            }
        });

        $this->audit->record(
            AuditAction::StaffImported,
            null,
            "Staff import: {$created} created, {$updated} updated, ".count($plan['rejected']).' rejected.',
            [
                'created' => $created,
                'updated' => $updated,
                'rejected' => count($plan['rejected']),
            ],
            $actor,
        );

        return [
            'created' => $created,
            'updated' => $updated,
            'rejected' => count($plan['rejected']),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return string|null the reason for rejection, or null when the row is usable
     */
    private function validationFailure(array $attributes): ?string
    {
        $validator = Validator::make($attributes, [
            'full_name' => ['required', 'string', 'max:255'],
            'gender' => ['nullable', new SharedEnumValue('Gender')],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'email' => ['nullable', 'email', 'max:255'],
            'profession' => ['nullable', new SharedEnumValue('Profession')],
            'speciality' => ['nullable', new SharedEnumValue('Speciality')],
            'sub_speciality' => ['nullable', new ValidSubSpeciality($attributes['speciality'] ?? null)],
            'position' => ['nullable', new SharedEnumValue('PositionType')],
            'employment_type' => ['nullable', new SharedEnumValue('EmploymentType')],
            'status' => ['required', new SharedEnumValue('StaffStatus')],
            'license_expiry' => ['nullable', 'date'],
            'joined_on' => ['nullable', 'date'],
        ]);

        return $validator->fails() ? $validator->errors()->first() : null;
    }

    /**
     * @param  array<string, string>  $row
     * @return array<string, mixed>
     */
    private function mapRow(array $row): array
    {
        $attributes = [];

        foreach (self::COLUMNS as $column) {
            $value = trim((string) ($row[$column] ?? ''));
            $attributes[$column] = $value === '' ? null : $value;
        }

        // A department arrives as a name and is resolved to an existing record.
        // Importing must not quietly create organisational structure.
        $departmentName = $attributes['department'] ?? null;
        unset($attributes['department']);

        $attributes['department_id'] = $departmentName === null
            ? null
            : Department::query()->where('name', $departmentName)->value('id');

        // Absent status means active; an explicitly wrong one is still rejected.
        $attributes['status'] ??= 'active';

        // Imported records are flagged for review so somebody confirms the
        // professional details that a spreadsheet rarely gets right.
        $attributes['needs_review'] = true;

        return $attributes;
    }

    /** @param array<string, mixed> $attributes */
    private function matchExisting(array $attributes): ?Staff
    {
        $key = $attributes[self::MATCH_COLUMN] ?? null;

        if ($key === null || $key === '') {
            return null;
        }

        return Staff::query()->where(self::MATCH_COLUMN, $key)->first();
    }

    /**
     * @return list<array<string, string>>
     */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return [];
        }

        $header = fgetcsv($handle);

        if ($header === false) {
            fclose($handle);

            return [];
        }

        // Tolerate a UTF-8 BOM and inconsistent header casing/spacing; a
        // spreadsheet exported from Excel routinely has both.
        $header = array_map(
            static fn ($column): string => strtolower(str_replace(' ', '_', trim((string) preg_replace('/^\x{FEFF}/u', '', (string) $column)))),
            $header,
        );

        $rows = [];

        while (($line = fgetcsv($handle)) !== false) {
            if ($line === [null] || $line === []) {
                continue;
            }

            $rows[] = array_combine(
                $header,
                array_pad(array_slice($line, 0, count($header)), count($header), ''),
            );
        }

        fclose($handle);

        return $rows;
    }
}
