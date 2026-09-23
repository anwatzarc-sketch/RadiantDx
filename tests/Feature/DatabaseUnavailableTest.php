<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\DatabaseUnavailable;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use PDOException;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * What the application says when its database cannot be reached.
 *
 * The behaviour under test is only ever exercised during an outage, which is
 * exactly when nobody is in a position to check it by hand — and the cost of
 * it being wrong is high, because with the database session driver this is
 * the page every route falls back to. So the failures are fabricated here.
 *
 * Two properties matter and they pull against each other: a server that never
 * answered must produce the page, and a server that answered and refused the
 * statement must not — that one is a bug in this application and swallowing
 * its stack trace would hide it.
 */
class DatabaseUnavailableTest extends TestCase
{
    // ---------------------------------------------- recognising the failure

    #[Test]
    public function a_refused_connection_is_recognised(): void
    {
        // The exact failure a stopped MySQL produces on Windows, as reported
        // from the session handler on a request that had not yet been routed.
        $this->assertTrue(DatabaseUnavailable::caused($this->connectionRefused()));
    }

    #[Test]
    public function the_cause_is_found_however_deeply_it_is_wrapped(): void
    {
        // Laravel already wraps the driver's PDOException in a QueryException;
        // application code that catches and re-throws adds another layer, and
        // the evidence stays at the bottom.
        $wrapped = new RuntimeException('Could not load the dashboard.', 0, $this->connectionRefused());

        $this->assertTrue(DatabaseUnavailable::caused($wrapped));
    }

    #[Test]
    public function the_other_ways_a_server_declines_to_serve_are_recognised(): void
    {
        $cases = [
            'wrong password' => [1045, 'Access denied for user \'lab\'@\'10.0.0.4\' (using password: YES)'],
            'wrong database' => [1049, 'Unknown database \'harme_laboratory\''],
            'out of connections' => [1040, 'Too many connections'],
            'server restarted mid-request' => [2006, 'MySQL server has gone away'],
        ];

        foreach ($cases as $label => [$code, $message]) {
            $exception = $this->queryException("SQLSTATE[HY000] [{$code}] {$message}", 'HY000', $code);

            $this->assertTrue(
                DatabaseUnavailable::caused($exception),
                "A {$label} should be treated as the database being unavailable.",
            );
        }
    }

    #[Test]
    public function a_statement_the_server_answered_and_refused_is_not_treated_as_an_outage(): void
    {
        /*
         * This is the guard on the whole feature. An unknown column is a
         * defect in this application; if it rendered the outage page, the
         * developer would be told the database was down and would go looking
         * at the wrong machine.
         *
         * Note the SQLSTATE: MySQL reports this as HY000 too, which is why
         * the driver error code is what the detection actually turns on.
         */
        $broken = $this->queryException(
            "SQLSTATE[42S22]: Column not found: 1054 Unknown column 'speciality' in 'field list'",
            '42S22',
            1054,
        );

        $this->assertFalse(DatabaseUnavailable::caused($broken));
    }

    #[Test]
    public function an_unrelated_failure_is_not_treated_as_an_outage(): void
    {
        $this->assertFalse(DatabaseUnavailable::caused(new RuntimeException('Something else broke.')));
        $this->assertFalse(DatabaseUnavailable::caused(null));
    }

    // -------------------------------------------------------- the response

    #[Test]
    public function the_browser_is_given_the_page_rather_than_a_stack_trace(): void
    {
        $response = $this->render(Request::create('/admin/dashboard'));

        $this->assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());

        $body = $response->getContent();

        $this->assertStringContainsString('The records service is unavailable', $body);

