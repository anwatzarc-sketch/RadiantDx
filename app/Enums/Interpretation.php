<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Flag reported alongside a value. The system can suggest one by evaluating the
 * configured reference range, but the laboratory always remains free to report
 * a different flag: the stored interpretation is what appears on the report.
 */
enum Interpretation: string
{
    case Normal = 'normal';
    case Low = 'low';
    case High = 'high';
    case CriticalLow = 'critical_low';
    case CriticalHigh = 'critical_high';
    case Abnormal = 'abnormal';
    case Positive = 'positive';
    case Negative = 'negative';
    case NotApplicable = 'not_applicable';

    public function label(): string
    {
        return match ($this) {
            self::Normal => 'Normal',
            self::Low => 'Low',
            self::High => 'High',
            self::CriticalLow => 'Critical low',
            self::CriticalHigh => 'Critical high',
            self::Abnormal => 'Abnormal',
            self::Positive => 'Positive',
            self::Negative => 'Negative',
            self::NotApplicable => 'Not applicable',
        };
    }

    /** Short marker printed in the Flag column of the laboratory report. */
    public function reportFlag(): string
    {
        return match ($this) {
            self::Normal => 'N',
            self::Low => 'L',
            self::High => 'H',
            self::CriticalLow => 'LL',
            self::CriticalHigh => 'HH',
            self::Abnormal => 'A',
            self::Positive => 'POS',
            self::Negative => 'NEG',
            self::NotApplicable => '-',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Normal, self::Negative => 'bg-emerald-100 text-emerald-800 ring-emerald-600/20',
            self::Low, self::High, self::Abnormal, self::Positive => 'bg-amber-100 text-amber-900 ring-amber-600/30',
            self::CriticalLow, self::CriticalHigh => 'bg-rose-100 text-rose-800 ring-rose-600/20',
            self::NotApplicable => 'bg-slate-100 text-slate-600 ring-slate-500/20',
        };
    }

    public function isOutsideReference(): bool
    {
        return ! in_array($this, [self::Normal, self::Negative, self::NotApplicable], true);
    }

    public function isCritical(): bool
    {
        return in_array($this, [self::CriticalLow, self::CriticalHigh], true);
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
