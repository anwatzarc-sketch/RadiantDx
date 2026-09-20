<x-layouts.admin
    title="Import staff"
    :breadcrumbs="['Administration' => null, 'Staff' => route('administration.staff.index'), 'Import' => null]"
>
    <x-page-header
        title="Import staff"
        subtitle="Upload a CSV, review exactly what it would do, then commit."
        :back="route('administration.staff.index')"
        back-label="Back to staff"
    />

    <x-card title="Upload">
        <form method="POST" action="{{ route('administration.staff.import.preview') }}" enctype="multipart/form-data"
              class="space-y-4">
            @csrf

            <x-form.field name="file" label="CSV file" required
                          hint="Maximum 2 MB. The first row must be the column headings.">
                <input type="file" name="file" id="file" accept=".csv,text/csv" required
                       class="field-control" />
            </x-form.field>

            <x-button type="submit" variant="primary" icon="search">Analyse file</x-button>
        </form>

        <div class="mt-6 rounded-lg bg-slate-50 p-4">
            <p class="text-xs font-semibold tracking-wider text-slate-600 uppercase">Recognised columns</p>
            <p class="mt-2 font-mono text-xs break-words text-slate-600">{{ implode(', ', $columns) }}</p>

            <p class="mt-4 text-xs text-slate-600">
                Two things this import will not do. It never creates sign-in accounts — those are
                created one at a time from a staff profile, because a spreadsheet that has been
                emailed around should not be able to mint credentials. And it ignores any
                <span class="font-mono">staff_id</span> column: identifiers are issued by the
                system, never taken from a file. An existing record is matched on
                <span class="font-mono">employee_id</span>.
            </p>
        </div>
    </x-card>

    @if ($plan !== null)
        @php
            $createCount = count($plan['create']);
            $updateCount = count($plan['update']);
            $rejectCount = count($plan['rejected']);
        @endphp

        <x-card title="Preview" :subtitle="$createCount.' to create · '.$updateCount.' to update · '.$rejectCount.' rejected'">
            @if ($createCount === 0 && $updateCount === 0)
                <p class="py-4 text-sm text-slate-600">
                    Nothing in this file can be imported. Fix the rejected rows below and upload it again.
                </p>
            @else
                <form method="POST" action="{{ route('administration.staff.import.store') }}" class="mb-6">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}" />
                    <x-button type="submit" variant="primary" icon="check">
                        Commit {{ $createCount + $updateCount }} record(s)
                    </x-button>
                </form>
            @endif

            @if ($rejectCount > 0)
                <div class="overflow-x-auto">
                    <p class="mb-2 text-xs font-semibold tracking-wider text-rose-700 uppercase">
                        Rejected rows — these will not be imported
                    </p>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th scope="col">Line</th>
                                <th scope="col">Name</th>
                                <th scope="col">Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($plan['rejected'] as $row)
                                <tr>
                                    <td class="font-mono text-xs">{{ $row['line'] }}</td>
                                    <td>{{ $row['name'] }}</td>
                                    <td class="text-rose-700">{{ $row['reason'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @foreach ([['create', 'To create', $plan['create']], ['update', 'To update', $plan['update']]] as [$key, $heading, $rows])
                @if (count($rows) > 0)
                    <div class="mt-6 overflow-x-auto">
                        <p class="mb-2 text-xs font-semibold tracking-wider text-slate-600 uppercase">{{ $heading }}</p>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th scope="col">Line</th>
                                    <th scope="col">Name</th>
                                    <th scope="col">Staff ID</th>
                                    <th scope="col">Profession</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rows as $row)
                                    <tr>
                                        <td class="font-mono text-xs">{{ $row['line'] }}</td>
                                        <td>{{ $row['name'] }}</td>
                                        <td class="font-mono text-xs">
                                            {{ $row['staff_id'] ?? 'issued on commit' }}
                                        </td>
                                        <td>{{ $row['attributes']['profession'] ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @endforeach
        </x-card>
    @endif
</x-layouts.admin>
