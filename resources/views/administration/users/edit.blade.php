<x-layouts.admin :title="'Edit '.$user->name" :breadcrumbs="['Administration' => null, 'Users' => route('administration.users.index'), $user->name => route('administration.users.show', $user), 'Edit' => null]">

    <x-page-header
        :title="'Edit '.$user->name"
        subtitle="Change the account details, role or status."
        :back="route('administration.users.show', $user)"
        back-label="Back to user"
    />

    <form method="POST" action="{{ route('administration.users.update', $user) }}">
        @csrf
        @method('PUT')

        <x-card title="Account" icon="profile">
            <div class="space-y-4">
                @include('administration.users.partials.form', ['user' => $user, 'roles' => $roles, 'canChangeRole' => $canChangeRole])
            </div>
        </x-card>

        <div class="mt-4 flex justify-end gap-2">
            <x-button :href="route('administration.users.show', $user)">Cancel</x-button>
            <x-button type="submit" variant="primary">Save changes</x-button>
        </div>
    </form>

</x-layouts.admin>
