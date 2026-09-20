@props([
    'staff',
    'compact' => false,
])

{{--
    Photo chooser with a visible crop region.

    The person sees the picture they just chose and decides which part of it is
    kept, instead of a centre crop being decided for them — a photograph with
    the face off to one side is the ordinary case.

    Only the crop rectangle is sent. The file itself is uploaded untouched and
    the server decodes, crops and re-encodes it, so the browser never becomes
    the authority on what is stored.
--}}

<div
    x-data="photoCropper({ viewport: {{ $compact ? 200 : 256 }} })"
    class="w-full"
>
    <form method="POST" action="{{ route('administration.staff.photo.store', $staff) }}"
          enctype="multipart/form-data" class="space-y-4">
        @csrf

        {{-- Written by the cropper; absent without JavaScript, and the server
             falls back to a centre crop. --}}
        <input type="hidden" name="crop_x" x-ref="cropX" />
        <input type="hidden" name="crop_y" x-ref="cropY" />
        <input type="hidden" name="crop_size" x-ref="cropSize" />

        {{-- Current photo, shown until a new file is chosen --}}
        <div class="flex flex-col items-center gap-3" x-show="! hasImage">
            <x-staff-avatar :staff="$staff" :size="$compact ? 'lg' : 'xl'" />
            <p class="text-center text-xs text-slate-500">
                {{ $staff->photo_path ? 'Current photo' : 'No photo yet' }}
            </p>
        </div>

        {{-- Crop viewport --}}
        <div x-show="hasImage" x-cloak class="flex flex-col items-center gap-3">
            <div
                class="relative overflow-hidden rounded-full bg-slate-100 ring-1 ring-slate-200 select-none"
                :style="`width: ${viewport}px; height: ${viewport}px`"
                x-on:mousedown="startDrag($event)"
                x-on:mousemove.window="drag($event)"
                x-on:mouseup.window="endDrag()"
                x-on:touchstart="startDrag($event)"
                x-on:touchmove.window.passive="drag($event)"
                x-on:touchend.window="endDrag()"
                :class="dragging ? 'cursor-grabbing' : 'cursor-grab'"
            >
                <img :src="src" alt="" draggable="false"
                     class="max-w-none origin-top-left"
                     :style="imageStyle" />

                {{-- The ring is the visible boundary of what will be kept. --}}
                <div class="pointer-events-none absolute inset-0 rounded-full ring-2 ring-white/80 ring-inset"></div>
            </div>

            <p class="text-center text-xs text-slate-500">
                Drag to reposition. Everything inside the circle is kept.
            </p>

            <label class="w-full">
                <span class="field-label">Zoom</span>
                <input
                    type="range" min="1" max="4" step="0.01"
                    x-model.number="zoom"
                    x-on:input="onZoom()"
                    class="w-full accent-brand-700"
                />
            </label>

            <x-button type="button" variant="ghost" size="sm" x-on:click="centre(); sync()">
                Re-centre
            </x-button>
        </div>

        <x-form.field
            name="photo"
            label="{{ $staff->photo_path ? 'Replace photo' : 'Upload a photo' }}"
            hint="JPEG, PNG or WebP, up to 4 MB."
        >
            <input
                type="file" name="photo" id="photo-{{ $staff->getKey() }}"
                accept="image/jpeg,image/png,image/webp" required
                x-on:change="pick($event)"
                class="field-control"
            />
        </x-form.field>

        <x-button type="submit" variant="primary" icon="check" class="w-full">
            Save photo
        </x-button>
    </form>

    @if ($staff->photo_path)
        <form method="POST" action="{{ route('administration.staff.photo.destroy', $staff) }}" class="mt-3">
            @csrf
            @method('DELETE')
            <x-button type="submit" variant="secondary" icon="trash" class="w-full">Remove photo</x-button>
        </form>
    @endif
</div>
