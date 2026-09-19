<x-layouts.admin :title="'Edit '.$test->name" :breadcrumbs="['Laboratory catalogue' => null, 'Laboratory tests' => route('laboratory.tests.index'), $test->name => route('laboratory.tests.show', $test), 'Edit' => null]">

    <x-page-header
        :title="'Edit '.$test->name"
        :subtitle="'Code '.$test->code"
        :back="route('laboratory.tests.show', $test)"
        back-label="Back to test"
    />

    <form method="POST" action="{{ route('laboratory.tests.update', $test) }}">
        @csrf
        @method('PUT')

        <x-card title="Test information" icon="catalogue">
            @include('laboratory.tests.partials.form', ['test' => $test, 'categories' => $categories])
        </x-card>

        <div class="mt-4 flex justify-end gap-2">
            <x-button :href="route('laboratory.tests.show', $test)">Cancel</x-button>
            <x-button type="submit" variant="primary">Save changes</x-button>
        </div>
    </form>

</x-layouts.admin>
