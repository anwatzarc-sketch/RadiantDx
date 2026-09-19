@props(['role' => null])

<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <x-form.field name="name" label="Role name" required>
        <x-form.input name="name" :value="$role?->name" required />
    </x-form.field>

    <x-form.field name="slug" label="Slug" hint="Lower case letters, numbers and hyphens. Derived from the name if left blank.">
        <x-form.input name="slug" :value="$role?->slug" :disabled="$role?->isSuperAdmin() ?? false" />
    </x-form.field>
</div>

<x-form.field name="description" label="Description" hint="What this role is for.">
    <x-form.textarea name="description" :value="$role?->description" rows="2" />
</x-form.field>

<x-form.checkbox
    name="is_active"
    label="Role is active"
    hint="Users holding an inactive role lose their permissions."
    :checked="$role?->is_active ?? true"
/>
