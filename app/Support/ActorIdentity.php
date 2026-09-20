<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Staff;
use App\Models\User;

/**
 * Who performed an action, resolved once and thereafter unchangeable.
 *
 * This is the only representation of a workflow actor in the application.
 * Nothing constructs it from request data: it comes from
 * {@see \App\Services\AuthenticatedStaffResolver} reading the authenticated
 * session, or from {@see self::system()} for work with no signed-in person.
 *
 * Readonly because an actor is a fact about a moment. Once a result has been
 * validated by someone, that is who validated it; a mutable actor object
 * invites code that "corrects" it later.
 */
final readonly class ActorIdentity
{
    /** Where this identity came from, recorded alongside it on historical rows. */
    public const PROVENANCE_AUTHENTICATED = 'authenticated';

    public const PROVENANCE_SYSTEM = 'system';

    public const PROVENANCE_PRE_MIGRATION = 'pre_migration';

    private function __construct(
        public ?int $userId,
        public ?int $staffId,
        public string $name,
        public ?string $staffNumber,
        public ?string $title,
        public ?string $speciality,
        public ?string $specialityLabel,
        public string $provenance,
    ) {}

    /**
     * The identity of a signed-in person.
     *
     * Only called by the resolver, which has already established that the
     * staff record exists and its status permits the work.
     */
    public static function fromStaff(User $user, Staff $staff): self
    {
        return new self(
            userId: $user->getKey(),
            staffId: $staff->getKey(),
            name: $staff->full_name,
            staffNumber: $staff->staff_id,
            title: $staff->title,
            speciality: $staff->speciality?->value,
            specialityLabel: $staff->speciality?->label(),
            provenance: self::PROVENANCE_AUTHENTICATED,
        );
    }

    /**
     * The actor for console commands, queued jobs and scheduled work.
     *
     * Such work has no browser session, but it still has an actor. Writing a
     * null actor instead would leave audit rows that cannot be accounted for.
     */
    public static function system(?string $detail = null): self
    {
        return new self(
            userId: null,
            staffId: null,
            name: $detail === null ? 'System' : "System ({$detail})",
            staffNumber: null,
            title: null,
            speciality: null,
            specialityLabel: null,
            provenance: self::PROVENANCE_SYSTEM,
        );
    }

    /**
     * An actor recovered from data that predates staff records.
     *
     * Used by the historical backfill when all that survives is a name, or not
     * even that. A report from before the migration still has to render a
     * meaningful line rather than a blank one.
     */
    public static function preMigration(?string $name, ?int $userId = null): self
    {
        $name = $name !== null ? trim($name) : '';

        return new self(
            userId: $userId,
            staffId: null,
            name: $name === '' ? 'Unknown — pre-migration' : $name,
            staffNumber: null,
            title: null,
            speciality: null,
            specialityLabel: null,
            provenance: self::PROVENANCE_PRE_MIGRATION,
        );
    }

    public function isSystem(): bool
    {
        return $this->provenance === self::PROVENANCE_SYSTEM;
    }

    /** Display form: "Dr Amina Hassan" when a title is recorded. */
    public function displayName(): string
    {
        $title = trim((string) $this->title);

        return $title === '' ? $this->name : $title.' '.$this->name;
    }

    /**
     * The columns persisted as an immutable snapshot on a workflow record.
     *
     * Prefixed per actor role, so one row can carry several — a result has both
     * the person who entered it and the person who validated it.
     *
     * @return array<string, mixed>
     */
    public function snapshotColumns(string $prefix): array
    {
        return [
            "{$prefix}_staff_id" => $this->staffId,
            "{$prefix}_actor_name" => $this->name,
            "{$prefix}_actor_title" => $this->title,
            "{$prefix}_actor_speciality" => $this->speciality,
            "{$prefix}_actor_provenance" => $this->provenance,
        ];
    }

    /**
     * The audit trail's view of this actor.
     *
     * @return array<string, mixed>
     */
    public function auditAttributes(): array
    {
        return [
            'user_id' => $this->userId,
            'user_name' => $this->name,
            'actor_staff_id' => $this->staffId,
            'actor_title' => $this->title,
            'actor_speciality' => $this->speciality,
            'actor_provenance' => $this->provenance,
        ];
    }
}
