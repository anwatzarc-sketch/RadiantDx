<x-layouts.admin :title="'Edit range · '.$parameter->name" :breadcrumbs="['Laboratory catalogue' => null, 'Test parameters' => route('laboratory.parameters.index'), $parameter->name => route('laboratory.parameters.show', $parameter), 'Edit range' => null]">

    <x-page-header
        title="Edit reference range"
        :subtitle="$range->resultLabel().' · '.$parameter->name"
        :back="route('laboratory.parameters.show', $parameter)"
        back-label="Back to parameter"
    >
        <x-slot:meta>
            @if ($range->is_placeholder)
                <x-badge classes="bg-amber-100 text-amber-900 ring-amber-600/30">Placeholder</x-badge>
            @else
                <x-badge classes="bg-emerald-100 text-emerald-800 ring-emerald-600/20" icon="check-circle">
                    Verified by {{ $range->actorDisplayName('verified_by') }} on {{ $range->verified_at?->format('d M Y') }}
                </x-badge>
            @endif
            @unless ($range->is_active)
                <x-badge>Inactive</x-badge>
            @endunless
        </x-slot:meta>
    </x-page-header>

    <form method="POST" action="{{ route('laboratory.parameters.ranges.update', [$parameter, $range]) }}">
        @csrf
        @method('PUT')

        <x-card title="Reference range" icon="sliders">
            @include('laboratory.parameters.ranges.partials.form')
        </x-card>

        <div class="mt-4 flex justify-end gap-2">
            <x-button :href="route('laboratory.parameters.show', $parameter)">Cancel</x-button>
            <x-button type="submit" variant="primary" icon="check">Save and verify</x-button>
        </div>
    </form>

</x-layouts.admin>
