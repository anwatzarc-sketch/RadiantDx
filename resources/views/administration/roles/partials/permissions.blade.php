@props([
    'permissionModules',
    'assigned' => [],
    'readonly' => false,
])

{{--
    The permission matrix, grouped by module exactly as the catalogue declares
    it. Each group can select or clear all of its own permissions.
--}}

@php($current = old('permissions', $assigned))

<div class="space-y-4" x-data="{
    toggleModule(module, checked) {
        this.$refs[module]?.querySelectorAll('input[type=checkbox]').forEach((input) => { input.checked = checked });
    }
}">
    @foreach ($permissionModules as $module => $permissions)
        @php($moduleKey = Str::slug($module))

        <fieldset class="rounded-lg border border-slate-200">
            <legend class="sr-only">{{ $module }}</legend>

            <div class="flex items-center justify-between gap-3 border-b border-slate-200 bg-slate-50 px-4 py-2.5">
                <p class="text-sm font-semibold text-slate-800">{{ $module }}</p>
                @unless ($readonly)
                    <div class="flex items-center gap-1 text-xs">
                        <button type="button" class="rounded px-2 py-1 font-medium text-brand-700 hover:bg-brand-50"
                                x-on:click="toggleModule('{{ $moduleKey }}', true)">Select all</button>
                        <button type="button" class="rounded px-2 py-1 font-medium text-slate-500 hover:bg-slate-100"
                                x-on:click="toggleModule('{{ $moduleKey }}', false)">Clear</button>
                    </div>
                @endunless
            </div>

            <div x-ref="{{ $moduleKey }}" class="grid grid-cols-1 gap-x-6 gap-y-2 px-4 py-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($permissions as $permission)
                    <label class="flex cursor-pointer items-start gap-2.5 {{ $readonly ? 'cursor-default' : '' }}">
                        <input
                            type="checkbox"
                            name="permissions[]"
                            value="{{ $permission->name }}"
                            @checked(in_array($permission->name, $current, true))
                            @disabled($readonly)
                            class="mt-0.5 size-4 shrink-0 rounded border-slate-300 text-brand-700 focus:ring-brand-600
                                   disabled:cursor-not-allowed disabled:opacity-70"
                        />
                        <span class="text-sm">
                            <span class="block font-medium text-slate-700">{{ $permission->label }}</span>
                            <span class="block font-mono text-[0.7rem] text-slate-400">{{ $permission->name }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
        </fieldset>
    @endforeach
</div>
