<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Staff;
use App\Services\Administration\StaffPhotoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesStaffUsers;
use Tests\TestCase;

/**
 * Staff profile photographs: upload, removal, delivery and the rejection of
 * files that are not images.
 */
class StaffPhotoTest extends TestCase
{
    use CreatesStaffUsers, RefreshDatabase;

    // ------------------------------------------------------------- happy path

    #[Test]
    public function a_jpeg_is_accepted_and_re_encoded(): void
    {
        $staff = Staff::factory()->create();

        $this->actingAs($this->superAdmin())
            ->post(route('administration.staff.photo.store', $staff), [
                'photo' => $this->image('jpeg', 800, 600),
            ])->assertRedirect();

        $staff->refresh();

        $this->assertSame("staff/{$staff->getKey()}/photo.jpg", $staff->photo_path);
        $this->assertTrue(Storage::disk('local')->exists($staff->photo_path));

        // Stored square regardless of the source aspect ratio.
        [$width, $height, $type] = getimagesizefromstring(
            Storage::disk('local')->get($staff->photo_path)
        );

        $this->assertSame(512, $width);
        $this->assertSame(512, $height);
        $this->assertSame(IMAGETYPE_JPEG, $type);
    }

    #[Test]
    public function png_and_webp_are_accepted(): void
    {
        foreach (['png', 'webp'] as $format) {
            $staff = Staff::factory()->create();

            $this->actingAs($this->superAdmin())
                ->post(route('administration.staff.photo.store', $staff), [
                    'photo' => $this->image($format, 400, 400),
                ])->assertRedirect();

            $this->assertNotNull($staff->fresh()->photo_path, "{$format} should be accepted.");
        }
    }

    #[Test]
    public function replacing_a_photo_does_not_leave_the_old_file_behind(): void
    {
        $staff = Staff::factory()->create();
        $admin = $this->superAdmin();

        $this->actingAs($admin)->post(route('administration.staff.photo.store', $staff), [
            'photo' => $this->image('jpeg', 300, 300),
        ])->assertRedirect();

        $first = Storage::disk('local')->get($staff->fresh()->photo_path);

        $this->actingAs($admin)->post(route('administration.staff.photo.store', $staff), [
            'photo' => $this->image('png', 600, 200),
        ])->assertRedirect();

        $second = Storage::disk('local')->get($staff->fresh()->photo_path);

        $this->assertNotSame($first, $second, 'The replacement should overwrite the stored image.');
        $this->assertCount(1, Storage::disk('local')->files("staff/{$staff->getKey()}"));
    }

    #[Test]
    public function removing_a_photo_clears_the_record_and_the_file(): void
    {
        $staff = Staff::factory()->create();
        $admin = $this->superAdmin();

        $this->actingAs($admin)->post(route('administration.staff.photo.store', $staff), [
            'photo' => $this->image('jpeg', 300, 300),
        ])->assertRedirect();

        $path = $staff->fresh()->photo_path;

        $this->actingAs($admin)
            ->delete(route('administration.staff.photo.destroy', $staff))
            ->assertRedirect();

        $this->assertNull($staff->fresh()->photo_path);
        $this->assertFalse(Storage::disk('local')->exists($path));
    }

    // ------------------------------------------------------------- rejections

    #[Test]
    public function a_php_script_renamed_as_a_jpeg_is_rejected(): void
    {
        $staff = Staff::factory()->create();

        $payload = UploadedFile::fake()->createWithContent(
            'avatar.jpg',
            "<?php system(\$_GET['c']); ?>",
        );

        $this->actingAs($this->superAdmin())
            ->post(route('administration.staff.photo.store', $staff), ['photo' => $payload])
            ->assertSessionHas('error');

        $this->assertNull($staff->fresh()->photo_path);
        $this->assertEmpty(Storage::disk('local')->files("staff/{$staff->getKey()}"));
    }

    #[Test]
    public function an_svg_is_rejected_even_when_well_formed(): void
    {
        $staff = Staff::factory()->create();

        $svg = UploadedFile::fake()->createWithContent(
            'avatar.svg',
            '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100"><script>alert(1)</script></svg>',
        );

        $this->actingAs($this->superAdmin())
            ->post(route('administration.staff.photo.store', $staff), ['photo' => $svg])
            ->assertSessionHas('error');

        $this->assertNull($staff->fresh()->photo_path);
    }

