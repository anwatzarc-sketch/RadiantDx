<?php

declare(strict_types=1);

namespace App\Support\Enums;

use App\Enums\EmploymentType;
use App\Enums\Gender;
use App\Enums\Interpretation;
use App\Enums\LicenseStatus;
use App\Enums\ParameterDataType;
use App\Enums\PhysicianPracticeStatus;
use App\Enums\PositionType;
use App\Enums\Profession;
use App\Enums\QualificationType;
use App\Enums\RegistrationStatus;
use App\Enums\RequisitionPriority;
use App\Enums\RequisitionStatus;
use App\Enums\Speciality;
use App\Enums\StaffStatus;
use App\Enums\SubSpeciality;

/**
 * The one place that maps a public vocabulary name to its implementation.
 *
 * The map is an explicit allow-list. A name arriving from a request is looked
 * up in it and never used to build a class name, so no request can reach a
 * class that was not deliberately published here.
 *
 * Only vocabularies that are safe to serve publicly belong in this map.
 * AuditAction is deliberately excluded: it enumerates internal event types and
 * is of no use to a form.
 */
final class EnumRegistry
{
    /**
     * Published vocabulary name => enum class.
     *
     * Names are the PHP class short name, which is what callers already know.
     *
     * @var array<string, class-string<SharedEnum&\BackedEnum>>
     */
    private const MAP = [
        // Staff identity and professional vocabularies (Phase 1)
        'Speciality' => Speciality::class,
        'SubSpeciality' => SubSpeciality::class,
        'StaffStatus' => StaffStatus::class,
        'EmploymentType' => EmploymentType::class,
        'Profession' => Profession::class,
        'PositionType' => PositionType::class,
        'LicenseStatus' => LicenseStatus::class,
        'RegistrationStatus' => RegistrationStatus::class,
        'PhysicianPracticeStatus' => PhysicianPracticeStatus::class,
        'QualificationType' => QualificationType::class,
    ];

    /**
     * Existing laboratory vocabularies, published read-only.
     *
     * These predate the shared contract and do not implement SharedEnum. They
     * are exposed so a client has one place to resolve every controlled value,
     * but they are served through a compatibility shim rather than being
     * modified — changing enums the laboratory workflows depend on is not worth
     * the risk for a catalogue endpoint.
     *
     * @var array<string, class-string<\BackedEnum>>
     */
    private const LEGACY_MAP = [
        'Gender' => Gender::class,
        'RequisitionStatus' => RequisitionStatus::class,
        'RequisitionPriority' => RequisitionPriority::class,
        'Interpretation' => Interpretation::class,
        'ParameterDataType' => ParameterDataType::class,
    ];

    /** Every published vocabulary name, sorted. */
    public static function names(): array
    {
        $names = array_merge(array_keys(self::MAP), array_keys(self::LEGACY_MAP));
        sort($names);

        return $names;
    }

    public static function has(string $name): bool
    {
        return isset(self::MAP[$name]) || isset(self::LEGACY_MAP[$name]);
    }

    /**
     * The class backing a published name, or null when the name is not
     * published. Callers turn null into a 404; this never throws on bad input.
     *
     * @return class-string<\BackedEnum>|null
     */
    public static function resolve(string $name): ?string
    {
        return self::MAP[$name] ?? self::LEGACY_MAP[$name] ?? null;
    }

    /** Whether the named vocabulary implements the full shared contract. */
    public static function isShared(string $name): bool
    {
        return isset(self::MAP[$name]);
    }

    /**
     * The catalogue for a published name.
     *
     * Vocabularies implementing the shared contract serve their own entries.
     * Legacy ones are adapted here: declaration order becomes sortOrder, every
     * value is active, and there is no parent or keyword data.
     *
     * @return list<array{value: string, text: string, sortOrder: int, active: bool, parent: string|null, searchKeywords: list<string>}>|null
     */
    public static function catalogueFor(string $name): ?array
    {
        $class = self::resolve($name);

        if ($class === null) {
            return null;
        }

        if (self::isShared($name)) {
            /** @var class-string<SharedEnum&\BackedEnum> $class */
            return $class::catalogue();
        }

        $entries = [];

        foreach ($class::cases() as $index => $case) {
            $entries[] = [
                'value' => (string) $case->value,
                'text' => method_exists($case, 'label') ? $case->label() : (string) $case->value,
                'sortOrder' => ($index + 1) * 10,
                'active' => true,
                'parent' => null,
                'searchKeywords' => [],
            ];
        }

        return $entries;
    }

    /**
     * Whether a raw value is a currently selectable member of the vocabulary.
     *
     * Exact, case-sensitive match on the machine value. Display labels are
     * never accepted, and nothing is trimmed or coerced into validity.
     */
    public static function isSelectableValue(string $name, mixed $value): bool
    {
        $class = self::resolve($name);

        if ($class === null || ! is_string($value)) {
            return false;
        }

        $case = $class::tryFrom($value);

        if ($case === null) {
            return false;
        }

        return ! ($case instanceof SharedEnum) || $case->isActive();
    }
}
