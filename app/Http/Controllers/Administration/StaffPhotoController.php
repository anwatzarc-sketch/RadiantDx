<?php

declare(strict_types=1);

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Services\Administration\StaffPhotoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Upload, removal and delivery of staff profile photographs.
 *
 * Photographs are held on the private disk, so `show()` is the only way to
 * reach one and it checks authorisation first. The alternative — the public
 * disk with a symlink — would make every staff photograph readable by anyone
 * who guessed the path.
 *
 * Which record is being changed always comes from the route. There is no field
 * anywhere in this flow naming a staff record.
 */
class StaffPhotoController extends Controller
{
    public function __construct(private readonly StaffPhotoService $photos) {}

    public function store(Request $request, Staff $staff): RedirectResponse
    {
        $this->authorize('managePhoto', $staff);

        $request->validate([
            // A first pass only. The real decision is made in the service by
            // decoding the bytes; a mimes rule reads the extension and the
            // browser's claim, neither of which is evidence.
            'photo' => ['required', 'file', 'max:4096'],

            // The region the person chose. Optional: a client without
            // JavaScript sends none and gets a centre crop. Validated for shape
            // only — the service clamps the values to the real image, because
            // nothing here knows the dimensions yet.
            'crop_x' => ['nullable', 'integer', 'min:0'],
            'crop_y' => ['nullable', 'integer', 'min:0'],
            'crop_size' => ['nullable', 'integer', 'min:1'],
        ], [
            'photo.required' => 'Choose an image to upload.',
            'photo.max' => 'The image must be 4 MB or smaller.',
        ]);

        $this->photos->store(
            $staff,
            $request->file('photo'),
            $request->user(),
            $this->cropFrom($request),
        );

        return back()->with('success', "Profile photo updated for {$staff->full_name}.");
    }

    /**
     * The chosen crop, or null when the request did not carry a complete one.
     *
     * @return array{x: int, y: int, size: int}|null
     */
    private function cropFrom(Request $request): ?array
    {
        if (! $request->filled(['crop_x', 'crop_y', 'crop_size'])) {
            return null;
        }

        return [
            'x' => $request->integer('crop_x'),
            'y' => $request->integer('crop_y'),
            'size' => $request->integer('crop_size'),
        ];
    }

    public function destroy(Request $request, Staff $staff): RedirectResponse
    {
        $this->authorize('managePhoto', $staff);

        $this->photos->remove($staff, $request->user());

        return back()->with('success', "Profile photo removed for {$staff->full_name}.");
    }

    /**
     * Serves the stored photograph to anyone allowed to view the staff record.
     *
     * A missing photograph is a 404 rather than a placeholder image: the
     * interface renders an initials tile when there is nothing stored, so it
     * never asks for one that does not exist.
     */
    public function show(Staff $staff): StreamedResponse
    {
        $this->authorize('view', $staff);

        abort_unless($this->photos->exists($staff), Response::HTTP_NOT_FOUND);

        return Storage::disk('local')->response(
            $staff->photo_path,
            "staff-{$staff->staff_id}.jpg",
            [
                'Content-Type' => 'image/jpeg',
                // Personal data: cacheable by the browser that fetched it,
                // never by a shared proxy.
                'Cache-Control' => 'private, max-age=300',
            ],
            'inline',
        );
    }
}
