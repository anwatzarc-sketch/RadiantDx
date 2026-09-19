{{--
    Everything a page needs to be mobile-correct and installable.

    Include this in the <head> of every standalone layout. Anything added later
    — a new layout for a new module, a kiosk view, a patient portal — gets the
    viewport, the manifest, the icons and the platform meta by including this
    one component, rather than by remembering to copy four <meta> tags.

    viewport-fit=cover is what lets the layout paint into the notch/home-bar
    area on modern phones; the safe-area padding that makes that usable lives
    in app.css.
--}}

<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />

<link rel="manifest" href="{{ route('pwa.manifest') }}" />

<meta name="theme-color" content="{{ config('laboratory.pwa.theme_color', '#00303c') }}" />
<meta name="color-scheme" content="light" />

{{-- iOS does not read the manifest; these are its equivalents. --}}
<meta name="mobile-web-app-capable" content="yes" />
<meta name="apple-mobile-web-app-capable" content="yes" />
<meta name="apple-mobile-web-app-status-bar-style" content="default" />
<meta name="apple-mobile-web-app-title" content="{{ config('laboratory.pwa.short_name', 'Laboratory') }}" />
<meta name="format-detection" content="telephone=no" />

<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="32x32" />
<link rel="icon" type="image/svg+xml" href="{{ asset('images/logo-mark.svg') }}" />
<link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png') }}" />
