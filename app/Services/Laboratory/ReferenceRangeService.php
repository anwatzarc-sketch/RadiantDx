<?php

declare(strict_types=1);

namespace App\Services\Laboratory;

use App\Enums\AuditAction;
use App\Enums\ParameterDataType;
use App\Exceptions\WorkflowViolationException;
use App\Models\LaboratoryReferenceRange;
use App\Models\LaboratoryTestParameter;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\AuthenticatedStaffResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Maintenance of a parameter's reference ranges by the laboratory.
 *
 * Every range arrives from the seed as a placeholder: a textbook value no one
 * here has approved. Saving a range through the form, or verifying it,
 * is the laboratory director approving it, so both clear the placeholder,
 * make the range active and record who approved it and when.
 *
 * Two active ranges for the same parameter and sex may not cover the same
 * age. An any-sex range may sit under a sex-specific one: that is how a more
 * specific range is expressed, and the resolver prefers it.
 */
class ReferenceRangeService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly AuthenticatedStaffResolver $identity,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function create(LaboratoryTestParameter $parameter, array $attributes, User $actor): LaboratoryReferenceRange
    {
        $this->assertNumeric($parameter);

        return DB::transaction(function () use ($parameter, $attributes, $actor): LaboratoryReferenceRange {
            $range = new LaboratoryReferenceRange($attributes);
            $range->laboratory_test_parameter_id = $parameter->getKey();
            $range->display_order = $attributes['display_order']
                ?? (int) $parameter->referenceRanges()->withTrashed()->max('display_order') + 1;

            $this->assertNoActiveOverlap($range);
            $this->markVerified($range, $actor);

            $this->audit->record(
                AuditAction::ReferenceRangeCreated,
                $range,
                "Reference range {$range->resultLabel()} added to {$parameter->name}.",
                ['parameter' => $parameter->code],
                $actor,
            );
            $this->recordVerified($range, $parameter, $actor, 'form');

            return $range;
        });
    }

    /**
     * Saving through the form is an approval of what was saved, so it also
     * verifies the range, even when nothing but the notes changed.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(LaboratoryReferenceRange $range, array $attributes, User $actor): LaboratoryReferenceRange
    {
        return DB::transaction(function () use ($range, $attributes, $actor): LaboratoryReferenceRange {
            $range->fill($attributes);
            $changes = $this->describeChanges($range);

            $this->assertNoActiveOverlap($range);
            $this->markVerified($range, $actor);

            $this->audit->record(
                AuditAction::ReferenceRangeUpdated,
                $range,
                "Reference range {$range->resultLabel()} updated on {$range->parameter->name}.",
                ['parameter' => $range->parameter->code, 'changes' => $changes],
                $actor,
            );
            $this->recordVerified($range, $range->parameter, $actor, 'form');

            return $range;
        });
    }

    public function verify(LaboratoryReferenceRange $range, User $actor): LaboratoryReferenceRange
    {
        if (! $range->is_placeholder) {
            throw WorkflowViolationException::because('This range has already been verified.');
        }

        return DB::transaction(function () use ($range, $actor): LaboratoryReferenceRange {
            $this->assertNoActiveOverlap($range);
            $this->markVerified($range, $actor);
            $this->recordVerified($range, $range->parameter, $actor, 'single');

            return $range;
        });
    }

    /**
     * Verifies several placeholder ranges of one parameter at once: the ones
     * named, or every placeholder when $ids is null.
     *
     * All or nothing. Verifying activates, so the whole selection is checked
     * for overlaps against the ranges already active and against itself
     * before any row is written.
     *
     * @param  list<int>|null  $ids
     * @return int number of ranges verified
     */
    public function verifyMany(LaboratoryTestParameter $parameter, ?array $ids, User $actor): int
    {
        $selected = $parameter->referenceRanges()
            ->placeholder()
            ->when($ids !== null, fn ($query) => $query->whereKey($ids))
            ->get();

        if ($selected->isEmpty()) {
            throw WorkflowViolationException::because('There are no placeholder ranges to verify in that selection.');
        }

        $alreadyActive = $parameter->referenceRanges()
            ->active()
            ->whereKeyNot($selected->modelKeys())
            ->get();

        foreach ($selected as $index => $range) {
            $clash = $this->overlapping($range, $alreadyActive->concat($selected->slice($index + 1)));

            if ($clash !== null) {
                throw WorkflowViolationException::because(
                    "Nothing was verified: {$this->describe($range)} would overlap {$this->describe($clash)}. "
                    .'Deactivate or correct one of them first.'
                );
            }
        }

        return DB::transaction(function () use ($selected, $parameter, $actor, $ids): int {
            foreach ($selected as $range) {
                $this->markVerified($range, $actor);
                $this->recordVerified($range, $parameter, $actor, $ids === null ? 'bulk_all' : 'bulk_selected');
            }

            return $selected->count();
        });
    }

    public function setActive(LaboratoryReferenceRange $range, bool $active, User $actor): void
    {
        DB::transaction(function () use ($range, $active, $actor): void {
            if ($active) {
                $range->is_active = true;
                $this->assertNoActiveOverlap($range);
            }

            $range->is_active = $active;
            $range->save();

            $this->audit->record(
                $active ? AuditAction::ReferenceRangeActivated : AuditAction::ReferenceRangeDeactivated,
                $range,
                sprintf(
                    'Reference range %s %s on %s.',
                    $range->resultLabel(),
                    $active ? 'activated' : 'deactivated',
                    $range->parameter->name,
                ),
                ['parameter' => $range->parameter->code, 'is_placeholder' => $range->is_placeholder],
                $actor,
            );
        });
    }

    public function delete(LaboratoryReferenceRange $range, User $actor): void
    {
        DB::transaction(function () use ($range, $actor): void {
            $label = $range->resultLabel();
            $range->delete();

            $this->audit->record(
                AuditAction::ReferenceRangeDeleted,
                $range,
                "Reference range {$label} deleted from {$range->parameter->name}.",
                ['parameter' => $range->parameter->code, 'was_placeholder' => $range->is_placeholder],
                $actor,
            );
        });
    }

    /**
     * The active range of the same parameter and sex whose age interval this
     * one would share, if any. Used by the form request as well, so the form
     * and the service can never disagree about what an overlap is.
     *
     * @param  iterable<LaboratoryReferenceRange>|null  $others  defaults to the parameter's active ranges
     */
    public function overlapping(LaboratoryReferenceRange $range, ?iterable $others = null): ?LaboratoryReferenceRange
    {
        $others ??= LaboratoryReferenceRange::query()
            ->where('laboratory_test_parameter_id', $range->laboratory_test_parameter_id)
            ->active()
            ->when($range->exists, fn ($query) => $query->whereKeyNot($range->getKey()))
            ->get();

        foreach ($others as $other) {
            if ($range->exists && $other->is($range)) {
                continue;
            }

            if ($other->sex === $range->sex && $range->overlapsAgeOf($other)) {
                return $other;
            }
        }

        return null;
    }

    /** "the Female range 18+ a (12–15.5)" */
    public function describe(LaboratoryReferenceRange $range): string
    {
        return "the {$range->sex->label()} range {$range->ageLabel()} ({$range->valuesLabel()})";
    }

    // ----------------------------------------------------------------------

    private function assertNumeric(LaboratoryTestParameter $parameter): void
    {
        if ($parameter->data_type !== ParameterDataType::Numeric) {
            throw WorkflowViolationException::because('Reference ranges apply to numeric parameters only.');
        }
    }

    /** Verifying activates, so a range may only become active without overlap. */
    private function assertNoActiveOverlap(LaboratoryReferenceRange $range): void
    {
        $clash = $this->overlapping($range);

        if ($clash !== null) {
            throw WorkflowViolationException::because(
                ucfirst($this->describe($range))." would overlap {$this->describe($clash)}. "
                .'Deactivate or correct one of them first.'
            );
        }
    }

    /**
     * Approves the range as it now stands and records the approver.
     *
     * A previous approval is cleared and saved first: the snapshot trait
     * refuses to overwrite a recorded actor in place, because a snapshot
     * states a fact about a moment. Re-approving is a new moment, and the
     * previous approver stays in the audit trail.
     */
    private function markVerified(LaboratoryReferenceRange $range, User $actor): void
    {
        if ($range->hasActorSnapshot('verified_by')) {
            $range->clearActorSnapshot('verified_by');
            $range->save();
        }

        $range->is_placeholder = false;
        $range->is_active = true;
        $range->verified_at = now();
        $range->verified_by = $actor->getKey();
        $range->recordActor('verified_by', $this->identity->resolve($actor));
        $range->save();
    }

    private function recordVerified(LaboratoryReferenceRange $range, LaboratoryTestParameter $parameter, User $actor, string $via): void
    {
        $this->audit->record(
            AuditAction::ReferenceRangeVerified,
            $range,
            "Reference range {$range->resultLabel()} verified on {$parameter->name}.",
            ['parameter' => $parameter->code, 'via' => $via, 'verified_at' => $range->verified_at?->toDateTimeString()],
            $actor,
        );
    }

    /** @return array<string, array{from: mixed, to: mixed}> */
    private function describeChanges(LaboratoryReferenceRange $range): array
    {
        return (new Collection($range->getDirty()))
            ->mapWithKeys(fn ($value, string $key): array => [$key => [
                'from' => self::plain($range->getOriginal($key)),
                'to' => self::plain($range->getAttribute($key)),
            ]])
            ->all();
    }

    private static function plain(mixed $value): mixed
    {
        return $value instanceof \BackedEnum ? $value->value : $value;
    }
}
