<x-layouts.admin
    title="Add staff"
    :breadcrumbs="['Administration' => null, 'Staff' => route('administration.staff.index'), 'Add' => null]"
>
    <x-page-header
        title="Add staff"
        subtitle="The staff identifier is issued automatically once the record is saved."
        :back="route('administration.staff.index')"
        back-label="Back to staff"
    />

    <form method="POST" action="{{ route('administration.staff.store') }}" class="space-y-6">
        @csrf

        @include('administration.staff.partials.form', ['staff' => null])

        <div class="flex items-center justify-end gap-3">
            <x-button :href="route('administration.staff.index')" variant="secondary">Cancel</x-button>
            <x-button type="submit" variant="primary" icon="check">Create staff record</x-button>
        </div>
    </form>
</x-layouts.admin>
