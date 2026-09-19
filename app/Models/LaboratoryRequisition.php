<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Gender;
use App\Enums\RequisitionPriority;
use App\Enums\RequisitionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A request for laboratory investigation, and the centre of the request
 * workflow. Items hold the individual tests the laboratory has to perform.
 *
 * @property int $id
 * @property string $requisition_number
 * @property string $patient_identifier
 * @property string $patient_name
 * @property Gender|null $patient_gender
 * @property Carbon|null $patient_date_of_birth
 * @property int|null $patient_age_years
 * @property Carbon $requested_date
 * @property RequisitionPriority $priority
 * @property RequisitionStatus $status
 * @property-read Collection<int, LaboratoryRequisitionItem> $items
 * @property-read Collection<int, LaboratoryResult> $results
 */
class LaboratoryRequisition extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'requisition_number',
        'patient_identifier',
        'patient_name',
        'patient_gender',
        'patient_date_of_birth',
        'patient_age_years',
        'requested_date',
        'requesting_clinician',
        'requesting_department',
        'priority',
        'clinical_indication',
        'clinical_notes',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'patient_gender' => Gender::class,
            'patient_date_of_birth' => 'date',
            'patient_age_years' => 'integer',
            'requested_date' => 'date',
            'priority' => RequisitionPriority::class,
            'status' => RequisitionStatus::class,
            'submitted_at' => 'datetime',
            'collected_at' => 'datetime',
            'processing_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /** @return HasMany<LaboratoryRequisitionItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(LaboratoryRequisitionItem::class)
            ->orderBy('display_order')
            ->orderBy('id');
    }

    /** @return HasMany<LaboratoryResult, $this> */
    public function results(): HasMany
    {
        return $this->hasMany(LaboratoryResult::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** @return BelongsTo<User, $this> */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
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
            $builder->where('requisition_number', 'like', "%{$term}%")
                ->orWhere('patient_name', 'like', "%{$term}%")
                ->orWhere('patient_identifier', 'like', "%{$term}%")
                ->orWhere('requesting_clinician', 'like', "%{$term}%");
        });
    }

    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }

    public function isCancelled(): bool
    {
        return $this->status === RequisitionStatus::Cancelled;
    }

    public function canBeSubmitted(): bool
    {
        return $this->status === RequisitionStatus::Draft && $this->items()->exists();
    }

    public function canBeCancelled(): bool
    {
        return $this->status->canTransitionTo(RequisitionStatus::Cancelled);
    }

    /** Age in completed years, from the date of birth when known. */
    public function age(): ?int
    {
        if ($this->patient_date_of_birth !== null) {
            return (int) $this->patient_date_of_birth->diffInYears(Carbon::today());
        }

        return $this->patient_age_years;
    }

    public function ageLabel(): string
    {
        $age = $this->age();

        return $age === null ? 'Not recorded' : $age.' yr';
    }

    /**
     * Investigations grouped the way they were requested: one entry per single
     * test, and one entry per requested panel holding its member tests.
     *
     * @return \Illuminate\Support\Collection<int, array{type: string, label: string, code: string|null, items: Collection<int, LaboratoryRequisitionItem>}>
     */
    public function groupedItems(): \Illuminate\Support\Collection
    {
        return $this->items
            ->groupBy(fn (LaboratoryRequisitionItem $item): string => $item->laboratory_panel_id === null
                ? 'test:'.$item->id
                : 'panel:'.$item->laboratory_panel_id)
            ->map(function (Collection $items, string $key): array {
                $first = $items->first();
                $isPanel = str_starts_with($key, 'panel:');

                return [
                    'type' => $isPanel ? 'Panel' : 'Test',
                    'label' => $isPanel ? (string) $first->panel_name : (string) $first->test_name,
                    'code' => $isPanel ? $first->panel_code : $first->test_code,
                    'items' => $items,
                ];
            })
            ->values();
    }

    /** Requisition level progress used by the details page and dashboard. */
    public function resultProgress(): array
    {
        $total = $this->items->count();
        $entered = $this->results->where('status', \App\Enums\ResultStatus::Completed)->count();
        $validated = $this->results
            ->where('validation_status', \App\Enums\ValidationStatus::Validated)
            ->count();

        return [
            'total' => $total,
            'entered' => $entered,
            'validated' => $validated,
            'percentage' => $total === 0 ? 0 : (int) round($validated / $total * 100),
        ];
    }
}
