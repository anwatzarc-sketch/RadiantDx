<?php

declare(strict_types=1);

namespace App\Http\Controllers\Laboratory;

use App\Http\Controllers\Controller;
use App\Models\LaboratoryRequisition;
use App\Models\LaboratoryResult;
use App\Services\Laboratory\BuildLaboratoryReport;
use App\Services\Laboratory\ResultService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The printable laboratory report.
 *
 * This is deliberately a separate template from the administration screens: it
 * carries no navigation or actions, and is laid out for A4 paper.
 */
class ReportController extends Controller
{
    public function __construct(
        private readonly BuildLaboratoryReport $builder,
        private readonly ResultService $results,
    ) {}

    /** Report for one investigation. */
    public function result(Request $request, LaboratoryResult $result): View
    {
        $this->authorize('print', $result);

        $report = $this->builder->forResult($result);

        $this->results->recordPrint($result, $request->user());

        return view('laboratory.reports.report', $report);
    }

    /** Combined report covering every validated investigation on a requisition. */
    public function requisition(Request $request, LaboratoryRequisition $requisition): View
    {
        $this->authorize('view', $requisition);
        $this->authorize('print', LaboratoryResult::class);

        $report = $this->builder->forRequisition($requisition);

        foreach ($report['results'] as $result) {
            $this->results->recordPrint($result, $request->user());
        }

        return view('laboratory.reports.report', $report);
    }
}
