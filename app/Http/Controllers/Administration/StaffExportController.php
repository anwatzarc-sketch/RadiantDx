<?php

declare(strict_types=1);

namespace App\Http\Controllers\Administration;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV export of the staff list.
 *
 * Exports exactly what the screen is showing: it reuses StaffController's query
 * builder, so the filters in the URL apply and the file cannot quietly contain
 * more than the administrator was looking at.
 *
 * Streamed in chunks rather than collected, because this is specified to work
 * against tens of thousands of records and building that in memory to hand it
 * to a browser is how an export takes a server down.
 *
 * The export itself is audited: a downloaded copy of the staff directory leaves
 * the application's access controls behind, so there is a record of who took
 * one.
 */
class StaffExportController extends Controller
{
    private const COLUMNS = [
        'staff_id', 'full_name', 'title', 'gender', 'profession', 'speciality',
        'sub_speciality', 'department', 'unit', 'position', 'employee_id',
        'employment_type', 'status', 'phone', 'email', 'professional_license',
        'license_expiry', 'joined_on', 'account_email', 'account_active',
    ];

    public function __construct(
        private readonly StaffController $staff,
        private readonly AuditLogger $audit,
    ) {}

    public function __invoke(Request $request): StreamedResponse
    {
        $this->authorize('export', Staff::class);

        $query = $this->staff->filtered($request);
        $total = (clone $query)->count();

        $this->audit->record(
            AuditAction::StaffExported,
            null,
            "Staff directory exported ({$total} records).",
            ['filters' => $request->only(['search', 'profession', 'speciality', 'department', 'status', 'account']), 'count' => $total],
            $request->user(),
        );

        $filename = 'staff-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'wb');

            fputcsv($handle, self::COLUMNS);

            $query->chunk(500, function ($records) use ($handle): void {
                foreach ($records as $staff) {
                    fputcsv($handle, [
                        $staff->staff_id,
                        $staff->full_name,
                        $staff->title,
                        $staff->gender?->label(),
                        $staff->profession?->label(),
                        $staff->speciality?->label(),
                        $staff->sub_speciality?->label(),
                        $staff->department?->name,
                        $staff->unit?->name,
                        $staff->position?->label(),
                        $staff->employee_id,
                        $staff->employment_type?->label(),
                        $staff->status->label(),
                        $staff->phone,
                        $staff->email,
                        $staff->professional_license,
                        $staff->license_expiry?->format('Y-m-d'),
                        $staff->joined_on?->format('Y-m-d'),
                        $staff->user?->email,
                        $staff->user === null ? '' : ($staff->user->is_active ? 'yes' : 'no'),
                    ]);
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=utf-8',
        ]);
    }
}
