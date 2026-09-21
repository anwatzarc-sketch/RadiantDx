<?php

declare(strict_types=1);

namespace App\Services\Administration;

use App\Enums\AuditAction;
use App\Exceptions\WorkflowViolationException;
use App\Models\Staff;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Staff profile photographs.
 *
 * ---------------------------------------------------------------------------
 * Why an upload is re-encoded rather than stored
 * ---------------------------------------------------------------------------
 * The file is never kept as it arrived. It is decoded with GD and written out
 * again from the decoded pixels, which is what actually defeats this class of
 * attack rather than merely checking for it:
 *
 *  - a PHP script renamed .jpg does not decode, so it never reaches disk;
 *  - a polyglot — a valid image with executable content appended or embedded in
 *    a comment segment — loses everything that is not pixels;
 *  - EXIF is discarded, which also removes the GPS coordinates that phone
 *    photographs routinely carry;
 *  - SVG is refused outright. It is a document format that can carry script,
 *    and there is no reason for a profile photograph to be one.
 *
 * The declared MIME type and the file extension are both ignored for the
 * decision; only what `getimagesize()` reports about the bytes is trusted.
 *
 * Files live on the private disk and are served by an authorised controller,
 * so a staff photograph is not reachable by guessing a URL. The path is built
 * from the record's primary key as loaded from the route, never from anything
 * a caller supplied.
 */
class StaffPhotoService
{
    /** Formats accepted, keyed by what getimagesize() reports. */
    private const ACCEPTED = [
        IMAGETYPE_JPEG => 'jpeg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_WEBP => 'webp',
    ];

    private const MAX_BYTES = 4 * 1024 * 1024;

    /** Guards against a decompression bomb: a small file describing a huge image. */
    private const MAX_SOURCE_DIMENSION = 6000;

    private const MIN_SOURCE_DIMENSION = 48;

    /** Stored square; more than enough for a letterhead or a directory row. */
    private const OUTPUT_SIZE = 512;

    private const OUTPUT_QUALITY = 82;

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Validates, re-encodes and stores a new photograph.
     *
     * @param  array{x: int, y: int, size: int}|null  $crop  the region the person
     *                                                       chose, in the image's own pixels. Treated as a preference and
     *                                                       clamped to the real bounds — a crop arriving from a browser is
     *                                                       input like any other. Null centre-crops.
     * @return string the stored path, relative to the private disk
     *
     * @throws WorkflowViolationException when the upload is not a usable image
     */
    public function store(Staff $staff, UploadedFile $file, User $actor, ?array $crop = null): string
    {
        $source = $this->decode($file);

        try {
            $encoded = $this->reEncode($source, $crop);
        } finally {
            imagedestroy($source);
        }

        return DB::transaction(function () use ($staff, $encoded, $actor): string {
            $previous = $staff->photo_path;

            // Keyed on the primary key of the record the router resolved, so a
            // caller cannot steer where the file lands.
            $path = "staff/{$staff->getKey()}/photo.jpg";

            Storage::disk('local')->put($path, $encoded);

            $staff->forceFill(['photo_path' => $path])->save();

            // Only after the row is committed, and only when the name changed.
            if ($previous !== null && $previous !== $path) {
                Storage::disk('local')->delete($previous);
            }

            $this->audit->record(
                AuditAction::StaffUpdated,
                $staff,
                'Profile photo '.($previous === null ? 'added' : 'changed')." for staff {$staff->staff_code}.",
                ['photo' => $previous === null ? 'added' : 'changed'],
                $actor,
            );

            return $path;
        });
    }

    /**
     * Removes the photograph, returning the record to the default.
     *
     * The row is cleared before the file, so a failure to unlink leaves an
     * orphaned file rather than a profile pointing at something that is gone.
     */
    public function remove(Staff $staff, User $actor): void
    {
        $path = $staff->photo_path;

        if ($path === null) {
            return;
        }

        DB::transaction(function () use ($staff, $path, $actor): void {
            $staff->forceFill(['photo_path' => null])->save();

            Storage::disk('local')->delete($path);

            $this->audit->record(
                AuditAction::StaffUpdated,
                $staff,
                "Profile photo removed for staff {$staff->staff_code}.",
                ['photo' => 'removed'],
                $actor,
            );
        });
    }

