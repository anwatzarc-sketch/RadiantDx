<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The command that proves the mail settings.
 *
 * Worth testing despite being a diagnostic, because a diagnostic that lies is
 * worse than none: it is run on a server with no shell, by someone who cannot
 * see behind it, and its answer decides whether the settings get changed.
 *
 * The property that matters most is the one that was wrong first time round —
 * it must not report success when it has not been anywhere near SMTP.
 */
class TestMailCommandTest extends TestCase
{
    /** Somewhere nothing is listening, so the transport fails quickly. */
    private const DEAD = ['--host' => '127.0.0.1', '--port' => '2', '--scheme' => ''];

    #[Test]
    public function it_tests_smtp_even_when_the_application_is_configured_to_log_mail(): void
    {
        /*
         * The original bug. With MAIL_MAILER=log the command sent through the
         * log mailer, which cannot fail, and announced success — telling the
         * operator the SMTP settings were good when nothing had tried them.
         */
        config(['mail.default' => 'log']);

        $this->artisan('mail:test', ['recipient' => 'someone@example.com'] + self::DEAD)
            ->assertExitCode(1);
    }

    #[Test]
    public function it_says_so_when_the_application_is_not_sending_over_smtp(): void
    {
        config(['mail.default' => 'log']);

        $this->artisan('mail:test', ['recipient' => 'someone@example.com'] + self::DEAD)
            ->expectsOutputToContain('MAIL_MAILER')
            ->assertExitCode(1);
    }

    #[Test]
    public function it_refuses_an_address_that_is_not_one(): void
    {
        // Rejected before anything is sent, so a typo costs no waiting.
        $this->artisan('mail:test', ['recipient' => 'not-an-address'])
            ->assertExitCode(1);
    }

    #[Test]
    public function the_overrides_reach_the_transport(): void
    {
        config([
            'mail.mailers.smtp.host' => 'from.env.example',
            'mail.mailers.smtp.port' => 587,
            'mail.mailers.smtp.scheme' => 'smtp',
        ]);

        $this->artisan('mail:test', ['recipient' => 'someone@example.com'] + self::DEAD)
            ->assertExitCode(1);

        // Whatever was in .env, the flags are what got used — otherwise the
        // trial-and-error loop the command exists for would be a no-op.
        $this->assertSame('127.0.0.1', config('mail.mailers.smtp.host'));
        $this->assertSame(2, config('mail.mailers.smtp.port'));
    }

    #[Test]
    public function an_empty_scheme_means_no_encryption_rather_than_no_opinion(): void
    {
        config(['mail.mailers.smtp.scheme' => 'smtps']);

        $this->artisan('mail:test', ['recipient' => 'someone@example.com'] + self::DEAD)
            ->assertExitCode(1);

        /*
         * `--scheme=` is a real answer — plaintext — and has to be told apart
         * from the flag being absent. If it were treated as "unset", port 25
         * could never be tested.
         */
        $this->assertNull(config('mail.mailers.smtp.scheme'));
    }
}
