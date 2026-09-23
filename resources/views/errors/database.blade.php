{{--
    Shown when the database cannot be reached.

    Rendered from App\Support\DatabaseUnavailable, which is what decides that a
    QueryException was a connection failure rather than a bad statement. A bad
    statement still renders the ordinary error page: this one is for the case
    where there is no stack frame worth reading.

    ---------------------------------------------------------------------------
    The one rule this page has to keep
    ---------------------------------------------------------------------------
    Nothing on it may touch the database, and nothing on it may depend on a
    build asset. It is rendered at the moment the application's own storage is
    unavailable, so @vite, the application layout, the footer component and
    anything that resolves the signed-in user are all off limits — each would
    throw again and leave Laravel's fallback plain-text page in place of this
    one. Everything it needs is configuration and inline CSS.

    It is a deliberate sibling of offline.blade.php: same brand ground, same
    proportions, same reassurance. The two say different things — "this device
    has no network" against "the server is up but its records store is not" —
    and someone who has seen one should recognise the other.
--}}

@php
    $brand = (string) config('laboratory.pwa.theme_color', '#00303c');
    $organisation = (string) config('laboratory.organisation.name', config('app.name'));
    $hotline = trim((string) config('laboratory.footer.support_hotline', ''));
    $supportEmail = trim((string) config('laboratory.footer.support_email', ''));
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <title>Service unavailable · {{ config('app.name') }}</title>

    {{--
        Written out rather than pulled in with <x-pwa-head />, which links the
        manifest through route(). Route names are almost always available here,
        but "almost always" is the wrong standard for the page that has to
        render when everything else has failed — a database read from a service
        provider would fail before the routes were registered, and this page
        would then fail with it. The metas that matter on a phone are cheap to
        state directly.
    --}}
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    <meta name="theme-color" content="{{ $brand }}" />
    <meta name="color-scheme" content="dark" />
    <meta name="robots" content="noindex" />
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="32x32" />
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/logo-mark.svg') }}" />

    <style>
        :root {
            --brand: {{ $brand }};
            --ink: #ecfeff;
            --muted: #8fb3bb;
            --line: rgba(255, 255, 255, 0.12);
            --accent: #2dd4bf;
            --warn: #fbbf24;
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
            /* The same two pools of light as the offline page, so the pair
               read as one family rather than as two unrelated failures. */
            background-image:
                radial-gradient(ellipse 80% 60% at 50% -10%, rgba(45, 212, 191, 0.18), transparent 70%),
                radial-gradient(ellipse 70% 50% at 50% 110%, rgba(56, 189, 248, 0.10), transparent 70%);
            -webkit-font-smoothing: antialiased;
            text-rendering: optimizeLegibility;
        }

        main {
            width: 100%;
            max-width: 30rem;
            text-align: center;
        }

        /* --- The mark: a store that is there but not answering --- */

        .figure {
            position: relative;
            width: 8.5rem;
            height: 8.5rem;
            margin: 0 auto 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* One slow sweep rather than the offline page's outgoing rings: this
           is something being waited on, not something being called. */
        .sweep {
            position: absolute;
            inset: 0.75rem;
            border-radius: 50%;
            border: 1px solid transparent;
            border-top-color: rgba(45, 212, 191, 0.45);
            border-right-color: rgba(45, 212, 191, 0.12);
            animation: sweep 2.6s linear infinite;
        }

        @keyframes sweep {
            to { transform: rotate(360deg); }
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
            margin: 0.625rem auto 0;
            max-width: 25rem;
            font-size: 0.9375rem;
            line-height: 1.6;
            color: var(--muted);
        }

        /* --- Retry state --- */

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

        .status[data-state='checking'] {
            border-color: rgba(45, 212, 191, 0.4);
            background: rgba(45, 212, 191, 0.12);
            color: #99f6e4;
        }

        .dot {
            width: 0.5rem;
            height: 0.5rem;
            border-radius: 50%;
            background: var(--warn);
            box-shadow: 0 0 8px var(--warn);
            animation: blink 1.8s ease-in-out infinite;
        }

        .status[data-state='checking'] .dot {
            background: var(--accent);
            box-shadow: 0 0 8px var(--accent);
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
            max-width: 24rem;
            padding-top: 1.25rem;
            border-top: 1px solid var(--line);
            font-size: 0.75rem;
            line-height: 1.6;
            color: rgba(143, 179, 187, 0.8);
        }

        .note strong { color: rgba(236, 254, 255, 0.9); font-weight: 600; }

        .support {
            margin: 0.875rem auto 0;
            max-width: 24rem;
            font-size: 0.75rem;
            line-height: 1.6;
            color: rgba(143, 179, 187, 0.8);
        }

        .support strong { color: rgba(236, 254, 255, 0.9); font-weight: 600; }
        .support a { color: #99f6e4; text-decoration: none; }
        .support a:hover { text-decoration: underline; }

        .stamp {
            margin: 0.875rem 0 0;
            font-size: 0.6875rem;
            color: rgba(143, 179, 187, 0.55);
            font-variant-numeric: tabular-nums;
        }

        .org {
            margin: 0.75rem 0 0;
            font-size: 0.6875rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: rgba(143, 179, 187, 0.6);
        }

        /* --- Developer detail (APP_DEBUG only) --- */

        details {
            margin: 1.5rem auto 0;
            max-width: 26rem;
            text-align: left;
            border: 1px solid var(--line);
            border-radius: 0.625rem;
            background: rgba(0, 0, 0, 0.22);
        }

        summary {
            padding: 0.625rem 0.875rem;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--muted);
            cursor: pointer;
            list-style: none;
        }

        summary::-webkit-details-marker { display: none; }
        summary::before { content: '▸ '; color: var(--accent); }
        details[open] summary::before { content: '▾ '; }

        details pre {
            margin: 0;
            padding: 0 0.875rem 0.875rem;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 0.6875rem;
            line-height: 1.55;
            color: #fca5a5;
            white-space: pre-wrap;
            word-break: break-word;
        }

        @media (max-width: 26rem) {
            .figure { width: 7rem; height: 7rem; margin-bottom: 1.5rem; }
            .badge { width: 4rem; height: 4rem; border-radius: 1.25rem; }
            .badge svg { width: 1.875rem; height: 1.875rem; }
            h1 { font-size: 1.1875rem; }
        }

        @media (prefers-reduced-motion: reduce) {
            .sweep, .dot { animation: none; }
            button { transition: none; }
        }
    </style>
</head>
<body>
    <main>
        <div class="figure">
            <span class="sweep" aria-hidden="true"></span>

            <span class="badge">
                {{-- Stacked discs with a line through them: the records store
                     is where it always was, it is simply not answering. --}}
                <svg viewBox="0 0 24 24" fill="none" stroke="#2dd4bf" stroke-width="1.6"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                    <ellipse cx="12" cy="5.5" rx="7.5" ry="3" />
                    <path d="M4.5 5.5v6c0 1.66 3.36 3 7.5 3s7.5-1.34 7.5-3v-6" />
                    <path d="M4.5 11.5v6c0 1.66 3.36 3 7.5 3s7.5-1.34 7.5-3v-6" />
                    <path d="M3 2.5l18 18" stroke="#f8fafc" />
                </svg>
            </span>
        </div>

        <h1>The records service is unavailable</h1>

        <p class="lede">
            {{ $organisation }} cannot reach the database it keeps patients, requisitions
            and results in, so none of them can be shown right now.
        </p>

        <div class="status" id="status" data-state="waiting" role="status" aria-live="polite">
            <span class="dot" aria-hidden="true"></span>
            <span id="status-text">Checking again shortly</span>
        </div>

        <div class="actions">
            <button type="button" id="retry">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                    <path d="M16.023 9.348h4.992V4.356M20.015 9.348a8.25 8.25 0 1 0-1.86 5.303" />
                </svg>
                Try again now
            </button>
        </div>

        <p class="note">
            <strong>Nothing already saved has been lost.</strong> Work that was recorded
            before this is held in the database and will be there when it answers again.
            Anything that was part-way through being entered was not saved, and will need
            to be entered once more.
        </p>

        {{--
            The hotline is plain text and the address is a link, matching the
            application footer. A hotline is commonly configured as something
            like "Ext. 4400", which is dialable from a bench handset and
            meaningless to a mobile — a tel: link around it would offer to
            place a call that could not connect.
        --}}
        @if ($hotline !== '' || $supportEmail !== '')
            <p class="support">
                If this does not clear on its own, tell the people who run this system:
                @if ($hotline !== '')
                    <strong>{{ $hotline }}</strong>@if ($supportEmail !== ''){{ ' · ' }}@endif
                @endif
                @if ($supportEmail !== '')
                    <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a>
                @endif
            </p>
        @endif

        {{-- The server log line for this failure carries the same timestamp,
             which is the whole reason it is printed. --}}
        <p class="stamp">Reported at {{ $occurredAt }}</p>

        @if ($detail !== null)
            <details>
                <summary>Technical detail (shown because APP_DEBUG is on)</summary>
                <pre>{{ $detail }}</pre>
            </details>
        @endif

        <p class="org">{{ $organisation }}</p>
    </main>

    <script>
        /*
         * The page retries by itself, because a database that is coming back
         * up — a restarted service, a connection pool draining — usually
         * returns within a minute, and nobody should have to sit pressing a
         * button to find out.
         *
         * The wait grows 15s, 30s, 60s and then holds at 60s. A flat short
         * interval would turn every browser left open on this page into a
         * load generator against a server that is already struggling, and
         * would write a log line per attempt per tab.
         *
         * A reload is the only honest test: the failure is server side, so
         * there is nothing this page can ask the browser that would prove the
         * database is back short of asking the application again.
         */
        (function () {
            var WAITS = [15, 30, 60];

            var status = document.getElementById('status');
            var text = document.getElementById('status-text');
            var retry = document.getElementById('retry');

            // Kept across reloads so the backoff keeps growing rather than
            // restarting at 15s on every attempt. sessionStorage is per tab
            // and clears itself when the tab closes, which is exactly the
            // lifetime this counter should have.
            var attempt = 0;

            try {
                attempt = parseInt(window.sessionStorage.getItem('db-unavailable-attempt') || '0', 10) || 0;
                window.sessionStorage.setItem('db-unavailable-attempt', String(attempt + 1));
            } catch (e) {
                // Private browsing, or storage disabled. The page still works,
                // it just always waits the first interval.
            }

            var remaining = WAITS[Math.min(attempt, WAITS.length - 1)];

            function reload() {
                status.setAttribute('data-state', 'checking');
                text.textContent = 'Checking now';
                window.location.reload();
            }

            function tick() {
                if (remaining <= 0) {
                    reload();
                    return;
                }

                text.textContent = 'Checking again in ' + remaining + 's';
                remaining -= 1;
                window.setTimeout(tick, 1000);
            }

            retry.addEventListener('click', reload);

            // Someone who comes back to this tab has usually just done
            // something about the problem, so check straight away instead of
            // making them wait out a counter that ran while they were away.
            document.addEventListener('visibilitychange', function () {
                if (!document.hidden && remaining > 3) {
                    remaining = 3;
                }
            });

            tick();
        })();
    </script>
</body>
</html>
