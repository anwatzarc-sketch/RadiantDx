<?php

declare(strict_types=1);

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Services\Administration\StaffImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Staff CSV import: upload, preview, then commit.
 *
 * The preview is not optional. An import that silently created forty records
 * and rejected three is very hard to unpick afterwards, so the administrator
 * sees the outcome before anything is written.
 *
 * The uploaded file is held on the private disk between the two steps and
 * removed once it has been applied.
 */
class StaffImportController extends Controller
{
    public function __construct(private readonly StaffImporter $importer) {}

    public function create(): View
    {
        $this->authorize('import', Staff::class);

        return view('administration.staff.import', [
            'plan' => null,
            'token' => null,
            'columns' => StaffImporter::COLUMNS,
        ]);
    }

    /** Step 1: analyse the upload and show what it would do. */
    public function preview(Request $request): View
    {
        $this->authorize('import', Staff::class);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ], [
            'file.mimes' => 'Upload a CSV file.',
        ]);

        // Kept on the private disk: a staff list is personal data and must not
        // be reachable by URL.
        $token = $request->file('file')->store('staff-imports');

        return view('administration.staff.import', [
            'plan' => $this->importer->analyse(storage_path('app/private/'.$token)),
            'token' => $token,
            'columns' => StaffImporter::COLUMNS,
        ]);
    }

    /** Step 2: apply the file that was previewed. */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('import', Staff::class);

        $validated = $request->validate([
            // A plain filename under the import directory; nothing else.
            'token' => ['required', 'string', 'regex:/^staff-imports\/[A-Za-z0-9]+\.(csv|txt)$/'],
        ]);

        $path = storage_path('app/private/'.$validated['token']);

        if (! is_file($path)) {
            return redirect()
                ->route('administration.staff.import.create')
                ->withErrors(['file' => 'That upload has expired. Please upload the file again.']);
        }

        $summary = $this->importer->commit($path, $request->user());

        @unlink($path);

        return redirect()
            ->route('administration.staff.index')
            ->with('success', sprintf(
                'Import complete: %d created, %d updated, %d rejected.',
                $summary['created'],
                $summary['updated'],
                $summary['rejected'],
            ));
    }
}
