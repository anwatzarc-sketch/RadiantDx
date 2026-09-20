<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\RecordsActorSnapshots;

use App\Enums\ResultStatus;
use App\Enums\ValidationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The result produced for one requested investigation.
 *
 * @property int $id
 * @property string $result_number
 * @property ResultStatus $status
 * @property ValidationStatus $validation_status
 * @property int $revision
 * @property-read Collection<int, LaboratoryResultParameter> $parameters
 * @property-read LaboratoryRequisition $requisition
 */
class LaboratoryResult extends Model
{
    use RecordsActorSnapshots, SoftDeletes;

    /**
     * Roles this record freezes an actor for. Each appears on a printed report
     * or a historical screen, so each must survive a later name change.
     *
     * @var list<string>
     */
    protected array $actorSnapshotRoles = ['performed_by', 'validated_by', 'printed_by'];

    protected $fillable = [
        'result_number',
        'laboratory_requisition_id',
        'laboratory_requisition_item_id',
        'laboratory_test_id',
        'test_name',
        'test_code',
        'specimen_type',
        'panel_name',
        'status',
        'validation_status',
        'interpretation',
        'comments',
    ];

    protected function casts(): array
    {
        return [
            'status' => ResultStatus::class,
            'validation_status' => ValidationStatus::class,
            'performed_at' => 'datetime',
            'validated_at' => 'datetime',
            'unvalidated_at' => 'datetime',
            'last_printed_at' => 'datetime',
            'revision' => 'integer',
            'print_count' => 'integer',
        ];
    }

    /** @return BelongsTo<LaboratoryRequisition, $this> */
    public function requisition(): BelongsTo
    {
        return $this->belongsTo(LaboratoryRequisition::class, 'laboratory_requisition_id');
    }

    /** @return BelongsTo<LaboratoryRequisitionItem, $this> */
    public function requisitionItem(): BelongsTo
    {
        return $this->belongsTo(LaboratoryRequisitionItem::class, 'laboratory_requisition_item_id');
    }

    /** @return BelongsTo<LaboratoryTest, $this> */
    public function test(): BelongsTo
    {
        return $this->belongsTo(LaboratoryTest::class, 'laboratory_test_id');
    }

    /** @return HasMany<LaboratoryResultParameter, $this> */
    public function parameters(): HasMany
    {
        return $this->hasMany(LaboratoryResultParameter::class)
            ->orderBy('display_order')
            ->orderBy('id');
    }

    /** @return BelongsTo<User, $this> */
    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    /** @return BelongsTo<User, $this> */
    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    /** @return BelongsTo<User, $this> */
    public function unvalidatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'unvalidated_by');
    }

    /** @return HasMany<AuditLog, $this> */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'entity_id')
            ->where('entity_type', self::class)
            ->latest('created_at');
    }

    /** @param Builder<$this> $query */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $query->where(function (Builder $builder) use ($term): void {
            $builder->where('result_number', 'like', "%{$term}%")
                ->orWhere('test_name', 'like', "%{$term}%")
                ->orWhereHas('requisition', function (Builder $requisition) use ($term): void {
                    $requisition->where('requisition_number', 'like', "%{$term}%")
                        ->orWhere('patient_name', 'like', "%{$term}%")
                        ->orWhere('patient_identifier', 'like', "%{$term}%");
                });
        });
    }

    public function isValidated(): bool
    {
        return $this->validation_status === ValidationStatus::Validated;
    }

    /**
     * A validated result is finalised: ordinary editing is closed until it is
     * explicitly unvalidated through the authorised correction workflow.
     */
    public function isEditable(): bool
    {
        return ! $this->isValidated();
    }

    /** Every reportable value has been entered. */
    public function isComplete(): bool
    {
        return $this->parameters->every(
            fn (LaboratoryResultParameter $parameter): bool => $parameter->hasValue()
        ) && $this->parameters->isNotEmpty();
    }

    public function canBeValidated(): bool
    {
        return ! $this->isValidated() && $this->status === ResultStatus::Completed;
    }

    public function hasAbnormalValues(): bool
    {
        return $this->parameters->contains(
            fn (LaboratoryResultParameter $parameter): bool => $parameter->isOutsideReference()
        );
    }

    public function hasCriticalValues(): bool
    {
        return $this->parameters->contains(
            fn (LaboratoryResultParameter $parameter): bool => $parameter->interpretation?->isCritical() ?? false
        );
    }

    public function investigationLabel(): string
    {
        return $this->panel_name ? $this->panel_name.' / '.$this->test_name : $this->test_name;
    }

    /** Count of entered values, used by list screens and progress meters. */
    public function enteredParameterCount(): int
    {
        return $this->parameters
            ->filter(fn (LaboratoryResultParameter $parameter): bool => $parameter->hasValue())
            ->count();
    }
}
