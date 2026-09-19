<x-layouts.admin :title="'Edit '.$panel->name" :breadcrumbs="['Laboratory catalogue' => null, 'Panels' => route('laboratory.panels.index'), $panel->name => route('laboratory.panels.show', $panel), 'Edit' => null]">

    <x-page-header
        :title="'Edit '.$panel->name"
        :subtitle="'Code '.$panel->code"
        :back="route('laboratory.panels.show', $panel)"
        back-label="Back to panel"
    />

    <form method="POST" action="{{ route('laboratory.panels.update', $panel) }}">
        @csrf
        @method('PUT')

        <x-card title="Panel information" icon="layers">
            @include('laboratory.panels.partials.form', [
                'panel' => $panel,
                'availableTests' => $availableTests,
                'selectedTests' => $selectedTests,
                'categories' => $categories,
            ])
        </x-card>

        <div class="mt-4 flex justify-end gap-2">
            <x-button :href="route('laboratory.panels.show', $panel)">Cancel</x-button>
            <x-button type="submit" variant="primary">Save changes</x-button>
        </div>
    </form>

</x-layouts.admin>
