<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
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
            'user_id' => $actor?->getKey(),
            'user_name' => $actor?->name,
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
