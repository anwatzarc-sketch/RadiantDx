<x-layouts.admin title="New laboratory test" :breadcrumbs="['Laboratory catalogue' => null, 'Laboratory tests' => route('laboratory.tests.index'), 'New test' => null]">

    <x-page-header
        title="New laboratory test"
        subtitle="Define an investigation the laboratory can perform."
        :back="route('laboratory.tests.index')"
        back-label="Back to tests"
    />

    <form method="POST" action="{{ route('laboratory.tests.store') }}">
        @csrf

        <x-card title="Test information" icon="catalogue">
            @include('laboratory.tests.partials.form', ['test' => null, 'categories' => $categories])
        </x-card>

        <div class="mt-4 flex justify-end gap-2">
            <x-button :href="route('laboratory.tests.index')">Cancel</x-button>
            <x-button type="submit" variant="primary">Create test</x-button>
        </div>
    </form>

</x-layouts.admin>
