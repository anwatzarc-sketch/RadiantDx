<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;
use PDOException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * The database could not be reached — recognised, and answered with a page a
 * technologist can read.
 *
 * Two different failures arrive as the same QueryException, and they want
 * opposite treatment:
 *
 *   - The server answered and refused the statement: a typo in a column name,
 *     a constraint violation. That is a defect in this application, and the
 *     developer wants the stack trace, the SQL and the bindings.
 *
 *   - The server never answered at all: stopped, firewalled, out of
 *     connections, wrong credentials. Nothing in the stack trace helps,
 *     because the fault is not in any of those frames. What the person at the
 *     bench needs is to be told plainly that the records service is down, that
 *     their saved work is safe, and when to try again.
 *
 * {@see self::caused()} separates the two and {@see self::response()} answers
 * the second. The first is left alone to render as it always has.
 *
 * Worth knowing where this fires from: with SESSION_DRIVER=database the first
 * thing every request does is read its session row, so a database that is down
 * takes out the sign-in page, the dashboard and every other route at once —
 * before routing, before any controller. That is why the response built here
 * touches nothing but configuration and the view compiler.
 */
final class DatabaseUnavailable
{
    /**
     * SQLSTATE values that are about the connection rather than the statement.
     *
     * Class 08 is the ANSI "connection exception" class and every driver uses
     * it; 57P03 is PostgreSQL refusing work while it starts up or shuts down.
     *
     * MySQL's HY000 is deliberately absent: it is that driver's catch-all and
     * a plain syntax error carries it too, so MySQL failures are recognised by
     * their driver error code below instead.
     */
    private const CONNECTION_SQL_STATES = [
        '08001', // Client cannot establish the connection.
        '08003', // Connection does not exist.
        '08004', // Server rejected the connection.
        '08006', // Connection failure.
        '08S01', // Communication link failure.
        '57P03', // PostgreSQL: cannot connect now.
    ];

    /**
     * Driver error numbers that mean "this server will not serve you", whether
     * because it is not listening or because it will not let us in.
     *
     * The credential and unknown-database codes are here on purpose. They are
     * deployment faults rather than application faults — an operator pointed
     * the application at the wrong database, or a password was rotated — and
     * from the bench they are indistinguishable from the server being off.
     * Nobody is helped by a stack trace through the session handler.
     */
    private const CONNECTION_ERROR_CODES = [
        1040, // Too many connections.
        1042, // Unable to connect to any of the specified hosts.
        1043, // Bad handshake.
        1044, // Access denied for this user to this database.
        1045, // Access denied for this user.
        1049, // Unknown database.
        1129, // Host blocked by too many connection errors.
        1130, // Host not allowed to connect to this server.
        2002, // Cannot connect: socket missing, or the machine refused us.
        2003, // Cannot connect to the server on this host.
        2005, // Unknown server host.
        2006, // Server has gone away.
        2013, // Lost connection during the query.
        2026, // TLS handshake failed.
    ];

    /**
     * Last resort, for the failures that arrive with no usable code at all.
     *
     * A missing PDO extension and an unopenable SQLite file both report
     * SQLSTATE 00000, and some drivers hand back nothing but a message. Each
     * needle is specific enough that no statement-level error contains it.
     */
    private const CONNECTION_MESSAGE_NEEDLES = [
        'could not find driver',
        'connection refused',
        'actively refused',
        'connection timed out',
        'timed out while connecting',
        'no connection could be made',
        'no route to host',
        'name or service not known',
        'unknown mysql server host',
        'server has gone away',
        'lost connection',
        'connection reset by peer',
        'broken pipe',
        'too many connections',
        'unable to open database file',
        'the database system is starting up',
        'the database system is shutting down',
    ];

