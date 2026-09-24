<x-layouts.admin :title="'Add range · '.$parameter->name" :breadcrumbs="['Laboratory catalogue' => null, 'Test parameters' => route('laboratory.parameters.index'), $parameter->name => route('laboratory.parameters.show', $parameter), 'Add range' => null]">

    <x-page-header
        title="Add reference range"
        :subtitle="$parameter->name.' · '.$parameter->test->name"
        :back="route('laboratory.parameters.show', $parameter)"
        back-label="Back to parameter"
    />

    <form method="POST" action="{{ route('laboratory.parameters.ranges.store', $parameter) }}">
        @csrf

        <x-card title="Reference range" icon="sliders">
            @include('laboratory.parameters.ranges.partials.form')
        </x-card>

        <div class="mt-4 flex justify-end gap-2">
            <x-button :href="route('laboratory.parameters.show', $parameter)">Cancel</x-button>
            <x-button type="submit" variant="primary" icon="check">Save and verify</x-button>
        </div>
    </form>

</x-layouts.admin>