        // The reassurance is the point of the page: somebody who was part-way
        // through entering a result needs to know what became of it.
        $this->assertStringContainsString('Nothing already saved has been lost', $body);
    }

    #[Test]
    public function the_page_never_names_the_database_it_could_not_reach(): void
    {
        /*
         * The page renders before authentication, so it is readable by anyone
         * who can reach the site. A host, a port and a database name are the
         * first three things an attacker would like to be given.
         */
        config()->set('app.debug', false);

        $body = $this->render(Request::create('/admin/dashboard'))->getContent();

        $this->assertStringNotContainsString('127.0.0.1', $body);
        $this->assertStringNotContainsString('3306', $body);
        $this->assertStringNotContainsString('harme_laboratory', $body);
        $this->assertStringNotContainsString('SQLSTATE', $body);
        $this->assertStringNotContainsString('vendor', $body);
    }

    #[Test]
    public function the_detail_is_shown_only_while_debug_is_on(): void
    {
        config()->set('app.debug', true);

        $body = $this->render(Request::create('/admin/dashboard'))->getContent();

        // The root cause, not the QueryException wrapper that restates the
        // whole statement the connection never carried.
        $this->assertStringContainsString('2002', $body);
        $this->assertStringContainsString('APP_DEBUG', $body);
    }

    #[Test]
    public function the_response_tells_caches_and_monitors_what_to_do(): void
    {
        $response = $this->render(Request::create('/admin/dashboard'));

        // 503 plus Retry-After is what a load balancer, an uptime monitor and
        // a crawler each read to know this is temporary.
        $this->assertSame('30', $response->headers->get('Retry-After'));

        // A proxy holding this page would keep serving the outage after the
        // database came back.
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    #[Test]
    public function a_json_client_is_answered_with_json(): void
    {
        $request = Request::create('/admin/staff');
        $request->headers->set('Accept', 'application/json');

        $response = $this->render($request);

        $this->assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
        $this->assertSame('30', $response->headers->get('Retry-After'));

        $payload = json_decode((string) $response->getContent(), true);

        $this->assertIsArray($payload);
        $this->assertArrayHasKey('message', $payload);

        // Same discretion as the HTML page: a fetch() from the dashboard is
        // no more entitled to the connection details than the page is.
        $this->assertStringNotContainsString('SQLSTATE', $payload['message']);
    }

    #[Test]
    public function an_ordinary_query_failure_still_renders_the_ordinary_error_page(): void
    {
        /*
         * Registering a render callback is easy to over-reach with. This
         * pins the other half of the contract end to end: the handler, not
         * just the detector, has to leave application bugs alone.
         */
        config()->set('app.debug', false);

        $broken = $this->queryException(
            "SQLSTATE[42S22]: Column not found: 1054 Unknown column 'speciality' in 'field list'",
            '42S22',
            1054,
        );

        $response = app(ExceptionHandler::class)->render(Request::create('/admin/dashboard'), $broken);

        $this->assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $this->assertStringNotContainsString('The records service is unavailable', (string) $response->getContent());
    }

    // ------------------------------------------------------------- helpers

    /** Render a fabricated outage through the application's real handler. */
    private function render(Request $request): Response
    {
        return app(ExceptionHandler::class)->render($request, $this->connectionRefused());
    }

    /** The failure reported when nothing is listening on the database port. */
    private function connectionRefused(): QueryException
    {
        return $this->queryException(
            'SQLSTATE[HY000] [2002] No connection could be made because the target machine actively refused it',
            'HY000',
            2002,
        );
    }

    /** A QueryException shaped the way the MySQL driver shapes a real one. */
    private function queryException(string $message, string $sqlState, int $driverCode): QueryException
    {
        /*
         * PDO sets the SQLSTATE onto the inherited `code` property natively,
         * where it is a string rather than the int the Exception signature
         * declares. From outside the class that property is protected and
         * cannot be assigned, so the driver's behaviour is reproduced from
         * inside a subclass — which is the only way to test detection that
         * reads `getCode()` at all.
         */
        $pdo = new class($message, $sqlState, $driverCode) extends PDOException
        {
            public function __construct(string $message, string $sqlState, int $driverCode)
            {
                parent::__construct($message);

                $this->code = $sqlState;
                $this->errorInfo = [$sqlState, $driverCode, $message];
            }
        };

        return new QueryException(
            'mysql',
            'select * from `sessions` where `id` = ? limit 1',
            ['a-session-id'],
            $pdo,
        );
    }
}