    /**
     * Was this failure the database being unreachable?
     *
     * The whole chain is walked because the useful evidence is almost never on
     * the exception that surfaced: Laravel wraps the driver's PDOException in
     * a QueryException, and application code may wrap that again.
     */
    public static function caused(?Throwable $exception): bool
    {
        for ($current = $exception; $current !== null; $current = $current->getPrevious()) {
            if (self::isConnectionFailure($current)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The response to send while the database is unreachable.
     *
     * 503 rather than 500, because the application is fine and its dependency
     * is not. It says "come back", which is both true and what a monitor, a
     * load balancer and a crawler each need to hear. Retry-After gives them a
     * number to honour, and no-store keeps a proxy from pinning this page in
     * front of a service that has since recovered.
     */
    public static function response(Request $request, Throwable $exception): Response
    {
        $retryAfter = 30;

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()
                ->json([
                    'message' => 'The laboratory records service is temporarily unavailable. Please try again shortly.',
                ], Response::HTTP_SERVICE_UNAVAILABLE)
                ->header('Retry-After', (string) $retryAfter)
                ->header('Cache-Control', 'no-store, private');
        }

        return response()
            ->view('errors.database', [
                /*
                 * Shown so a support desk has something to search the log
                 * with: the log line this same exception produced carries the
                 * same timestamp. UTC rather than local time because the log
                 * and the server agree on UTC and the reader may not be in
                 * the laboratory's timezone.
                 */
                'occurredAt' => now()->utc()->format('d M Y H:i:s').' UTC',

                /*
                 * Only ever populated with APP_DEBUG on. In production this
                 * page must not name the host, the port or the database: it
                 * is served to anyone who can reach the site, including
                 * people who have not signed in.
                 */
                'detail' => config('app.debug') ? self::detail($exception) : null,

                'retryAfter' => $retryAfter,
            ], Response::HTTP_SERVICE_UNAVAILABLE)
            ->header('Retry-After', (string) $retryAfter)
            ->header('Cache-Control', 'no-store, private');
    }

    /**
     * Does this one exception, ignoring anything it wraps, describe a
     * connection that could not be made or could not be kept?
     */
    private static function isConnectionFailure(Throwable $exception): bool
    {
        if (in_array(self::sqlState($exception), self::CONNECTION_SQL_STATES, true)) {
            return true;
        }

        if (in_array(self::driverErrorCode($exception), self::CONNECTION_ERROR_CODES, true)) {
            return true;
        }

        $message = mb_strtolower($exception->getMessage());

        foreach (self::CONNECTION_MESSAGE_NEEDLES as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The five-character SQLSTATE: from the exception code where the driver
     * set one, and from the message where it did not.
     *
     * PDO puts the SQLSTATE in getCode() as a string, but an exception that
     * has been re-thrown or constructed by hand often carries the default
     * integer 0 instead, while its message still reads "SQLSTATE[08006] ...".
     */
    private static function sqlState(Throwable $exception): ?string
    {
        $code = $exception->getCode();

        if (is_string($code) && $code !== '' && $code !== '00000') {
            return $code;
        }

        if (preg_match('/SQLSTATE\[(\w{5})\]/', $exception->getMessage(), $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    /**
     * The driver's own error number — 2002 and its neighbours — which is what
     * actually separates a MySQL connection failure from a MySQL syntax error,
     * since both are reported as SQLSTATE HY000.
     */
    private static function driverErrorCode(Throwable $exception): ?int
    {
        if ($exception instanceof PDOException && isset($exception->errorInfo[1]) && is_int($exception->errorInfo[1])) {
            return $exception->errorInfo[1];
        }

        if (preg_match('/SQLSTATE\[\w{5}\]\s*\[(\d+)\]/', $exception->getMessage(), $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * The one line a developer needs while APP_DEBUG is on.
     *
     * The root cause, not the wrapper: QueryException restates the whole
     * failing statement, and when the server was never reached the statement
     * is beside the point.
     */
    private static function detail(Throwable $exception): string
    {
        $root = $exception;

        while ($root->getPrevious() !== null) {
            $root = $root->getPrevious();
        }

        return sprintf(
            '%s: %s (%s:%d)',
            $root::class,
            $root->getMessage(),
            str_replace(base_path().DIRECTORY_SEPARATOR, '', $root->getFile()),
            $root->getLine(),
        );
    }
}
