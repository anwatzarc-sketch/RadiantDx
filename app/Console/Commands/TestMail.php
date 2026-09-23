<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

/**
 * Prove the mail settings, from the machine that will actually send.
 *
 * This exists because of a specific gap. The mail provider hands over a host
 * and a mailbox and names neither a port nor an encryption mode, the server
 * refuses connections from outside its own network — so it cannot be probed
 * from a laptop — and the production host has no shell to try it from. That
 * leaves editing `.env`, deploying, and guessing from a silent failure.
 *
 * So: a command, runnable from the panel's Laravel extension, that says which
 * settings were used and what the transport actually objected to.
 *
 * The overrides are the point. Finding the right combination means trying
 * three or four, and each attempt would otherwise be an edit-and-redeploy:
 *
 *     php artisan mail:test someone@example.com
 *     php artisan mail:test someone@example.com --port=465 --scheme=smtps
 *     php artisan mail:test someone@example.com --port=25  --scheme=
 *
 * Nothing here writes to `.env`. Once a combination works, put those values in
 * `.env` by hand — a command that rewrote it would be editing a file this
 * deployment deliberately keeps off the server's git tree and out of reach of
 * anything automated.
 */
final class TestMail extends Command
{
    protected $signature = 'mail:test
        {recipient? : Address to send to. Defaults to MAIL_FROM_ADDRESS.}
        {--host= : Override MAIL_HOST for this attempt only}
        {--port= : Override MAIL_PORT for this attempt only}
        {--scheme= : Override MAIL_SCHEME. Pass --scheme= (empty) for no encryption.}
        {--username= : Override MAIL_USERNAME for this attempt only}';

    protected $description = 'Send one message to prove the SMTP settings, reporting exactly what failed';

    public function handle(): int
    {
        $this->applyOverrides();

        $config = (array) config('mail.mailers.smtp');
        $recipient = (string) ($this->argument('recipient') ?? config('mail.from.address'));

        if ($recipient === '' || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $this->components->error('Give a valid recipient, or set MAIL_FROM_ADDRESS.');

            return self::FAILURE;
        }

        /*
         * Printed before the attempt, because the most common cause of a
         * confusing result is not the network at all — it is a cached config
         * still serving the previous .env. If these values are not the ones
         * just edited, run `optimize:clear` and try again.
         */
        $this->components->twoColumnDetail('<fg=gray>testing mailer</>', 'smtp');
        $this->components->twoColumnDetail('<fg=gray>host</>', (string) ($config['host'] ?? '—'));
        $this->components->twoColumnDetail('<fg=gray>port</>', (string) ($config['port'] ?? '—'));
        $this->components->twoColumnDetail('<fg=gray>scheme</>', ($config['scheme'] ?? null) ?: '(none — plaintext)');
        $this->components->twoColumnDetail('<fg=gray>username</>', (string) ($config['username'] ?? '—'));
        $this->components->twoColumnDetail('<fg=gray>password</>', ($config['password'] ?? '') !== '' ? '(set)' : '<fg=red>(empty)</>');
        $this->components->twoColumnDetail('<fg=gray>from</>', (string) config('mail.from.address'));
        $this->components->twoColumnDetail('<fg=gray>to</>', $recipient);
        $this->newLine();

        /*
         * The default mailer is reported but deliberately not used. Sending
         * through it would mean that with MAIL_MAILER=log this command writes
         * a line to the log and announces success, having proved nothing about
         * SMTP at all — the exact false result it exists to prevent.
         */
        $default = (string) config('mail.default');

        if ($default !== 'smtp') {
            $this->components->warn(
                "MAIL_MAILER is '{$default}', so the application is not sending over SMTP yet. ".
                'This test uses the smtp mailer regardless; set MAIL_MAILER=smtp once it passes.'
            );
        }

        try {
            Mail::mailer('smtp')->raw($this->body(), function ($message) use ($recipient): void {
                $message->to($recipient)->subject('RadiantDx mail test');
            });
        } catch (TransportExceptionInterface $e) {
            $this->components->error('The mail server refused the message.');
            $this->line('  <fg=red>'.$e->getMessage().'</>');
            $this->newLine();
            $this->explain($e->getMessage());

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->components->error('Sending failed before the transport was reached.');
            $this->line('  <fg=red>'.$e::class.': '.$e->getMessage().'</>');

            return self::FAILURE;
        }

        $this->components->info('Accepted by the server. Check the inbox — delivery is a separate question from acceptance.');

        return self::SUCCESS;
    }

    /**
     * Apply the one-off overrides and drop any mailer already built from the
     * old configuration, which would otherwise be reused and silently ignore
     * every flag passed.
     */
    private function applyOverrides(): void
    {
        $map = [
            'host' => 'mail.mailers.smtp.host',
            'port' => 'mail.mailers.smtp.port',
            'username' => 'mail.mailers.smtp.username',
        ];

        foreach ($map as $option => $key) {
            $value = $this->option($option);

            if ($value !== null && $value !== '') {
                config([$key => $option === 'port' ? (int) $value : $value]);
            }
        }

        /*
         * Handled apart from the others because an empty --scheme is a
         * meaningful answer — "no encryption" — and must not be confused with
         * the flag being absent.
         */
        if ($this->input->hasParameterOption('--scheme')) {
            $scheme = (string) $this->option('scheme');
            config(['mail.mailers.smtp.scheme' => $scheme === '' ? null : $scheme]);
        }

        Mail::purge('smtp');
    }

    /** Turn the transport's complaint into the next thing to try. */
    private function explain(string $error): void
    {
        $error = mb_strtolower($error);

        $hint = match (true) {
            str_contains($error, 'authentication')
            || str_contains($error, '535')
            || str_contains($error, '534') => 'The connection worked; the credentials did not. Check MAIL_USERNAME is the full address and that its mailbox password is the one set in the mail control panel, which is not always the portal password.',

            str_contains($error, 'connection could not be established')
            || str_contains($error, 'connection refused')
            || str_contains($error, 'timed out') => 'Nothing answered on that port. Try --port=465 --scheme=smtps, then --port=25 --scheme= (empty). If none answer, the host is firewalled from this server and that is a question for the provider.',

            str_contains($error, 'ssl')
            || str_contains($error, 'tls')
            || str_contains($error, 'certificate') => 'The port answered but the encryption did not match. Port 587 wants --scheme=smtp (STARTTLS); port 465 wants --scheme=smtps. A self-signed certificate on the mail host will also land here.',

            str_contains($error, 'sender')
            || str_contains($error, 'from')
            || str_contains($error, '553')
            || str_contains($error, '550') => 'The server rejected the sender. Most providers require MAIL_FROM_ADDRESS to be the same mailbox as MAIL_USERNAME — an unrouted no-reply@ address is the usual cause.',

            default => 'Try the other port and scheme combinations: 587 with --scheme=smtp, 465 with --scheme=smtps, 25 with --scheme= (empty).',
        };

        $this->components->bulletList([$hint]);
    }

    private function body(): string
    {
        return implode(PHP_EOL, [
            'This is a test message from RadiantDx.',
            '',
            'If you are reading it, the application can send mail from '.config('app.url').'.',
            'Sent '.now()->utc()->format('d M Y H:i:s').' UTC.',
        ]);
    }
}
