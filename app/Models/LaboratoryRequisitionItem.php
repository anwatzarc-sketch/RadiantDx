<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RequisitionItemStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One test the laboratory has to perform for a requisition. A requested panel
 * is expanded into one item per member test while keeping the panel reference
 * and its snapshotted name, so the original request stays readable.
 *
 * @property int $id
 * @property int $laboratory_requisition_id
 * @property string $source_type
 * @property int $laboratory_test_id
 * @property int|null $laboratory_panel_id
 * @property string $test_name
 * @property string $test_code
 * @property string|null $panel_name
 * @property string|null $panel_code
 * @property RequisitionItemStatus $status
 * @property-read LaboratoryResult|null $result
 */
class LaboratoryRequisitionItem extends Model
{
    public const SOURCE_TEST = 'test';

    public const SOURCE_PANEL = 'panel';

    protected $fillable = [
        'laboratory_requisition_id',
        'source_type',
        'laboratory_test_id',
        'laboratory_panel_id',
        'test_name',
        'test_code',
        'specimen_type',
        'panel_name',
        'panel_code',
        'status',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'status' => RequisitionItemStatus::class,
            'display_order' => 'integer',
        ];
    }

    /** @return BelongsTo<LaboratoryRequisition, $this> */
    public function requisition(): BelongsTo
    {
        return $this->belongsTo(LaboratoryRequisition::class, 'laboratory_requisition_id');
    }

    /** @return BelongsTo<LaboratoryTest, $this> */
    public function test(): BelongsTo
    {
        return $this->belongsTo(LaboratoryTest::class, 'laboratory_test_id');
    }

    /** @return BelongsTo<LaboratoryPanel, $this> */
    public function panel(): BelongsTo
    {
        return $this->belongsTo(LaboratoryPanel::class, 'laboratory_panel_id');
    }

    /** @return HasOne<LaboratoryResult, $this> */
    public function result(): HasOne
    {
        return $this->hasOne(LaboratoryResult::class, 'laboratory_requisition_item_id');
    }

    public function isFromPanel(): bool
    {
        return $this->laboratory_panel_id !== null;
    }

    public function displayLabel(): string
    {
        return $this->isFromPanel()
            ? $this->panel_name.' / '.$this->test_name
            : $this->test_name;
    }
}
