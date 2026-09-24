<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $laboratory_test_parameter_id
 * @property string $value
 * @property string $label
 * @property bool $is_abnormal
 * @property bool $is_active
 */
class LaboratoryTestParameterOption extends Model
{
    protected $fillable = [
        'laboratory_test_parameter_id',
        'value',
        'label',
        'snomed_code',
        'is_abnormal',
        'hl7_flag',
        'is_active',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'is_abnormal' => 'boolean',
            'is_active' => 'boolean',
            'display_order' => 'integer',
        ];
    }

    /** @return BelongsTo<LaboratoryTestParameter, $this> */
    public function parameter(): BelongsTo
    {
        return $this->belongsTo(LaboratoryTestParameter::class, 'laboratory_test_parameter_id');
    }
}
