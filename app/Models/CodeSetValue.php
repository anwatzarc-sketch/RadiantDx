<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One entry of a coded value set, with its HL7 and FHIR mapping.
 *
 * @property int $id
 * @property string $value_set
 * @property string $code
 * @property string $display
 * @property string|null $hl7_code
 * @property string|null $hl7_table
 * @property string|null $fhir_code
 * @property bool $is_active
 */
class CodeSetValue extends Model
{
    protected $fillable = [
        'value_set',
        'code',
        'display',
        'hl7_code',
        'hl7_display',
        'hl7_table',
        'fhir_code',
        'fhir_system',
        'notes',
        'is_active',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'display_order' => 'integer',
        ];
    }

    /** @param Builder<$this> $query */
    public function scopeInSet(Builder $query, string $valueSet): void
    {
        $query->where('value_set', $valueSet)->orderBy('display_order')->orderBy('code');
    }
}
