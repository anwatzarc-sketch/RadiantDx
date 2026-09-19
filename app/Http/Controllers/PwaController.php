<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

/**
 * Installable-app endpoints.
 *
 * Both of these are deliberately unauthenticated. The browser fetches the
 * manifest before anyone signs in, and the service worker caches the offline
 * page at install time, so neither may sit behind auth — and neither carries
 * anything patient identifying.
 */
class PwaController extends Controller
{
    /**
     * The web app manifest, built from configuration so a site deploying this
     * application installs under its own name, icon and colours.
     */
    public function manifest(): JsonResponse
    {
        $name = (string) config('laboratory.organisation.name', config('app.name'));
        $department = (string) config('laboratory.organisation.department', '');

        return response()->json([
            'name' => trim($name.' — Laboratory'),
            'short_name' => (string) config('laboratory.pwa.short_name', 'Laboratory'),
            'description' => $department !== '' ? $department : 'Laboratory management',
            'id' => '/',
            'scope' => '/',
            // Lands on the dashboard; an unauthenticated launch redirects to
            // the sign-in page, which is the correct behaviour for a shared
            // laboratory device.
            'start_url' => '/admin/dashboard',
            'display' => 'standalone',
            'orientation' => 'any',
            'background_color' => (string) config('laboratory.pwa.background_color', '#f8fafc'),
            'theme_color' => (string) config('laboratory.pwa.theme_color', '#00303c'),
            'icons' => [
                [
                    'src' => asset('images/icon-192.png'),
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => asset('images/icon-512.png'),
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => asset('images/icon-maskable-512.png'),
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'maskable',
                ],
            ],
        ], 200, [
            'Content-Type' => 'application/manifest+json',
            'Cache-Control' => 'public, max-age=3600',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** Shown by the service worker when a navigation is attempted with no network. */
    public function offline(): View
    {
        return view('offline');
    }
}
