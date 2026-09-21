{{--
    Served by the service worker when a navigation is attempted with no
    network.

    Deliberately generic: it never shows cached patient data, because nothing
    patient identifying is ever written to the cache. See public/sw.js.

    ---------------------------------------------------------------------------
    Why this page carries its own CSS instead of @vite
    ---------------------------------------------------------------------------
    This is the one page in the application that must render when nothing can
    be fetched. Linking the compiled stylesheet makes it depend on that build
    asset also being in the cache, and on the hash in its filename still being
    the one this page was cached against — a build deployed since would leave
    the page rendering as unstyled serif text at the exact moment it is the
    only thing on screen.

    So everything it needs is inline and the page is self-sufficient. It is
    small, it changes rarely, and it is the last thing standing between a
    technologist and a blank browser error.
--}}

@php
    $brand = (string) config('laboratory.pwa.theme_color', '#00303c');
    $organisation = (string) config('laboratory.organisation.name', config('app.name'));
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <title>Offline · {{ config('app.name') }}</title>
    <x-pwa-head />

    <style>
        :root {
            --brand: {{ $brand }};
            --ink: #ecfeff;
            --muted: #8fb3bb;
            --line: rgba(255, 255, 255, 0.12);
            --accent: #2dd4bf;
        }

        *, *::before, *::after { box-sizing: border-box; }

        html, body { height: 100%; }

        body {
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            padding:
                calc(env(safe-area-inset-top, 0px) + 1.5rem)
                calc(env(safe-area-inset-right, 0px) + 1.25rem)
                calc(env(safe-area-inset-bottom, 0px) + 1.5rem)
                calc(env(safe-area-inset-left, 0px) + 1.25rem);
            font-family: 'Instrument Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            color: var(--ink);
            background-color: var(--brand);
            /* Two soft pools of light rather than a flat fill, so the page
               reads as a designed surface and not as a browser error. */
            background-image:
                radial-gradient(ellipse 80% 60% at 50% -10%, rgba(45, 212, 191, 0.18), transparent 70%),
                radial-gradient(ellipse 70% 50% at 50% 110%, rgba(56, 189, 248, 0.10), transparent 70%);
            -webkit-font-smoothing: antialiased;
            text-rendering: optimizeLegibility;
        }

        main {
            width: 100%;
            max-width: 27rem;
            text-align: center;
        }

        /* --- The disconnected mark --- */

        .figure {
            position: relative;
            width: 8.5rem;
            height: 8.5rem;
            margin: 0 auto 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Rings travelling outward: a signal going nowhere. */
        .ring {
            position: absolute;
            inset: 0;
            border: 1px solid rgba(45, 212, 191, 0.35);
            border-radius: 50%;
            opacity: 0;
            animation: pulse 3.2s cubic-bezier(0.22, 1, 0.36, 1) infinite;
        }

        .ring:nth-child(2) { animation-delay: 1.05s; }
        .ring:nth-child(3) { animation-delay: 2.1s; }

        @keyframes pulse {
            0%   { transform: scale(0.45); opacity: 0; }
            25%  { opacity: 0.85; }
            100% { transform: scale(1); opacity: 0; }
        }

        .badge {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 4.75rem;
            height: 4.75rem;
            border-radius: 1.5rem;
            background: rgba(255, 255, 255, 0.07);
            border: 1px solid var(--line);
            box-shadow: 0 16px 40px -12px rgba(0, 0, 0, 0.55);
            backdrop-filter: blur(2px);
        }

        .badge svg { width: 2.25rem; height: 2.25rem; }

        /* --- Type --- */

        h1 {
            margin: 0;
            font-size: 1.375rem;
            font-weight: 700;
            letter-spacing: -0.015em;
            color: #ffffff;
        }

        .lede {
            margin: 0.625rem 0 0;
            font-size: 0.9375rem;
            line-height: 1.6;
            color: var(--muted);
        }

        /* --- Live connection state --- */

        .status {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 1.5rem;
            padding: 0.4375rem 0.875rem;
            font-size: 0.75rem;
            font-weight: 500;
            border-radius: 999px;
            border: 1px solid rgba(251, 191, 36, 0.35);
            background: rgba(251, 191, 36, 0.10);
            color: #fde68a;
            transition: all 0.25s ease;
        }

        .status[data-state='online'] {
            border-color: rgba(45, 212, 191, 0.4);
            background: rgba(45, 212, 191, 0.12);
            color: #99f6e4;
        }

        .dot {
            width: 0.5rem;
            height: 0.5rem;
            border-radius: 50%;
            background: #fbbf24;
            box-shadow: 0 0 8px #fbbf24;
            animation: blink 1.8s ease-in-out infinite;
        }

        .status[data-state='online'] .dot {
            background: var(--accent);
            box-shadow: 0 0 8px var(--accent);
            animation: none;
        }

        @keyframes blink {
            0%, 100% { opacity: 1; }
            50%      { opacity: 0.35; }
        }

        /* --- Action --- */

        .actions { margin-top: 1.75rem; }

        button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            /* Comfortably past the 44px touch target the rest of the
               application holds itself to. */
            min-height: 2.75rem;
            padding: 0 1.5rem;
            font: inherit;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--brand);
            background: #ffffff;
            border: 0;
            border-radius: 0.625rem;
            cursor: pointer;
            box-shadow: 0 8px 20px -8px rgba(0, 0, 0, 0.6);
            transition: transform 0.15s ease, background-color 0.15s ease;
        }

        button:hover { background: #e0f2f1; }
        button:active { transform: scale(0.98); }
        button:focus-visible { outline: 2px solid var(--accent); outline-offset: 3px; }
        button svg { width: 1rem; height: 1rem; }

        /* --- Notes --- */

        .note {
            margin: 1.75rem auto 0;
            max-width: 22rem;
            padding-top: 1.25rem;
            border-top: 1px solid var(--line);
            font-size: 0.75rem;
            line-height: 1.6;
            color: rgba(143, 179, 187, 0.8);
        }

        .org {
            margin: 0.75rem 0 0;
            font-size: 0.6875rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: rgba(143, 179, 187, 0.6);
        }

        @media (max-width: 26rem) {
            .figure { width: 7rem; height: 7rem; margin-bottom: 1.5rem; }
            .badge { width: 4rem; height: 4rem; border-radius: 1.25rem; }
            .badge svg { width: 1.875rem; height: 1.875rem; }
            h1 { font-size: 1.1875rem; }
        }

        @media (prefers-reduced-motion: reduce) {
            .ring, .dot { animation: none; }
            .ring { opacity: 0.3; transform: scale(1); }
            button { transition: none; }
        }
    </style>
</head>
<body>
    <main>
        <div class="figure">
            <span class="ring"></span>
            <span class="ring"></span>
            <span class="ring"></span>

            <span class="badge">
                {{-- A signal with a line through it: the connection, not the
                     laboratory, is what has gone. --}}
                <svg viewBox="0 0 24 24" fill="none" stroke="#2dd4bf" stroke-width="1.6"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                    <path d="M1.5 8.5a16 16 0 0 1 6-3.7" />
                    <path d="M16.5 4.8a16 16 0 0 1 6 3.7" />
                    <path d="M5 12.1a11 11 0 0 1 3.2-2" />
                    <path d="M15.8 10.1a11 11 0 0 1 3.2 2" />
                    <path d="M8.6 15.7a6 6 0 0 1 6.8 0" />
                    <path d="M12 19.5h.01" />
                    <path d="M2.5 2.5l19 19" stroke="#f8fafc" />
                </svg>
            </span>
        </div>

        <h1>No network connection</h1>

        <p class="lede">
            This device cannot reach the laboratory server right now.
        </p>

        <div class="status" id="status" data-state="offline" role="status" aria-live="polite">
            <span class="dot" aria-hidden="true"></span>
            <span id="status-text">Waiting for the connection to return</span>
        </div>

        <div class="actions">
            <button type="button" id="retry">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                    <path d="M16.023 9.348h4.992V4.356M20.015 9.348a8.25 8.25 0 1 0-1.86 5.303" />
                </svg>
                Try again
            </button>
        </div>

        <p class="note">
            Requisitions, results and patient details are never stored on this device,
            so none of them can be shown until the connection is back. Nothing you had
            already saved has been lost.
        </p>

        <p class="org">{{ $organisation }}</p>
    </main>

    <script>
        /*
         * The page watches for the connection itself rather than leaving the
         * person to guess when to press the button: a laboratory phone that
         * walks back into Wi-Fi range should return to work on its own.
         *
         * The `online` event only reports that an interface came back, not
         * that this server is reachable, so the reload is what actually proves
         * it — if it fails, this page is simply served again.
         */
        (function () {
            var status = document.getElementById('status');
            var text = document.getElementById('status-text');
            var retry = document.getElementById('retry');

            function reload() {
                window.location.reload();
            }

            function restored() {
                status.setAttribute('data-state', 'online');
                text.textContent = 'Connection restored — reopening';
                window.setTimeout(reload, 600);
            }

            retry.addEventListener('click', reload);
            window.addEventListener('online', restored);

            if (navigator.onLine) {
                // Reached this page with an interface already up: the server
                // itself was unreachable, so say so rather than claiming there
                // is no connection at all.
                text.textContent = 'Connected, but the server did not answer';
            }
        })();
    </script>
</body>
</html>
