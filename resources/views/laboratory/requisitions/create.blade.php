<x-layouts.admin title="New requisition" :breadcrumbs="['Laboratory' => null, 'Requisitions' => route('laboratory.requisitions.index'), 'New requisition' => null]">

    <x-page-header
        title="New laboratory requisition"
        subtitle="Record the patient, the request and the investigations to perform."
        :back="route('laboratory.requisitions.index')"
        back-label="Back to requisitions"
    />

    <form method="POST" action="{{ route('laboratory.requisitions.store') }}">
        @csrf

        @include('laboratory.requisitions.partials.form', [
            'requisition' => $requisition,
            'availableTests' => $availableTests,
            'availablePanels' => $availablePanels,
            'selected' => $selected,
        ])

        <div class="mt-6 flex flex-wrap items-center justify-end gap-2">
            <x-button :href="route('laboratory.requisitions.index')">Cancel</x-button>
            <x-button type="submit" name="action" value="draft" variant="secondary">Save draft</x-button>
            @can('laboratory.requisition.submit')
                <x-button type="submit" name="action" value="submit" variant="primary" icon="check">
                    Submit to laboratory
                </x-button>
            @endcan
        </div>
    </form>

</x-layouts.admin>
