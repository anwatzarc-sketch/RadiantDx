@props(['name', 'label', 'hint' => null, 'checked' => false, 'value' => 1])

<label class="flex cursor-pointer items-start gap-2.5">
    <input type="hidden" name="{{ $name }}" value="0" />
    <input
        type="checkbox"
        name="{{ $name }}"
        id="{{ $name }}"
        value="{{ $value }}"
        @checked((bool) old($name, $checked))
        {{ $attributes->merge(['class' => 'mt-0.5 size-4 shrink-0 rounded border-slate-300 text-brand-700 focus:ring-brand-600']) }}
    />
    <span class="text-sm">
        <span class="font-medium text-slate-700">{{ $label }}</span>
        @if ($hint)
            <span class="block text-xs text-slate-500">{{ $hint }}</span>
        @endif
    </span>
</label>
