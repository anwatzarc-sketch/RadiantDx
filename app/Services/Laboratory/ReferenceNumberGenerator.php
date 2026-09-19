<?php

declare(strict_types=1);

namespace App\Services\Laboratory;

use App\Models\LaboratoryRequisition;
use App\Models\LaboratoryResult;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Produces the human readable references printed on requisitions and reports,
 * in the form PREFIX-YYYYMMDD-00001.
 *
 * The sequence restarts each day and is derived from the highest number already
 * issued for that day, read inside the caller's transaction. The unique index on
 * the number column is the real guarantee; callers retry on collision.
 */
class ReferenceNumberGenerator
{
    public function forRequisition(?Carbon $date = null): string
    {
        return $this->next(
            (new LaboratoryRequisition)->getTable(),
            'requisition_number',
            (string) config('laboratory.numbering.requisition_prefix', 'REQ'),
            $date,
        );
    }

    public function forResult(?Carbon $date = null): string
    {
        return $this->next(
            (new LaboratoryResult)->getTable(),
            'result_number',
            (string) config('laboratory.numbering.result_prefix', 'RES'),
            $date,
        );
    }

    private function next(string $table, string $column, string $prefix, ?Carbon $date): string
    {
        $date ??= Carbon::now();
        $stem = $prefix.'-'.$date->format('Ymd').'-';
        $padding = (int) config('laboratory.numbering.sequence_padding', 5);

        $latest = DB::table($table)
            ->where($column, 'like', $stem.'%')
            ->orderByDesc($column)
            ->value($column);

        $sequence = $latest === null
            ? 1
            : ((int) mb_substr((string) $latest, mb_strlen($stem))) + 1;

        return $stem.str_pad((string) $sequence, $padding, '0', STR_PAD_LEFT);
    }
}
