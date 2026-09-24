<?php

declare(strict_types=1);

namespace App\Http\Controllers\Laboratory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Laboratory\TestParameterRequest;
use App\Models\LaboratoryTest;
use App\Models\LaboratoryTestParameter;
use App\Services\Laboratory\TestParameterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TestParameterController extends Controller
{
    public function __construct(private readonly TestParameterService $parameters) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', LaboratoryTestParameter::class);

        $parameters = LaboratoryTestParameter::query()
            ->with('test')
            ->withCount(['referenceRanges as placeholder_ranges_count' => fn ($query) => $query->where('is_placeholder', true)])
            ->search($request->string('search')->trim()->value())
            ->when($request->filled('test'), fn ($query) => $query->where('laboratory_test_id', $request->integer('test')))
            ->when($request->filled('data_type'), fn ($query) => $query->where('data_type', $request->string('data_type')))
            ->when($request->filled('status'), fn ($query) => $query->where('is_active', $request->input('status') === 'active'))
            ->orderBy('laboratory_test_id')
            ->ordered()
            ->paginate(20)
            ->withQueryString();

        return view('laboratory.parameters.index', [
            'parameters' => $parameters,
            'tests' => LaboratoryTest::query()->ordered()->get(),
            'filters' => $request->only(['search', 'test', 'data_type', 'status']),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', LaboratoryTestParameter::class);

        return view('laboratory.parameters.create', [
            'tests' => LaboratoryTest::query()->ordered()->get(),
            'selectedTestId' => $request->integer('test') ?: null,
        ]);
    }

    public function store(TestParameterRequest $request): RedirectResponse
    {
        $this->authorize('create', LaboratoryTestParameter::class);

        $parameter = $this->parameters->create(
            $request->safe()->except('options'),
            $request->dropdownOptions(),
            $request->user(),
        );

        return redirect()
            ->route('laboratory.tests.show', $parameter->laboratory_test_id)
            ->with('success', "Parameter {$parameter->name} added.");
    }

    public function show(LaboratoryTestParameter $parameter): View
    {
        $this->authorize('view', $parameter);

        return view('laboratory.parameters.show', [
            'parameter' => $parameter->load(['test', 'options', 'referenceRanges']),
        ]);
    }

    public function edit(LaboratoryTestParameter $parameter): View
    {
        $this->authorize('update', $parameter);

        return view('laboratory.parameters.edit', [
            'parameter' => $parameter->load(['options', 'referenceRanges']),
            'tests' => LaboratoryTest::query()->ordered()->get(),
        ]);
    }

    public function update(TestParameterRequest $request, LaboratoryTestParameter $parameter): RedirectResponse
    {
        $this->authorize('update', $parameter);

        $this->parameters->update(
            $parameter,
            $request->safe()->except('options'),
            $request->dropdownOptions(),
            $request->user(),
        );

        return redirect()
            ->route('laboratory.parameters.show', $parameter)
            ->with('success', "Parameter {$parameter->name} updated.");
    }

    public function activate(Request $request, LaboratoryTestParameter $parameter): RedirectResponse
    {
        $this->authorize('activate', $parameter);

        $this->parameters->setActive($parameter, true, $request->user());

        return back()->with('success', "Parameter {$parameter->name} activated.");
    }

    public function deactivate(Request $request, LaboratoryTestParameter $parameter): RedirectResponse
    {
        $this->authorize('deactivate', $parameter);

        $this->parameters->setActive($parameter, false, $request->user());

        return back()->with('success', "Parameter {$parameter->name} deactivated.");
    }

    public function destroy(Request $request, LaboratoryTestParameter $parameter): RedirectResponse
    {
        $this->authorize('delete', $parameter);

        $name = $parameter->name;
        $testId = $parameter->laboratory_test_id;
        $this->parameters->delete($parameter, $request->user());

        return redirect()
            ->route('laboratory.tests.show', $testId)
            ->with('success', "Parameter {$name} deleted.");
    }
}
