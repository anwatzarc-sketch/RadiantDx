<?php

declare(strict_types=1);

namespace App\Support\Enums;

/**
 * Contract for a controlled vocabulary that is offered through the shared enum
 * API and the SharedEnumSelect component.
 *
 * This extends, rather than replaces, the convention the application's existing
 * enums already follow: a string-backed case whose `value` is the stable machine
 * value and whose `label()` is the display text. The additions here are the four
 * things a centrally served vocabulary needs and a plain `label()` cannot carry —
 * an explicit ordering, a retirement flag, search synonyms, and a parent for
 * dependent vocabularies such as SubSpeciality.
 *
 * Existing laboratory enums are deliberately left alone. They may adopt this
 * contract later by adding the trait; nothing here requires them to.
 *
 * @see ProvidesSharedEnumMetadata for the default implementations.
 */
interface SharedEnum
{
    /** Display text. Never stored — the backing `value` is what persists. */
    public function label(): string;

    /**
     * Position in a list offered to a user. Lower sorts first.
     *
     * Declaration order is a poor default for a clinical vocabulary that grows
     * over time: a speciality added years later should be able to sit in its
     * clinical group rather than at the end of the list.
     */
    public function sortOrder(): int;

    /**
     * Whether the value may be chosen for NEW records.
     *
     * A retired value stays resolvable so historical records keep displaying
     * correctly — it is simply no longer offered.
     */
    public function isActive(): bool;

    /**
     * Additional terms that should match this value when searching.
     *
     * Label substring matching already covers the obvious cases; this is for
     * synonyms and lay terms, so "heart" finds Cardiology and "kidney" finds
     * Nephrology.
     *
     * @return list<string>
     */
    public function searchKeywords(): array;

    /**
     * The machine value of the parent this value belongs to, for dependent
     * vocabularies, or null for a flat one.
     */
    public function parent(): ?string;
}
