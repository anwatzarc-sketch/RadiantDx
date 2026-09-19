{{--
    Primary navigation.

    The structure below describes the whole product. What an individual sees is
    filtered by the permissions they hold: a group disappears once none of its
    entries are reachable. Hiding a link is a convenience only — the route
    itself is what enforces the permission.
--}}

@php
    $user = auth()->user();

    $groups = [
        [
            'label' => null,
            'items' => [
                [
                    'label' => 'Dashboard',
                    'icon' => 'dashboard',
                    'route' => 'dashboard',
                    'href' => route('dashboard'),
                    'permission' => 'dashboard.view',
                ],
            ],
        ],
        [
            'label' => 'Laboratory',
            'items' => [
                [
                    'label' => 'Requisitions',
                    'icon' => 'requisition',
                    'route' => 'laboratory.requisitions.*',
                    'href' => route('laboratory.requisitions.index'),
                    'permission' => 'laboratory.requisition.view',
                ],
                [
                    'label' => 'Results',
                    'icon' => 'beaker',
                    'route' => 'laboratory.results.*',
                    'href' => route('laboratory.results.index'),
                    'permission' => 'laboratory.result.view',
                ],
            ],
        ],
        [
            'label' => 'Laboratory catalogue',
            'items' => [
                [
                    'label' => 'Laboratory tests',
                    'icon' => 'catalogue',
                    'route' => 'laboratory.tests.*',
                    'href' => route('laboratory.tests.index'),
                    'permission' => 'laboratory.test.view',
                ],
                [
                    'label' => 'Panels',
                    'icon' => 'layers',
                    'route' => 'laboratory.panels.*',
                    'href' => route('laboratory.panels.index'),
                    'permission' => 'laboratory.panel.view',
                ],
                [
                    'label' => 'Test parameters',
                    'icon' => 'parameter',
                    'route' => 'laboratory.parameters.*',
                    'href' => route('laboratory.parameters.index'),
                    'permission' => 'laboratory.parameter.view',
                ],
            ],
        ],
        [
            'label' => 'Administration',
            'items' => [
                [
                    'label' => 'Users',
                    'icon' => 'users',
                    'route' => 'administration.users.*',
                    'href' => route('administration.users.index'),
                    'permission' => 'users.view',
                ],
                [
                    'label' => 'Roles',
                    'icon' => 'role',
                    'route' => 'administration.roles.*',
                    'href' => route('administration.roles.index'),
                    'permission' => 'roles.view',
                ],
            ],
        ],
    ];

    $visibleGroups = collect($groups)
        ->map(function (array $group) use ($user): array {
            $group['items'] = array_values(array_filter(
                $group['items'],
                fn (array $item): bool => $user->hasPermission($item['permission']),
            ));

            return $group;
        })
        ->filter(fn (array $group): bool => $group['items'] !== [])
        ->values();
@endphp

<nav class="flex h-full flex-col gap-1 overflow-y-auto px-3 py-4" aria-label="Main navigation">
    @forelse ($visibleGroups as $group)
        @if ($group['label'])
            <p class="mt-4 px-3 pb-1 text-[0.68rem] font-semibold tracking-wider text-brand-200/70 uppercase">
                {{ $group['label'] }}
            </p>
        @endif

        @foreach ($group['items'] as $item)
            @php($isActive = request()->routeIs($item['route']))
            <a
                href="{{ $item['href'] }}"
                @class([
                    'flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium transition',
                    'bg-white/15 text-white shadow-inner' => $isActive,
                    'text-brand-100/85 hover:bg-white/10 hover:text-white' => ! $isActive,
                ])
                @if ($isActive) aria-current="page" @endif
            >
                <x-icon :name="$item['icon']" class="size-[1.15rem] shrink-0" />
                <span class="truncate">{{ $item['label'] }}</span>
            </a>
        @endforeach
    @empty
        <p class="px-3 py-2 text-sm text-brand-100/70">
            No areas are available with your current permissions.
        </p>
    @endforelse
</nav>
