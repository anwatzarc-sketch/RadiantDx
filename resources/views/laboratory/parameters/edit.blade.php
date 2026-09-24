<x-layouts.admin :title="'Edit '.$parameter->name" :breadcrumbs="['Laboratory catalogue' => null, 'Test parameters' => route('laboratory.parameters.index'), $parameter->name => route('laboratory.parameters.show', $parameter), 'Edit' => null]">

    <x-page-header
        :title="'Edit '.$parameter->name"
        :subtitle="'Parameter of '.$parameter->test->name"
        :back="route('laboratory.parameters.show', $parameter)"
        back-label="Back to parameter"
    />

    @if ($parameter->isReferencedByLaboratoryRecords())
        <div class="flex items-start gap-3 rounded-lg bg-sky-50 px-4 py-3 text-sm text-sky-900 ring-1 ring-sky-600/20 ring-inset">
            <x-icon name="info" class="mt-0.5 size-5 shrink-0" />
            <p>
                This parameter has already been reported on. Existing results keep their own copy of the unit and
                reference range, so changes made here apply only to results created from now on.
            </p>
        </div>
    @endif

    <form method="POST" action="{{ route('laboratory.parameters.update', $parameter) }}">
        @csrf
        @method('PUT')

        <x-card title="Parameter" icon="parameter">
            @include('laboratory.parameters.partials.form', [
                'parameter' => $parameter,
                'tests' => $tests,
                'selectedTestId' => $parameter->laboratory_test_id,
            ])
        </x-card>

        <div class="mt-4 flex justify-end gap-2">
            <x-button :href="route('laboratory.parameters.show', $parameter)">Cancel</x-button>
            <x-button type="submit" variant="primary">Save changes</x-button>
        </div>
    </form>

    {{-- Outside the parameter form: each range has its own forms, and forms
         cannot nest. --}}
    @if ($parameter->data_type->isNumeric())
        <div class="mt-8">
            @include('laboratory.parameters.partials.reference-ranges', ['parameter' => $parameter])
        </div>
    @endif

</x-layouts.admin>
