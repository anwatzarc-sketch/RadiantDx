<?php

declare(strict_types=1);

namespace App\Http\Controllers\Laboratory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Laboratory\LaboratoryPanelRequest;
use App\Models\LaboratoryPanel;
use App\Models\LaboratoryTest;
use App\Services\Laboratory\LaboratoryPanelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LaboratoryPanelController extends Controller
{
    public function __construct(private readonly LaboratoryPanelService $panels) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', LaboratoryPanel::class);

        $panels = LaboratoryPanel::query()
            ->withCount('tests')
            ->search($request->string('search')->trim()->value())
            ->when($request->filled('category'), fn ($query) => $query->where('category', $request->string('category')))
            ->when($request->filled('status'), fn ($query) => $query->where('is_active', $request->input('status') === 'active'))
            ->ordered()
            ->paginate(15)
            ->withQueryString();

        return view('laboratory.panels.index', [
            'panels' => $panels,
            'categories' => $this->categories(),
            'filters' => $request->only(['search', 'category', 'status']),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', LaboratoryPanel::class);

        return view('laboratory.panels.create', [
            'availableTests' => LaboratoryTest::query()->ordered()->get(),
            'selectedTests' => collect(),
            'categories' => $this->categories(),
        ]);
    }

    public function store(LaboratoryPanelRequest $request): RedirectResponse
    {
        $this->authorize('create', LaboratoryPanel::class);

        $panel = $this->panels->create(
            $request->safe()->except('tests'),
            $request->testIds(),
            $request->user(),
        );

        return redirect()->route('laboratory.panels.show', $panel)->with('success', "Panel {$panel->name} created.");
    }

    public function show(LaboratoryPanel $panel): View
    {
        $this->authorize('view', $panel);

        return view('laboratory.panels.show', [
            'panel' => $panel->load('tests.parameters'),
        ]);
    }

    public function edit(LaboratoryPanel $panel): View
    {
        $this->authorize('update', $panel);

        return view('laboratory.panels.edit', [
            'panel' => $panel->load('tests'),
            'availableTests' => LaboratoryTest::query()->ordered()->get(),
            'selectedTests' => $panel->tests,
            'categories' => $this->categories(),
        ]);
    }

    public function update(LaboratoryPanelRequest $request, LaboratoryPanel $panel): RedirectResponse
    {
        $this->authorize('update', $panel);

        $this->panels->update(
            $panel,
            $request->safe()->except('tests'),
            $request->testIds(),
            $request->user(),
        );

        return redirect()->route('laboratory.panels.show', $panel)->with('success', "Panel {$panel->name} updated.");
    }

    public function activate(Request $request, LaboratoryPanel $panel): RedirectResponse
    {
        $this->authorize('activate', $panel);

        $this->panels->setActive($panel, true, $request->user());

        return back()->with('success', "Panel {$panel->name} activated.");
    }

    public function deactivate(Request $request, LaboratoryPanel $panel): RedirectResponse
    {
        $this->authorize('deactivate', $panel);

        $this->panels->setActive($panel, false, $request->user());

        return back()->with('success', "Panel {$panel->name} deactivated.");
    }

    public function destroy(Request $request, LaboratoryPanel $panel): RedirectResponse
    {
        $this->authorize('delete', $panel);

        $name = $panel->name;
        $this->panels->delete($panel, $request->user());

        return redirect()->route('laboratory.panels.index')->with('success', "Panel {$name} deleted.");
    }

    /** @return array<int, string> */
    private function categories(): array
    {
        return LaboratoryPanel::query()
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->all();
    }
}
