<x-layouts.admin
    :title="'Edit '.$staff->full_name"
    :breadcrumbs="['Administration' => null, 'Staff' => route('administration.staff.index'), $staff->staff_code => route('administration.staff.show', $staff), 'Edit' => null]"
    :back="route('administration.staff.show', $staff)"
    back-label="Back to profile"
>
    <x-page-header
        :title="'Edit '.$staff->full_name"
        :subtitle="$staff->staff_code"
    />

    <form method="POST" action="{{ route('administration.staff.update', $staff) }}" class="space-y-6">
        @csrf
        @method('PUT')

        @include('administration.staff.partials.form', ['staff' => $staff])

        <div class="flex items-center justify-end gap-3">
            <x-button :href="route('administration.staff.show', $staff)" variant="secondary">Cancel</x-button>
            <x-button type="submit" variant="primary" icon="check">Save changes</x-button>
        </div>
    </form>
</x-layouts.admin>
