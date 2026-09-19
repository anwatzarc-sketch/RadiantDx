<?php

declare(strict_types=1);

namespace App\Http\Controllers\Laboratory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Laboratory\ReasonRequest;
use App\Http\Requests\Laboratory\ResultEntryRequest;
use App\Models\LaboratoryPanel;
use App\Models\LaboratoryRequisitionItem;
use App\Models\LaboratoryResult;
use App\Models\LaboratoryTest;
use App\Services\Laboratory\ResultService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ResultController extends Controller
{
    public function __construct(private readonly ResultService $results) {}

    /** The laboratory bench work queue. */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', LaboratoryResult::class);

        $results = LaboratoryResult::query()
            ->with(['requisition', 'validatedBy'])
            ->search($request->string('search')->trim()->value())
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when(
                $request->filled('validation_status'),
                fn ($query) => $query->where('validation_status', $request->string('validation_status'))
            )
            ->when($request->filled('test'), fn ($query) => $query->where('laboratory_test_id', $request->integer('test')))
            ->when($request->filled('panel'), fn ($query) => $query->whereHas(
                'requisitionItem',
                fn ($item) => $item->where('laboratory_panel_id', $request->integer('panel'))
            ))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('created_at', '<=', $request->date('to')))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('laboratory.results.index', [
            'results' => $results,
            'tests' => LaboratoryTest::query()->ordered()->get(),
            'panels' => LaboratoryPanel::query()->ordered()->get(),
            'filters' => $request->only(['search', 'status', 'validation_status', 'test', 'panel', 'from', 'to']),
        ]);
    }

    /** Opens a result record for an investigation that does not have one yet. */
    public function store(Request $request, LaboratoryRequisitionItem $item): RedirectResponse
    {
        $this->authorize('create', LaboratoryResult::class);
        $this->authorize('view', $item->requisition);

        $result = $this->results->createFor($item, $request->user());

        return redirect()
            ->route('laboratory.results.show', $result)
            ->with('success', "Result {$result->result_number} opened for {$result->test_name}.");
    }

    /** The central result management screen: entry, review, validation, history. */
    public function show(LaboratoryResult $result): View
    {
        $this->authorize('view', $result);

        $result->load([
            'requisition',
            'parameters.parameter.options',
            'performedBy',
            'validatedBy',
            'unvalidatedBy',
        ]);

        return view('laboratory.results.show', [
            'result' => $result,
            'activity' => $result->auditLogs()->limit(30)->get(),
        ]);
    }

    public function update(ResultEntryRequest $request, LaboratoryResult $result): RedirectResponse
    {
        $this->authorize('update', $result);

        $this->results->recordValues(
            $result,
            $request->values(),
            $request->resultAttributes(),
            $request->user(),
        );

        return redirect()
            ->route('laboratory.results.show', $result)
            ->with('success', "Result {$result->result_number} saved.");
    }

    public function validateResult(Request $request, LaboratoryResult $result): RedirectResponse
    {
        $this->authorize('validate', $result);

        $this->results->validate($result, $request->user());

        return back()->with('success', "Result {$result->result_number} validated and released.");
    }

    public function unvalidate(ReasonRequest $request, LaboratoryResult $result): RedirectResponse
    {
        $this->authorize('unvalidate', $result);

        $this->results->unvalidate($result, $request->reason(), $request->user());

        return back()->with('success', "Result {$result->result_number} reopened for correction.");
    }

    public function destroy(Request $request, LaboratoryResult $result): RedirectResponse
    {
        $this->authorize('delete', $result);

        $number = $result->result_number;
        $requisition = $result->requisition;

        $this->results->delete($result, $request->user());

        return redirect()
            ->route('laboratory.requisitions.show', $requisition)
            ->with('success', "Result {$number} deleted.");
    }
}
