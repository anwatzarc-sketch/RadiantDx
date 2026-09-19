<?php

declare(strict_types=1);

namespace App\Http\Controllers\Laboratory;

use App\Enums\RequisitionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Laboratory\ReasonRequest;
use App\Http\Requests\Laboratory\RequisitionRequest;
use App\Http\Requests\Laboratory\TransitionRequisitionRequest;
use App\Models\LaboratoryPanel;
use App\Models\LaboratoryRequisition;
use App\Models\LaboratoryTest;
use App\Services\Laboratory\RequisitionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RequisitionController extends Controller
{
    public function __construct(private readonly RequisitionService $requisitions) {}

    /** The laboratory work queue for incoming requests. */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', LaboratoryRequisition::class);

        $requisitions = LaboratoryRequisition::query()
            ->withCount('items')
            ->with('createdBy')
            ->search($request->string('search')->trim()->value())
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('priority'), fn ($query) => $query->where('priority', $request->string('priority')))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('requested_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('requested_date', '<=', $request->date('to')))
            // Urgent work floats to the top of the queue. A CASE expression keeps
            // this portable across MySQL/MariaDB and the SQLite used by tests.
            ->orderByRaw("CASE priority WHEN 'stat' THEN 1 WHEN 'urgent' THEN 2 ELSE 3 END")
            ->latest('requested_date')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('laboratory.requisitions.index', [
            'requisitions' => $requisitions,
            'filters' => $request->only(['search', 'status', 'priority', 'from', 'to']),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', LaboratoryRequisition::class);

        return view('laboratory.requisitions.create', $this->catalogueForSelection() + [
            'requisition' => new LaboratoryRequisition(['requested_date' => today()]),
            'selected' => [],
        ]);
    }

    public function store(RequisitionRequest $request): RedirectResponse
    {
        $this->authorize('create', LaboratoryRequisition::class);

        $requisition = $this->requisitions->create(
            $request->requisitionAttributes(),
            $request->selections(),
            $request->user(),
        );

        if ($request->shouldSubmit()) {
            $this->authorize('submit', $requisition);
            $this->requisitions->submit($requisition, $request->user());

            return redirect()
                ->route('laboratory.requisitions.show', $requisition)
                ->with('success', "Requisition {$requisition->requisition_number} submitted to the laboratory.");
        }

        return redirect()
            ->route('laboratory.requisitions.show', $requisition)
            ->with('success', "Requisition {$requisition->requisition_number} saved as a draft.");
    }

    /** The main laboratory workflow screen for one request. */
    public function show(LaboratoryRequisition $requisition): View
    {
        $this->authorize('view', $requisition);

        $requisition->load([
            'items.result',
            'items.test',
            'results.parameters',
            'createdBy',
            'updatedBy',
            'cancelledBy',
        ]);

        return view('laboratory.requisitions.show', [
            'requisition' => $requisition,
            'activity' => $requisition->auditLogs()->limit(30)->get(),
        ]);
    }

    public function edit(LaboratoryRequisition $requisition): View
    {
        $this->authorize('update', $requisition);

        $requisition->load('items');

        return view('laboratory.requisitions.edit', $this->catalogueForSelection() + [
            'requisition' => $requisition,
            'selected' => $this->selectionKeysFor($requisition),
        ]);
    }

    public function update(RequisitionRequest $request, LaboratoryRequisition $requisition): RedirectResponse
    {
        $this->authorize('update', $requisition);

        $this->requisitions->update(
            $requisition,
            $request->requisitionAttributes(),
            $request->selections(),
            $request->user(),
        );

        if ($request->shouldSubmit()) {
            $this->authorize('submit', $requisition);
            $this->requisitions->submit($requisition, $request->user());

            return redirect()
                ->route('laboratory.requisitions.show', $requisition)
                ->with('success', "Requisition {$requisition->requisition_number} submitted to the laboratory.");
        }

        return redirect()
            ->route('laboratory.requisitions.show', $requisition)
            ->with('success', "Requisition {$requisition->requisition_number} updated.");
    }

    public function submit(Request $request, LaboratoryRequisition $requisition): RedirectResponse
    {
        $this->authorize('submit', $requisition);

        $this->requisitions->submit($requisition, $request->user());

        return back()->with('success', "Requisition {$requisition->requisition_number} submitted to the laboratory.");
    }

    /** Moves the specimen along: collected, processing, completed. */
    public function transition(
        TransitionRequisitionRequest $request,
        LaboratoryRequisition $requisition,
    ): RedirectResponse {
        $this->authorize('advance', $requisition);

        $target = $request->targetStatus();
        $this->requisitions->transitionTo($requisition, $target, $request->user());

        $message = $target === RequisitionStatus::Collected
            ? 'Specimen recorded as collected. Result records are now open for entry.'
            : "Requisition {$requisition->requisition_number} moved to {$target->label()}.";

        return back()->with('success', $message);
    }

    public function cancel(ReasonRequest $request, LaboratoryRequisition $requisition): RedirectResponse
    {
        $this->authorize('cancel', $requisition);

        $this->requisitions->cancel($requisition, $request->reason(), $request->user());

        return back()->with('success', "Requisition {$requisition->requisition_number} cancelled.");
    }

    public function destroy(Request $request, LaboratoryRequisition $requisition): RedirectResponse
    {
        $this->authorize('delete', $requisition);

        $number = $requisition->requisition_number;
        $this->requisitions->delete($requisition, $request->user());

        return redirect()
            ->route('laboratory.requisitions.index')
            ->with('success', "Draft requisition {$number} deleted.");
    }

    /**
     * Tests and panels that can actually be requested right now. Retired or
     * incomplete catalogue entries are left out of the picker.
     *
     * @return array<string, mixed>
     */
    private function catalogueForSelection(): array
    {
        return [
            'availableTests' => LaboratoryTest::query()
                ->active()
                ->with('activeParameters:id,laboratory_test_id')
                ->ordered()
                ->get()
                ->filter(fn (LaboratoryTest $test): bool => $test->isReadyForRequisition())
                ->values(),
            'availablePanels' => LaboratoryPanel::query()
                ->active()
                ->with('activeTests:id,name,code')
                ->ordered()
                ->get()
                ->filter(fn (LaboratoryPanel $panel): bool => $panel->activeTests->isNotEmpty())
                ->values(),
        ];
    }

    /**
     * Rebuilds the "type:id" selection keys from the stored work items so the
     * edit form reopens with the same investigations selected.
     *
     * @return array<int, string>
     */
    private function selectionKeysFor(LaboratoryRequisition $requisition): array
    {
        return $requisition->items
            ->map(fn ($item): string => $item->laboratory_panel_id !== null
                ? 'panel:'.$item->laboratory_panel_id
                : 'test:'.$item->laboratory_test_id)
            ->unique()
            ->values()
            ->all();
    }
}
