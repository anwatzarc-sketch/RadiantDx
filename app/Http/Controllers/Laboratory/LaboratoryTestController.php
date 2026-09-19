<?php

declare(strict_types=1);

namespace App\Http\Controllers\Laboratory;

use App\Enums\TestResultType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Laboratory\LaboratoryTestRequest;
use App\Models\LaboratoryTest;
use App\Services\Laboratory\LaboratoryTestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LaboratoryTestController extends Controller
{
    public function __construct(private readonly LaboratoryTestService $tests) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', LaboratoryTest::class);

        $tests = LaboratoryTest::query()
            ->withCount(['parameters', 'panels'])
            ->search($request->string('search')->trim()->value())
            ->when($request->filled('category'), fn ($query) => $query->where('category', $request->string('category')))
            ->when($request->filled('result_type'), fn ($query) => $query->where('result_type', $request->string('result_type')))
            ->when($request->filled('status'), fn ($query) => $query->where('is_active', $request->input('status') === 'active'))
            ->ordered()
            ->paginate(15)
            ->withQueryString();

        return view('laboratory.tests.index', [
            'tests' => $tests,
            'categories' => $this->categories(),
            'filters' => $request->only(['search', 'category', 'result_type', 'status']),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', LaboratoryTest::class);

        return view('laboratory.tests.create', [
            'categories' => $this->categories(),
        ]);
    }

    public function store(LaboratoryTestRequest $request): RedirectResponse
    {
        $this->authorize('create', LaboratoryTest::class);

        $test = $this->tests->create($request->validated(), $request->user());

        $message = $test->result_type === TestResultType::Parameterised
            ? "Test {$test->name} created. Add its parameters next."
            : "Test {$test->name} created.";

        return redirect()->route('laboratory.tests.show', $test)->with('success', $message);
    }

    public function show(LaboratoryTest $test): View
    {
        $this->authorize('view', $test);

        return view('laboratory.tests.show', [
            'test' => $test->load(['parameters.options', 'panels']),
        ]);
    }

    public function edit(LaboratoryTest $test): View
    {
        $this->authorize('update', $test);

        return view('laboratory.tests.edit', [
            'test' => $test,
            'categories' => $this->categories(),
        ]);
    }

    public function update(LaboratoryTestRequest $request, LaboratoryTest $test): RedirectResponse
    {
        $this->authorize('update', $test);

        $this->tests->update($test, $request->validated(), $request->user());

        return redirect()->route('laboratory.tests.show', $test)->with('success', "Test {$test->name} updated.");
    }

    public function activate(Request $request, LaboratoryTest $test): RedirectResponse
    {
        $this->authorize('activate', $test);

        $this->tests->setActive($test, true, $request->user());

        return back()->with('success', "Test {$test->name} activated.");
    }

    public function deactivate(Request $request, LaboratoryTest $test): RedirectResponse
    {
        $this->authorize('deactivate', $test);

        $this->tests->setActive($test, false, $request->user());

        return back()->with('success', "Test {$test->name} deactivated and withdrawn from new requisitions.");
    }

    public function destroy(Request $request, LaboratoryTest $test): RedirectResponse
    {
        $this->authorize('delete', $test);

        $name = $test->name;
        $this->tests->delete($test, $request->user());

        return redirect()->route('laboratory.tests.index')->with('success', "Test {$name} deleted.");
    }

    /** @return array<int, string> */
    private function categories(): array
    {
        return LaboratoryTest::query()
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->all();
    }
}
