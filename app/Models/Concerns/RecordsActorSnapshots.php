<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Exceptions\WorkflowViolationException;
use App\Support\ActorIdentity;

/**
 * Append-only actor snapshots on a workflow record.
 *
 * A snapshot answers "who did this, as they were at the time". Once written it
 * is a statement about a moment that has passed, so this trait refuses to let
 * it change — including when the person's name is later corrected. A report
 * reprinted years later has to match the copy that was filed.
 *
 * That is deliberately different from how the staff directory behaves, where a
 * corrected name propagates. The two answer different questions: what is this
 * person called now, versus what did this document say when it was issued.
 *
 * A model using this declares its roles in $actorSnapshotRoles.
 */
trait RecordsActorSnapshots
{
    public static function bootRecordsActorSnapshots(): void
    {
        static::updating(function (self $model): void {
            $frozen = $model->lockedSnapshotColumns();

            if ($frozen !== []) {
                throw WorkflowViolationException::because(
                    'A recorded actor cannot be changed: '.implode(', ', $frozen).'. '
                    .'Who performed an action is a fact about when it happened, and reports '
                    .'already issued must keep showing it.'
                );
            }
        });
    }

    /**
     * Writes the snapshot for one role.
     *
     * Refuses to overwrite one that is already present — re-validating a result
     * goes through unvalidate first, which clears it explicitly.
     */
    public function recordActor(string $role, ActorIdentity $actor): void
    {
        $this->assertKnownRole($role);

        if ($this->hasActorSnapshot($role)) {
            throw WorkflowViolationException::because(
                "An actor is already recorded for {$role} on this record."
            );
        }

        foreach ($actor->snapshotColumns($role) as $column => $value) {
            $this->setAttribute($column, $value);
        }
    }

    /**
     * Clears a snapshot so the step can be performed again.
     *
     * The only sanctioned way a snapshot leaves a record, and it exists because
     * unvalidating a result genuinely undoes the act of validating it. Every
     * caller of this is an audited workflow transition.
     */
    public function clearActorSnapshot(string $role): void
    {
        $this->assertKnownRole($role);

        foreach (array_keys(ActorIdentity::system()->snapshotColumns($role)) as $column) {
            $this->setAttribute($column, null);
        }
    }

    public function hasActorSnapshot(string $role): bool
    {
        return $this->getAttribute("{$role}_actor_name") !== null;
    }

    /**
     * The recorded identity for a role, for display.
     *
     * Returns the frozen values, never a live lookup — that is the whole point.
     * Null when nothing was recorded, so a caller can fall back deliberately
     * rather than printing a blank line by accident.
     *
     * @return array{name: string, title: string|null, speciality: string|null, staff_id: int|null, provenance: string|null}|null
     */
    public function actorSnapshot(string $role): ?array
    {
        $this->assertKnownRole($role);

        $name = $this->getAttribute("{$role}_actor_name");

        if ($name === null) {
            return null;
        }

        return [
            'name' => (string) $name,
            'title' => $this->getAttribute("{$role}_actor_title"),
            'speciality' => $this->getAttribute("{$role}_actor_speciality"),
            'staff_id' => $this->getAttribute("{$role}_staff_id"),
            'provenance' => $this->getAttribute("{$role}_actor_provenance"),
        ];
    }

    /** "Dr Amina Hassan" from the snapshot, or null when none was recorded. */
    public function actorDisplayName(string $role): ?string
    {
        $snapshot = $this->actorSnapshot($role);

        if ($snapshot === null) {
            return null;
        }

        $title = trim((string) $snapshot['title']);

        return $title === '' ? $snapshot['name'] : $title.' '.$snapshot['name'];
    }

    /** The speciality label as recorded, resolved through the enum for display. */
    public function actorSpecialityLabel(string $role): ?string
    {
        $snapshot = $this->actorSnapshot($role);

        return $snapshot === null
            ? null
            : \App\Enums\Speciality::labelFor($snapshot['speciality']);
    }

    /**
     * Snapshot columns that are dirty and were already populated.
     *
     * Writing a snapshot for the first time is allowed; changing one is not.
     * Clearing one is allowed only through clearActorSnapshot(), which nulls
     * every column of the role together — recognised here by the name column
     * going to null.
     *
     * @return list<string>
     */
    private function lockedSnapshotColumns(): array
    {
        $locked = [];

        foreach ($this->actorSnapshotRoles() as $role) {
            $nameColumn = "{$role}_actor_name";

            // The whole role is being cleared: a sanctioned undo.
            if ($this->isDirty($nameColumn) && $this->getAttribute($nameColumn) === null) {
                continue;
            }

            foreach (array_keys(ActorIdentity::system()->snapshotColumns($role)) as $column) {
                if (! $this->isDirty($column)) {
                    continue;
                }

                // Populated before this change: immutable.
                if ($this->getOriginal($column) !== null) {
                    $locked[] = $column;
                }
            }
        }

        return $locked;
    }

    /** @return list<string> */
    private function actorSnapshotRoles(): array
    {
        return $this->actorSnapshotRoles ?? [];
    }

    private function assertKnownRole(string $role): void
    {
        if (! in_array($role, $this->actorSnapshotRoles(), true)) {
            throw new \InvalidArgumentException(
                static::class." does not record an actor for [{$role}]."
            );
        }
    }
}
