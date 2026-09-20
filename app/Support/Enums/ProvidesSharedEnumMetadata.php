<?php

declare(strict_types=1);

namespace App\Support\Enums;

/**
 * Default implementations for {@see SharedEnum}, plus the query helpers the
 * enum API and the SharedEnumSelect component are built on.
 *
 * A vocabulary that is flat, fully active and needs no synonyms only has to
 * implement label(); everything else here has a workable default.
 */
trait ProvidesSharedEnumMetadata
{
    /** Declaration order, spaced so values can be inserted between later. */
    public function sortOrder(): int
    {
        foreach (array_values(self::cases()) as $index => $case) {
            if ($case === $this) {
                return ($index + 1) * 10;
            }
        }

        return PHP_INT_MAX;
    }

    public function isActive(): bool
    {
        return true;
    }

    /** @return list<string> */
    public function searchKeywords(): array
    {
        return [];
    }

    public function parent(): ?string
    {
        return null;
    }

    /**
     * Cases in display order.
     *
     * @return list<static>
     */
    public static function sorted(): array
    {
        $cases = self::cases();

        usort($cases, static fn (self $a, self $b): int => $a->sortOrder() <=> $b->sortOrder());

        return array_values($cases);
    }

    /**
     * Values selectable for new records, in display order.
     *
     * @return list<static>
     */
    public static function selectable(?string $parent = null): array
    {
        return array_values(array_filter(
            self::sorted(),
            static fn (self $case): bool => $case->isActive()
                && ($parent === null || $case->parent() === $parent),
        ));
    }

    /**
     * value => label, matching the shape the application's existing enums use
     * for Blade select options.
     *
     * Only active values are offered, because this feeds selection. Use
     * {@see labelFor()} to render a value that may since have been retired.
     *
     * @return array<string, string>
     */
    public static function options(?string $parent = null): array
    {
        $options = [];

        foreach (self::selectable($parent) as $case) {
            $options[(string) $case->value] = $case->label();
        }

        return $options;
    }

    /**
     * Display text for a stored value, including retired ones.
     *
     * Historical records must keep rendering correctly after a value is
     * retired, so an unknown value degrades to itself rather than throwing.
     */
    public static function labelFor(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::tryFrom($value)?->label() ?? $value;
    }

    /**
     * The serialised form served by the shared enum API.
     *
     * @return array{value: string, text: string, sortOrder: int, active: bool, parent: string|null, searchKeywords: list<string>}
     */
    public function toCatalogueEntry(): array
    {
        return [
            'value' => (string) $this->value,
            'text' => $this->label(),
            'sortOrder' => $this->sortOrder(),
            'active' => $this->isActive(),
            'parent' => $this->parent(),
            'searchKeywords' => $this->searchKeywords(),
        ];
    }

    /**
     * The whole vocabulary, in display order, including retired values so a
     * client can resolve historical data.
     *
     * @return list<array{value: string, text: string, sortOrder: int, active: bool, parent: string|null, searchKeywords: list<string>}>
     */
    public static function catalogue(): array
    {
        return array_map(
            static fn (self $case): array => $case->toCatalogueEntry(),
            self::sorted(),
        );
    }
}
