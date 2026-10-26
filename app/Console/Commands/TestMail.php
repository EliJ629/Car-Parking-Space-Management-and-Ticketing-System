<?php

namespace App\Console\Commands;

use App\Support\MailFailure;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

/** Sends one test email and says what is wrong when it does not arrive. */
class TestMail extends Command
{
    protected $signature = 'bayline:test-mail {to : The address to send the test email to}';

    protected $description = 'Send a test email with the mail settings from .env and explain any error';

    public function handle(): int
    {
        $to = (string) $this->argument('to');
        $mailer = (string) config('mail.default');
        $smtp = (array) config('mail.mailers.smtp');
        $password = (string) ($smtp['password'] ?? '');
        $from = (string) config('mail.from.address');

        $this->line('Mail settings in use (the password is never shown):');
        $this->line('  MAIL_MAILER        '.$mailer);
        $this->line('  MAIL_HOST          '.($smtp['host'] ?? ''));
        $this->line('  MAIL_PORT          '.($smtp['port'] ?? ''));
        $this->line('  MAIL_USERNAME      '.($smtp['username'] ?? ''));
        $this->line('  MAIL_PASSWORD      '.($password === '' ? '(empty)' : substr($password, 0, 6).'... ('.strlen($password).' characters)'));
        $this->line('  MAIL_FROM_ADDRESS  '.$from);
        $this->newLine();

        $warnings = [];

        if ($mailer !== 'smtp') {
            $warnings[] = "MAIL_MAILER is \"{$mailer}\". Set MAIL_MAILER=smtp in .env, then run: php artisan config:clear";
        }
        if (str_ends_with(strtolower($from), '@example.com')) {
            $warnings[] = 'MAIL_FROM_ADDRESS is still the example address. Use an address your mail service has verified.';
        }
        if (str_starts_with($password, 'xkeysib-')) {
            $warnings[] = 'MAIL_PASSWORD looks like a Brevo API key (xkeysib-). SMTP needs the SMTP key (xsmtpsib-).';
        }
        if (str_contains((string) ($smtp['host'] ?? ''), 'brevo') && ! str_contains((string) ($smtp['username'] ?? ''), '@smtp-brevo.com')) {
            $warnings[] = 'For Brevo, MAIL_USERNAME is the SMTP login shown on the SMTP & API page (it ends in @smtp-brevo.com), not your account email.';
        }
        if (str_contains((string) ($smtp['host'] ?? ''), 'brevo') && preg_match('/@(gmail|googlemail|yahoo|outlook|hotmail)\./i', $from)) {
            $warnings[] = 'MAIL_FROM_ADDRESS is a free mailbox address. Gmail and Yahoo reject or hide mail that Brevo sends "from" such an address. Send from your own domain, or use Gmail SMTP instead.';
        }

        foreach ($warnings as $warning) {
            $this->warn('Check: '.$warning);
        }

        if ($warnings !== []) {
            $this->newLine();
        }

        try {
            Mail::raw('This is a test email from Bayline. If you can read it, sending works.', function ($message) use ($to): void {
                $message->to($to)->subject('Bayline test email');
            });
        } catch (Throwable $e) {
            $this->error('The email was not sent.');
            $this->line(MailFailure::explain($e));
            $this->newLine();
            $this->line('Technical message: '.mb_substr(preg_replace('/\s+/', ' ', $e->getMessage()), 0, 400));

            return self::FAILURE;
        }

        if ($mailer === 'log') {
            $this->warn('MAIL_MAILER is "log", so the email was only written to storage/logs/laravel.log.');

            return self::SUCCESS;
        }

        $this->info("The mail service accepted the email for {$to}. Open that inbox and check Spam too.");
        $this->line('If it never arrives, the service accepted it but the receiving side dropped it. See the Brevo logs (Transactional, Logs).');

        return self::SUCCESS;
    }
}
