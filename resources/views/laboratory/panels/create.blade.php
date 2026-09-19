<x-layouts.admin title="New panel" :breadcrumbs="['Laboratory catalogue' => null, 'Panels' => route('laboratory.panels.index'), 'New panel' => null]">

    <x-page-header
        title="New panel"
        subtitle="A panel groups related tests so they can be requested as one item."
        :back="route('laboratory.panels.index')"
        back-label="Back to panels"
    />

    <form method="POST" action="{{ route('laboratory.panels.store') }}">
        @csrf

        <x-card title="Panel information" icon="layers">
            @include('laboratory.panels.partials.form', [
                'panel' => null,
                'availableTests' => $availableTests,
                'selectedTests' => $selectedTests,
                'categories' => $categories,
            ])
        </x-card>

        <div class="mt-4 flex justify-end gap-2">
            <x-button :href="route('laboratory.panels.index')">Cancel</x-button>
            <x-button type="submit" variant="primary">Create panel</x-button>
        </div>
    </form>

</x-layouts.admin>
