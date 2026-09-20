<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\ActorIdentity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Records workflow events for operational traceability.
 *
 * Only meaningful state changes are written, and the metadata deliberately
 * stays descriptive: identifiers, counts and reasons, never the clinical values
 * themselves. Clinical detail lives in the laboratory records, which are access
 * controlled; the audit trail is not.
 */
class AuditLogger
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        AuditAction $action,
        ?Model $entity = null,
        ?string $description = null,
        array $metadata = [],
        ?User $actor = null,
    ): AuditLog {
        $actor ??= Auth::user();

        return AuditLog::query()->create([
            ...$this->actorAttributes($actor),
            'action' => $action,
            'entity_type' => $entity === null ? null : $entity::class,
            'entity_id' => $entity?->getKey(),
            'entity_label' => $entity === null ? null : $this->labelFor($entity),
            'description' => $description,
            'metadata' => $metadata === [] ? null : $metadata,
            'ip_address' => Request::ip(),
            'user_agent' => mb_substr((string) Request::userAgent(), 0, 512) ?: null,
        ]);
    }

    /**
     * The actor's identity as at the moment of the event.
     *
     * `user_name` was always a snapshot of the name; this widens it to the rest
     * of the professional identity, so an entry written today still reads
     * correctly after the person changes speciality or leaves.
     *
     * The staff record is read directly rather than through
     * AuthenticatedStaffResolver on purpose: the resolver refuses to resolve a
     * suspended member of staff, which is right for performing work but wrong
     * for recording it. Suspending an account is itself an audited event, and
     * it must record who it happened to.
     *
     * A null actor means work with no session; it is recorded as the system
     * actor rather than left blank.
     *
     * @return array<string, mixed>
     */
    private function actorAttributes(?User $actor): array
    {
        if ($actor === null) {
            return ActorIdentity::system()->auditAttributes();
        }

        $staff = $actor->staff;

        if ($staff === null) {
            // An account with no staff record can still act on administration
            // screens; record what is known rather than failing the audit write.
            return ActorIdentity::preMigration($actor->name, $actor->getKey())->auditAttributes();
        }

        return ActorIdentity::fromStaff($actor, $staff)->auditAttributes();
    }

    /**
     * A short human readable handle for the record, so the trail stays readable
     * even after the record itself is gone.
     */
    private function labelFor(Model $entity): ?string
    {
        foreach (['requisition_number', 'result_number', 'code', 'email', 'name'] as $attribute) {
            $value = $entity->getAttribute($attribute);

            if (is_string($value) && $value !== '') {
                return mb_substr($value, 0, 255);
            }
        }

        return null;
    }
}