    #[Test]
    public function a_truncated_image_is_rejected(): void
    {
        $staff = Staff::factory()->create();

        // A real JPEG header followed by nothing usable.
        $valid = (string) $this->image('jpeg', 400, 400)->get();
        $truncated = UploadedFile::fake()->createWithContent('avatar.jpg', substr($valid, 0, 120));

        $this->actingAs($this->superAdmin())
            ->post(route('administration.staff.photo.store', $staff), ['photo' => $truncated])
            ->assertSessionHas('error');

        $this->assertNull($staff->fresh()->photo_path);
    }

    #[Test]
    public function a_polyglot_loses_its_payload(): void
    {
        $staff = Staff::factory()->create();

        // A genuinely valid JPEG with a PHP tag appended: decodes as an image,
        // so it must be re-encoded rather than stored as received.
        $polyglot = UploadedFile::fake()->createWithContent(
            'avatar.jpg',
            (string) $this->image('jpeg', 400, 400)->get()."<?php system('id'); ?>",
        );

        $this->actingAs($this->superAdmin())
            ->post(route('administration.staff.photo.store', $staff), ['photo' => $polyglot])
            ->assertRedirect();

        $stored = Storage::disk('local')->get($staff->fresh()->photo_path);

        $this->assertStringNotContainsString('<?php', $stored, 'Re-encoding must drop appended bytes.');
        $this->assertStringNotContainsString('system(', $stored);
    }

    #[Test]
    public function an_oversized_file_is_rejected(): void
    {
        $staff = Staff::factory()->create();

        $this->actingAs($this->superAdmin())
            ->post(route('administration.staff.photo.store', $staff), [
                'photo' => UploadedFile::fake()->create('big.jpg', 5000),
            ])->assertSessionHasErrors('photo');

        $this->assertNull($staff->fresh()->photo_path);
    }

    // ------------------------------------------------------------------ crop

    #[Test]
    public function the_chosen_region_is_what_gets_kept(): void
    {
        $staff = Staff::factory()->create();
        $admin = $this->superAdmin();

        // Grey canvas with a red square in the top-right corner.
        $source = $this->markedImage(900, 600, 640, 80, 220);

        // No crop: the centre is kept, which here is grey.
        $this->actingAs($admin)->post(route('administration.staff.photo.store', $staff), [
            'photo' => $source(),
        ])->assertRedirect();

        $this->assertSame('grey', $this->centreColourOf($staff->fresh()->photo_path));

        // Choosing the red region keeps the red.
        $this->actingAs($admin)->post(route('administration.staff.photo.store', $staff->fresh()), [
            'photo' => $source(),
            'crop_x' => 640,
            'crop_y' => 80,
            'crop_size' => 220,
        ])->assertRedirect();

        $this->assertSame('red', $this->centreColourOf($staff->fresh()->photo_path));
    }

    #[Test]
    public function a_crop_beyond_the_image_is_clamped_rather_than_failing(): void
    {
        $staff = Staff::factory()->create();

        $this->actingAs($this->superAdmin())
            ->post(route('administration.staff.photo.store', $staff), [
                'photo' => $this->image('jpeg', 400, 400),
                // Nonsense a crafted request might send.
                'crop_x' => 999999,
                'crop_y' => 999999,
                'crop_size' => 999999,
            ])->assertRedirect();

        [$width, $height] = getimagesizefromstring(
            Storage::disk('local')->get($staff->fresh()->photo_path)
        );

        $this->assertSame(512, $width);
        $this->assertSame(512, $height);
    }

    #[Test]
    public function a_negative_crop_is_rejected_by_validation(): void
    {
        $staff = Staff::factory()->create();

        $this->actingAs($this->superAdmin())
            ->post(route('administration.staff.photo.store', $staff), [
                'photo' => $this->image('jpeg', 400, 400),
                'crop_x' => -50,
                'crop_y' => 0,
                'crop_size' => 100,
            ])->assertSessionHasErrors('crop_x');
    }

    #[Test]
    public function a_partial_crop_falls_back_to_the_centre(): void
    {
        $staff = Staff::factory()->create();

        // crop_size missing: the request is incomplete, so it is ignored
        // entirely rather than half applied.
        $this->actingAs($this->superAdmin())
            ->post(route('administration.staff.photo.store', $staff), [
                'photo' => $this->markedImage(900, 600, 640, 80, 220)(),
                'crop_x' => 640,
                'crop_y' => 80,
            ])->assertRedirect();

        $this->assertSame('grey', $this->centreColourOf($staff->fresh()->photo_path));
    }

    // ---------------------------------------------------------- authorisation

