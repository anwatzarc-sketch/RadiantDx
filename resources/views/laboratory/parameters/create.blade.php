<x-layouts.admin title="New test parameter" :breadcrumbs="['Laboratory catalogue' => null, 'Test parameters' => route('laboratory.parameters.index'), 'New parameter' => null]">

    <x-page-header
        title="New test parameter"
        subtitle="A parameter is one reported value of a laboratory test."
        :back="route('laboratory.parameters.index')"
        back-label="Back to parameters"
    />

    <form method="POST" action="{{ route('laboratory.parameters.store') }}">
        @csrf

        <x-card title="Parameter" icon="parameter">
            @include('laboratory.parameters.partials.form', [
                'parameter' => null,
                'tests' => $tests,
                'selectedTestId' => $selectedTestId,
            ])
        </x-card>

        <div class="mt-4 flex justify-end gap-2">
            <x-button :href="route('laboratory.parameters.index')">Cancel</x-button>
            <x-button type="submit" variant="primary">Create parameter</x-button>
        </div>
    </form>

</x-layouts.admin>
