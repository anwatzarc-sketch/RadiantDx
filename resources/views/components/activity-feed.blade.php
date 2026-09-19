@props(['entries', 'empty' => 'No activity recorded yet.'])

@if ($entries->isEmpty())
    <p class="py-2 text-sm text-slate-500">{{ $empty }}</p>
@else
    <ol class="space-y-4">
        @foreach ($entries as $entry)
            <li class="flex gap-3">
                <span class="mt-1.5 size-2 shrink-0 rounded-full {{ $entry->action->toneClasses() }}" aria-hidden="true"></span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-slate-800">{{ $entry->action->label() }}</p>
                    @if ($entry->description)
                        <p class="text-sm text-slate-600">{{ $entry->description }}</p>
                    @endif
                    @if (! empty($entry->metadata['reason']))
                        <p class="mt-0.5 text-sm text-slate-600">
                            <span class="font-medium">Reason:</span> {{ $entry->metadata['reason'] }}
                        </p>
                    @endif
                    <p class="mt-0.5 text-xs text-slate-400">
                        {{ $entry->actorName() }} &middot;
                        <time datetime="{{ $entry->created_at?->toIso8601String() }}">
                            {{ $entry->created_at?->format('d M Y H:i') }}
                        </time>
                    </p>
                </div>
            </li>
        @endforeach
    </ol>
@endif