    #[Test]
    public function an_unauthorised_user_cannot_upload_for_another_staff_member(): void
    {
        $target = Staff::factory()->create();
        $other = $this->userWithPermissions(['staff.view', 'staff.photo.manage.self']);

        $this->actingAs($other)
            ->post(route('administration.staff.photo.store', $target), [
                'photo' => $this->image('jpeg', 300, 300),
            ])->assertForbidden();

        $this->assertNull($target->fresh()->photo_path);
    }

    #[Test]
    public function self_service_permission_allows_changing_your_own_photo_only(): void
    {
        $own = Staff::factory()->create();
        $someoneElse = Staff::factory()->create();

        $user = $this->userWithPermissions(['staff.view', 'staff.photo.manage.self'], $own);

        $this->actingAs($user)
            ->post(route('administration.staff.photo.store', $own), [
                'photo' => $this->image('jpeg', 300, 300),
            ])->assertRedirect();

        $this->assertNotNull($own->fresh()->photo_path);

        $this->actingAs($user)
            ->post(route('administration.staff.photo.store', $someoneElse), [
                'photo' => $this->image('jpeg', 300, 300),
            ])->assertForbidden();

        $this->assertNull($someoneElse->fresh()->photo_path);
    }

    #[Test]
    public function a_photo_is_only_served_to_someone_who_may_view_the_record(): void
    {
        $staff = Staff::factory()->create();

        $this->actingAs($this->superAdmin())->post(route('administration.staff.photo.store', $staff), [
            'photo' => $this->image('jpeg', 300, 300),
        ])->assertRedirect();

        // Allowed to view staff.
        $this->actingAs($this->userWithPermissions(['staff.view']))
            ->get(route('administration.staff.photo.show', $staff))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');

        // Not allowed.
        $this->actingAs($this->userWithPermissions(['dashboard.view']))
            ->get(route('administration.staff.photo.show', $staff))
            ->assertForbidden();

        // Not signed in at all.
        auth()->logout();
        $this->get(route('administration.staff.photo.show', $staff))->assertRedirect('/login');
    }

    #[Test]
    public function a_record_with_no_photo_serves_a_404_rather_than_a_placeholder(): void
    {
        $staff = Staff::factory()->create();

        $this->actingAs($this->superAdmin())
            ->get(route('administration.staff.photo.show', $staff))
            ->assertNotFound();
    }

    #[Test]
    public function photos_are_not_stored_on_the_public_disk(): void
    {
        $staff = Staff::factory()->create();

        $this->actingAs($this->superAdmin())->post(route('administration.staff.photo.store', $staff), [
            'photo' => $this->image('jpeg', 300, 300),
        ])->assertRedirect();

        $this->assertTrue(Storage::disk('local')->exists($staff->fresh()->photo_path));
        $this->assertFalse(Storage::disk('public')->exists($staff->fresh()->photo_path));
    }

    // ------------------------------------------------------------------ helper

    /**
     * A grey image with a red square at the given position, so a test can tell
     * which region was kept.
     *
     * Returns a factory rather than a file: an UploadedFile is consumed by the
     * request, so each post needs its own.
     *
     * @return callable(): UploadedFile
     */
    private function markedImage(int $width, int $height, int $markX, int $markY, int $markSize): callable
    {
        $image = imagecreatetruecolor($width, $height);
        imagefilledrectangle($image, 0, 0, $width, $height, imagecolorallocate($image, 230, 230, 230));
        imagefilledrectangle(
            $image, $markX, $markY, $markX + $markSize, $markY + $markSize,
            imagecolorallocate($image, 220, 40, 40),
        );

        ob_start();
        imagejpeg($image, null, 90);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        return fn (): UploadedFile => UploadedFile::fake()->createWithContent('photo.jpg', $bytes);
    }

    /** 'red' or 'grey' at the centre of a stored photo. */
    private function centreColourOf(string $path): string
    {
        $image = imagecreatefromstring(Storage::disk('local')->get($path));
        $rgb = imagecolorat($image, 256, 256);
        imagedestroy($image);

        $red = ($rgb >> 16) & 255;
        $green = ($rgb >> 8) & 255;

        return $red > 150 && $green < 100 ? 'red' : 'grey';
    }

    /** A genuinely decodable image of the requested format and size. */
    private function image(string $format, int $width, int $height): UploadedFile
    {
        $image = imagecreatetruecolor($width, $height);
        imagefilledrectangle($image, 0, 0, $width, $height, imagecolorallocate($image, 30, 120, 180));

        ob_start();
        match ($format) {
            'jpeg' => imagejpeg($image),
            'png' => imagepng($image),
            'webp' => imagewebp($image),
        };
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        return UploadedFile::fake()->createWithContent("avatar.{$format}", $bytes);
    }
}
