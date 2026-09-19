<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Lifecycle of a laboratory requisition. Transitions are declared here so the
 * rule lives in one place and can be asserted by services, requests and tests.
 */
enum RequisitionStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Collected = 'collected';
    case Processing = 'processing';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::Collected => 'Collected',
            self::Processing => 'Processing',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Tailwind classes for the status badge. Colour is never the only signal:
     * every badge also renders its label and an icon.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Draft => 'bg-slate-100 text-slate-700 ring-slate-500/20',
            self::Submitted => 'bg-sky-100 text-sky-800 ring-sky-600/20',
            self::Collected => 'bg-indigo-100 text-indigo-800 ring-indigo-600/20',
            self::Processing => 'bg-amber-100 text-amber-900 ring-amber-600/30',
            self::Completed => 'bg-emerald-100 text-emerald-800 ring-emerald-600/20',
            self::Cancelled => 'bg-rose-100 text-rose-800 ring-rose-600/20',
        };
    }

    /**
     * States that may follow this one. An empty list means the record is final.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Submitted, self::Cancelled],
            self::Submitted => [self::Collected, self::Cancelled],
            self::Collected => [self::Processing, self::Cancelled],
            self::Processing => [self::Completed, self::Cancelled],
            self::Completed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /** The requisition is still open for clinical or catalogue edits. */
    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    public function isFinal(): bool
    {
        return $this === self::Completed || $this === self::Cancelled;
    }

    /** Laboratory work may be recorded against the requisition. */
    public function acceptsResults(): bool
    {
        return in_array($this, [self::Collected, self::Processing, self::Completed], true);
    }

    /** @return list<self> The ordered happy path shown by the workflow tracker. */
    public static function workflowPath(): array
    {
        return [self::Draft, self::Submitted, self::Collected, self::Processing, self::Completed];
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
