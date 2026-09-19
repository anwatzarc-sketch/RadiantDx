<x-layouts.admin title="New user" :breadcrumbs="['Administration' => null, 'Users' => route('administration.users.index'), 'New user' => null]">

    <x-page-header
        title="New user"
        subtitle="Create an account and give it a role."
        :back="route('administration.users.index')"
        back-label="Back to users"
    />

    <form method="POST" action="{{ route('administration.users.store') }}">
        @csrf

        <x-card title="Account" icon="profile">
            <div class="space-y-4">
                @include('administration.users.partials.form', ['user' => null, 'roles' => $roles, 'canChangeRole' => true])
            </div>
        </x-card>

        <div class="mt-4 flex justify-end gap-2">
            <x-button :href="route('administration.users.index')">Cancel</x-button>
            <x-button type="submit" variant="primary">Create user</x-button>
        </div>
    </form>

</x-layouts.admin>