    /** Whether a stored photograph actually exists on disk. */
    public function exists(Staff $staff): bool
    {
        return $staff->photo_path !== null
            && Storage::disk('local')->exists($staff->photo_path);
    }

    /**
     * Decodes an upload into a GD image, refusing anything that is not one.
     *
     * @throws WorkflowViolationException
     */
    private function decode(UploadedFile $file): \GdImage
    {
        if (! $file->isValid()) {
            throw WorkflowViolationException::because('The upload did not complete. Please try again.');
        }

        if ($file->getSize() > self::MAX_BYTES) {
            throw WorkflowViolationException::because('The image must be 4 MB or smaller.');
        }

        $path = $file->getRealPath();

        if ($path === false) {
            throw WorkflowViolationException::because('The upload could not be read.');
        }

        // The bytes decide, not the extension and not the browser's MIME type.
        $info = @getimagesize($path);

        if ($info === false) {
            throw WorkflowViolationException::because(
                'That file is not a readable image. Upload a JPEG, PNG or WebP photograph.'
            );
        }

        [$width, $height, $type] = $info;

        if (! isset(self::ACCEPTED[$type])) {
            throw WorkflowViolationException::because(
                'Only JPEG, PNG and WebP images are accepted. SVG and other document formats are not.'
            );
        }

        if ($width > self::MAX_SOURCE_DIMENSION || $height > self::MAX_SOURCE_DIMENSION) {
            throw WorkflowViolationException::because(
                'That image is too large in pixels. Please use one under 6000×6000.'
            );
        }

        if ($width < self::MIN_SOURCE_DIMENSION || $height < self::MIN_SOURCE_DIMENSION) {
            throw WorkflowViolationException::because('That image is too small to use as a profile photo.');
        }

        $image = match (self::ACCEPTED[$type]) {
            'jpeg' => @imagecreatefromjpeg($path),
            'png' => @imagecreatefrompng($path),
            'webp' => @imagecreatefromwebp($path),
        };

        // A file that passes getimagesize() but will not decode is malformed or
        // truncated, whatever its header claims.
        if (! $image instanceof \GdImage) {
            throw WorkflowViolationException::because('That image could not be read. It may be damaged.');
        }

        return $image;
    }

    /**
     * The square region to keep, as [x, y, side] in source pixels.
     *
     * A crop chosen in the browser is input, so it is clamped rather than
     * believed: the side is bounded by the smaller edge, and the origin is
     * bounded so the square cannot run past the image. A missing, malformed or
     * unusable crop falls back to the centre, which is what a client with no
     * JavaScript gets.
     *
     * @param  array{x: int, y: int, size: int}|null  $crop
     * @return array{0: int, 1: int, 2: int}
     */
    private function cropRegion(int $width, int $height, ?array $crop): array
    {
        $maxSide = min($width, $height);

        if ($crop === null) {
            return [(int) (($width - $maxSide) / 2), (int) (($height - $maxSide) / 2), $maxSide];
        }

        $side = (int) max(self::MIN_SOURCE_DIMENSION, min($maxSide, $crop['size']));

        $x = (int) max(0, min($width - $side, $crop['x']));
        $y = (int) max(0, min($height - $side, $crop['y']));

        return [$x, $y, $side];
    }

    /**
     * Writes the decoded pixels out as a square JPEG.
     *
     * Everything that is not a pixel is lost here — metadata, trailing bytes,
     * comment segments — which is the point.
     */
    private function reEncode(\GdImage $source, ?array $crop = null): string
    {
        $width = imagesx($source);
        $height = imagesy($source);

        [$sourceX, $sourceY, $side] = $this->cropRegion($width, $height, $crop);

        $canvas = imagecreatetruecolor(self::OUTPUT_SIZE, self::OUTPUT_SIZE);

        // Transparent source pixels flatten onto white rather than black.
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, self::OUTPUT_SIZE, self::OUTPUT_SIZE, $white);

        imagecopyresampled(
            $canvas,
            $source,
            0, 0,
            $sourceX, $sourceY,
            self::OUTPUT_SIZE, self::OUTPUT_SIZE,
            $side, $side,
        );

        ob_start();
        imagejpeg($canvas, null, self::OUTPUT_QUALITY);
        $encoded = (string) ob_get_clean();

        imagedestroy($canvas);

        return $encoded;
    }
}
