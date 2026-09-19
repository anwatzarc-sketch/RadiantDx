<x-layouts.admin :title="'Edit '.$requisition->requisition_number" :breadcrumbs="['Laboratory' => null, 'Requisitions' => route('laboratory.requisitions.index'), $requisition->requisition_number => route('laboratory.requisitions.show', $requisition), 'Edit' => null]">

    <x-page-header
        :title="'Edit '.$requisition->requisition_number"
        subtitle="A requisition can only be changed while it is still a draft."
        :back="route('laboratory.requisitions.show', $requisition)"
        back-label="Back to requisition"
    >
        <x-slot:meta>
            <x-status-badge :status="$requisition->status" />
            <x-status-badge :status="$requisition->priority" />
        </x-slot:meta>
    </x-page-header>

    <form method="POST" action="{{ route('laboratory.requisitions.update', $requisition) }}">
        @csrf
        @method('PUT')

        @include('laboratory.requisitions.partials.form', [
            'requisition' => $requisition,
            'availableTests' => $availableTests,
            'availablePanels' => $availablePanels,
            'selected' => $selected,
        ])

        <div class="mt-6 flex flex-wrap items-center justify-end gap-2">
            <x-button :href="route('laboratory.requisitions.show', $requisition)">Cancel</x-button>
            <x-button type="submit" name="action" value="draft" variant="secondary">Save draft</x-button>
            @can('submit', $requisition)
                <x-button type="submit" name="action" value="submit" variant="primary" icon="check">
                    Submit to laboratory
                </x-button>
            @endcan
        </div>
    </form>

</x-layouts.admin>
