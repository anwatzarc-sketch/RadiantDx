<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Shape of a reportable value. New shapes can be added here without touching
 * the storage model: values are kept verbatim as text plus an optional parsed
 * numeric copy used for reference range comparison.
 */
enum ParameterDataType: string
{
    case Numeric = 'numeric';
    case Text = 'text';
    case Boolean = 'boolean';
    case PositiveNegative = 'positive_negative';
    case Dropdown = 'dropdown';

    public function label(): string
    {
        return match ($this) {
            self::Numeric => 'Numeric',
            self::Text => 'Text',
            self::Boolean => 'Boolean',
            self::PositiveNegative => 'Positive / Negative',
            self::Dropdown => 'Dropdown',
        };
    }

    /** Whether a reference range and decimal precision are meaningful. */
    public function isNumeric(): bool
    {
        return $this === self::Numeric;
    }

    /** Whether the value is chosen from a fixed set rather than typed freely. */
    public function hasFixedValues(): bool
    {
        return in_array($this, [self::Boolean, self::PositiveNegative, self::Dropdown], true);
    }

    /**
     * Fixed choices for the types that define them in code.
     *
     * @return array<string, string>
     */
    public function builtInValues(): array
    {
        return match ($this) {
            self::Boolean => ['yes' => 'Yes', 'no' => 'No'],
            self::PositiveNegative => ['positive' => 'Positive', 'negative' => 'Negative'],
            default => [],
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
