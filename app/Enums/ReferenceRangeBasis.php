<?php

declare(strict_types=1);

namespace App\Enums;

/** How the reference range on a result row was arrived at. */
enum ReferenceRangeBasis: string
{
    /** A sex- and age-specific range matched the patient. */
    case Stratified = 'stratified';

    /** No stratified range matched; the parameter's adult default was used. */
    case Default = 'default';

    /** Nothing applied, so the value is not auto-flagged. */
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::Stratified => 'Age and sex specific',
            self::Default => 'Adult default',
            self::None => 'No range',
        };
    }
}
