<?php

declare(strict_types=1);

namespace App\Http\Controllers\Laboratory;

use App\Enums\AbnormalWhen;
use App\Enums\AgeUnit;
use App\Enums\ParameterDataType;
use App\Enums\RangeSex;
use App\Http\Controllers\Controller;
use App\Http\Requests\Laboratory\ReferenceRangeRequest;
use App\Models\CodeSetValue;
use App\Models\LaboratoryReferenceRange;
use App\Models\LaboratoryTestParameter;
use App\Services\Laboratory\ReferenceRangeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A parameter's reference ranges. They are listed on the parameter's own
 * page; this controller handles the forms and the actions on them.
 */
class ReferenceRangeController extends Controller
{
    public function __construct(private readonly ReferenceRangeService $ranges) {}

    public function create(LaboratoryTestParameter $parameter): View|RedirectResponse
    {
        $this->authorize('create', LaboratoryReferenceRange::class);

        if ($parameter->data_type !== ParameterDataType::Numeric) {
            return $this->notNumeric($parameter);
        }

        return view('laboratory.parameters.ranges.create', [
            'parameter' => $parameter->load('test'),
            'range' => null,
            ...$this->formOptions(),
        ]);
    }

    public function store(ReferenceRangeRequest $request, LaboratoryTestParameter $parameter): RedirectResponse
    {
        $this->authorize('create', LaboratoryReferenceRange::class);

        $range = $this->ranges->create($parameter, $request->rangeAttributes(), $request->user());

        return redirect()
            ->route('laboratory.parameters.show', $parameter)
            ->with('success', "Range {$range->resultLabel()} added and verified.");
    }

    public function edit(LaboratoryTestParameter $parameter, LaboratoryReferenceRange $referenceRange): View
    {
        $this->authorize('update', $referenceRange);

        return view('laboratory.parameters.ranges.edit', [
            'parameter' => $parameter->load('test'),
            'range' => $referenceRange,
            ...$this->formOptions(),
        ]);
    }

    public function update(
        ReferenceRangeRequest $request,
        LaboratoryTestParameter $parameter,
        LaboratoryReferenceRange $referenceRange,
    ): RedirectResponse {
        $this->authorize('update', $referenceRange);

        $this->ranges->update($referenceRange, $request->rangeAttributes(), $request->user());

        return redirect()
            ->route('laboratory.parameters.show', $parameter)
            ->with('success', "Range {$referenceRange->resultLabel()} saved and verified.");
    }

    public function verify(Request $request, LaboratoryTestParameter $parameter, LaboratoryReferenceRange $referenceRange): RedirectResponse
    {
        $this->authorize('verify', $referenceRange);

        $this->ranges->verify($referenceRange, $request->user());

        return back()->with('success', "Range {$referenceRange->resultLabel()} verified.");
    }

    /**
     * "Verify selected" sends the ticked ids; "Verify all" sends scope=all
     * and covers every placeholder on the parameter.
     */
    public function verifyMany(Request $request, LaboratoryTestParameter $parameter): RedirectResponse
    {
        $this->authorize('create', LaboratoryReferenceRange::class);

        $validated = $request->validate([
            'scope' => ['required', 'in:selected,all'],
            'ranges' => ['required_if:scope,selected', 'array'],
            'ranges.*' => ['integer'],
        ], [
            'ranges.required_if' => 'Tick at least one range to verify.',
        ]);

        $ids = $validated['scope'] === 'all'
            ? null
            : array_map('intval', $validated['ranges']);

        $count = $this->ranges->verifyMany($parameter, $ids, $request->user());

        return back()->with('success', trans_choice('{1} 1 range verified.|[2,*] :count ranges verified.', $count, ['count' => $count]));
    }

    public function activate(Request $request, LaboratoryTestParameter $parameter, LaboratoryReferenceRange $referenceRange): RedirectResponse
    {
        $this->authorize('activate', $referenceRange);

        $this->ranges->setActive($referenceRange, true, $request->user());

        return back()->with('success', "Range {$referenceRange->resultLabel()} activated.");
    }

    public function deactivate(Request $request, LaboratoryTestParameter $parameter, LaboratoryReferenceRange $referenceRange): RedirectResponse
    {
        $this->authorize('deactivate', $referenceRange);

        $this->ranges->setActive($referenceRange, false, $request->user());

        return back()->with('success', "Range {$referenceRange->resultLabel()} deactivated.");
    }

    public function destroy(Request $request, LaboratoryTestParameter $parameter, LaboratoryReferenceRange $referenceRange): RedirectResponse
    {
        $this->authorize('delete', $referenceRange);

        $label = $referenceRange->resultLabel();
        $this->ranges->delete($referenceRange, $request->user());

        return redirect()
            ->route('laboratory.parameters.show', $parameter)
            ->with('success', "Range {$label} deleted.");
    }

    /** @return array<string, mixed> */
    private function formOptions(): array
    {
        return [
            'sexes' => RangeSex::options(),
            'ageUnits' => AgeUnit::options(),
            'rules' => AbnormalWhen::options(),
            'ageCategories' => CodeSetValue::query()->inSet('age_category')->pluck('display', 'code')->all(),
        ];
    }

    private function notNumeric(LaboratoryTestParameter $parameter): RedirectResponse
    {
        return redirect()
            ->route('laboratory.parameters.show', $parameter)
            ->with('error', 'Reference ranges apply to numeric parameters only.');
    }
}
