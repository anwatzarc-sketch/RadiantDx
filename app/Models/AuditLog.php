<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AuditAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Operational trace of a workflow event. The user name is snapshotted so the
 * history stays readable after an account is removed.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string|null $user_name
 * @property AuditAction $action
 * @property string|null $entity_type
 * @property int|null $entity_id
 * @property array<string, mixed>|null $metadata
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'user_name',
        'actor_staff_id',
        'actor_title',
        'actor_speciality',
        'actor_provenance',
        'action',
        'entity_type',
        'entity_id',
        'entity_label',
        'description',
        'metadata',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'action' => AuditAction::class,
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @param Builder<$this> $query */
    public function scopeForEntity(Builder $query, Model $entity): void
    {
        $query->where('entity_type', $entity::class)->where('entity_id', $entity->getKey());
    }

    public function actorName(): string
    {
        return $this->user_name ?? $this->user?->name ?? 'System';
    }
}
