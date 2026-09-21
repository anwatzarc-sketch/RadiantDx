<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The installable-app surface.
 *
 * Installability is easy to break silently: a route rename, an auth middleware
 * widened to cover the whole application, a deleted icon — none of which any
 * other test would notice, and none of which shows up until somebody tries to
 * add the application to a home screen. These tests pin the parts a browser
 * actually checks before it offers to install.
 */
class PwaTest extends TestCase
{
    // ------------------------------------------------------------- manifest

    #[Test]
    public function the_manifest_is_readable_without_signing_in(): void
    {
        // The browser fetches the manifest before anyone authenticates, so a
        // redirect to the sign-in page here means the app is never installable.
        $this->get(route('pwa.manifest'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/manifest+json');
    }

    #[Test]
    public function the_manifest_carries_what_a_browser_needs_to_install(): void
    {
        $manifest = $this->get(route('pwa.manifest'))->json();

        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('/', $manifest['scope']);
        $this->assertNotEmpty($manifest['name']);
        $this->assertNotEmpty($manifest['short_name']);

        // A start_url outside the scope is silently ignored by the browser and
        // the installed app opens at the site root instead.
        $this->assertStringStartsWith($manifest['scope'], $manifest['start_url']);
    }

    #[Test]
    public function the_manifest_offers_both_icon_sizes_chrome_requires(): void
    {
        $icons = collect($this->get(route('pwa.manifest'))->json('icons'));

        $this->assertTrue($icons->contains(fn (array $icon): bool => $icon['sizes'] === '192x192'));
        $this->assertTrue($icons->contains(fn (array $icon): bool => $icon['sizes'] === '512x512'));

        // Without a maskable icon Android renders the square artwork inside its
        // own shape, leaving a white tile behind the mark.
        $this->assertTrue($icons->contains(fn (array $icon): bool => $icon['purpose'] === 'maskable'));
    }

    #[Test]
    public function the_manifest_follows_the_deploying_site_identity(): void
    {
        config([
            'laboratory.organisation.name' => 'Kebele Health Centre',
            'laboratory.pwa.short_name' => 'KHC Lab',
            'laboratory.pwa.theme_color' => '#112233',
        ]);

        $manifest = $this->get(route('pwa.manifest'))->json();

        $this->assertStringContainsString('Kebele Health Centre', $manifest['name']);
        $this->assertSame('KHC Lab', $manifest['short_name']);
        $this->assertSame('#112233', $manifest['theme_color']);
    }

    // -------------------------------------------------------------- offline

    #[Test]
    public function the_offline_page_is_reachable_without_signing_in(): void
    {
        // The service worker caches this at install time, before any session
        // exists. Behind auth it would cache a redirect to the login page.
        $this->get(route('pwa.offline'))
            ->assertOk()
            ->assertSee('No network connection');
    }

    #[Test]
    public function the_offline_page_does_not_depend_on_the_compiled_stylesheet(): void
    {
        /*
         * This is the page that renders when nothing can be fetched, so it has
         * to stand on its own. Linking the build output would make it depend on
         * that asset also being cached, and on its content hash still matching
         * the one this page was cached against — a deploy since would leave it
         * as unstyled serif text at the one moment it is all there is.
         */
        $html = $this->get(route('pwa.offline'))->assertOk()->getContent();

        $this->assertStringNotContainsString('/build/', (string) $html);
        $this->assertStringContainsString('<style>', (string) $html);
    }

    #[Test]
    public function the_offline_page_recovers_on_its_own_when_the_connection_returns(): void
    {
        // A laboratory phone walking back into range should return to work
        // without anyone having to notice and press a button.
        $this->get(route('pwa.offline'))
            ->assertOk()
            ->assertSee('Try again')
            ->assertSee("addEventListener('online'", false);
    }

    #[Test]
    public function the_offline_page_keeps_the_promise_that_nothing_patient_identifying_is_cached(): void
    {
        $this->get(route('pwa.offline'))
            ->assertOk()
            ->assertSee('never stored on this device');
    }

    #[Test]
    public function the_service_worker_recaches_the_offline_page_after_a_successful_navigation(): void
    {
        /*
         * Install is not a reliable moment to precache: the request races the
         * page that registered the worker, and a single-threaded origin can
         * refuse to answer it. Without the retry, one unlucky install leaves
         * the worker permanently unable to show the designed offline page.
         */
        $worker = (string) file_get_contents(public_path('sw.js'));

        $this->assertStringContainsString('cacheOfflinePage', $worker);
        $this->assertStringContainsString('event.waitUntil(cacheOfflinePage())', $worker);
    }

    // ------------------------------------------------------- shipped assets

    #[Test]
    public function the_service_worker_and_icons_are_present_in_the_document_root(): void
    {
        $this->assertFileExists(public_path('sw.js'));

        foreach (['icon-192.png', 'icon-512.png', 'icon-maskable-512.png', 'apple-touch-icon.png'] as $icon) {
            $this->assertFileExists(public_path('images/'.$icon), $icon.' is referenced but not shipped.');
        }
    }

    #[Test]
    public function the_service_worker_is_served_from_the_root_so_it_can_scope_the_whole_app(): void
    {
        // A worker served from a subdirectory may only control that directory,
        // so the offline fallback would never fire for the application itself.
        $worker = (string) file_get_contents(public_path('sw.js'));

        $this->assertStringContainsString("addEventListener('fetch'", $worker);

        // Authenticated HTML must never reach Cache Storage on a shared
        // laboratory workstation — see the note at the top of public/sw.js.
        $this->assertStringContainsString("request.mode === 'navigate'", $worker);
    }

    // ---------------------------------------------------------- page markup

    #[Test]
    public function signed_out_pages_declare_the_viewport_and_the_manifest(): void
    {
        // Without these on the sign-in page the first screen a user ever sees
        // is the desktop layout shrunk to a phone, and the install prompt
        // never appears.
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('width=device-width', false)
            ->assertSee('viewport-fit=cover', false)
            ->assertSee('rel="manifest"', false)
            ->assertSee('apple-touch-icon', false);
    }
}
