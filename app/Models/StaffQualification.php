<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\QualificationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One qualification held by a member of staff.
 *
 * @property int $id
 * @property QualificationType $type
 */
class StaffQualification extends Model
{
    /** staff_id is set from the route-resolved record, never from a payload. */
    protected $fillable = [
        'type',
        'institution',
        'field',
        'awarded_year',
        'reference',
    ];

    protected function casts(): array
    {
        return [
            'type' => QualificationType::class,
            'awarded_year' => 'integer',
        ];
    }

    /** @return BelongsTo<Staff, $this> */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    /** "Medical Degree, Addis Ababa University (2014)" */
    public function summary(): string
    {
        $parts = array_filter([
            $this->type->label(),
            $this->field,
            $this->institution,
        ]);

        $line = implode(', ', $parts);

        return $this->awarded_year === null ? $line : "{$line} ({$this->awarded_year})";
    }
}
