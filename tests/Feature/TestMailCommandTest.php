<?php

namespace Tests\Feature;

use App\Support\MailFailure;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** php artisan bayline:test-mail, and the plain-language mail error hints. */
class TestMailCommandTest extends TestCase
{
    public function test_it_sends_a_test_email_and_hides_the_password(): void
    {
        Mail::fake();
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.password' => 'xsmtpsib-SECRET-VALUE-123', 'mail.from.address' => 'hello@bayline.test']);

        $this->artisan('bayline:test-mail', ['to' => 'someone@gmail.com'])
            ->expectsOutputToContain('xsmtps...')
            ->doesntExpectOutputToContain('SECRET-VALUE')
            ->expectsOutputToContain('accepted the email')
            ->assertSuccessful();
    }

    public function test_it_warns_about_the_usual_mistakes(): void
    {
        Mail::fake();
        config([
            'mail.default' => 'log',
            'mail.mailers.smtp.host' => 'smtp-relay.brevo.com',
            'mail.mailers.smtp.username' => 'me@gmail.com',
            'mail.mailers.smtp.password' => 'xkeysib-api-key',
            'mail.from.address' => 'me@gmail.com',
        ]);

        $this->artisan('bayline:test-mail', ['to' => 'someone@gmail.com'])
            ->expectsOutputToContain('MAIL_MAILER is "log"')
            ->expectsOutputToContain('API key')
            ->expectsOutputToContain('SMTP login')
            ->expectsOutputToContain('free mailbox')
            ->assertSuccessful();
    }

    public function test_it_explains_a_refused_login(): void
    {
        Mail::shouldReceive('raw')->once()->andThrow(new \RuntimeException('Failed to authenticate on SMTP server. Expected response code "235" but got code "535"'));

        $this->artisan('bayline:test-mail', ['to' => 'someone@gmail.com'])
            ->expectsOutputToContain('The email was not sent.')
            ->expectsOutputToContain('refused the login')
            ->assertFailed();
    }

    public function test_error_hints(): void
    {
        $this->assertStringContainsString('not activated', MailFailure::explain(new \RuntimeException('450 Your SMTP account is not yet activated')));
        $this->assertStringContainsString('could not be reached', MailFailure::explain(new \RuntimeException('Connection could not be established with host "x:587"')));
        $this->assertStringContainsString('cacert', MailFailure::explain(new \RuntimeException('SSL certificate problem')));
        $this->assertStringContainsString('laravel.log', MailFailure::explain(new \RuntimeException('something odd')));
    }

    public function test_the_screen_only_shows_the_hint_in_debug_mode(): void
    {
        config(['app.debug' => false]);
        $this->assertSame('', MailFailure::screenDetail(new \RuntimeException('535')));
        config(['app.debug' => true]);
        $this->assertStringContainsString('refused the login', MailFailure::screenDetail(new \RuntimeException('535')));
    }
}
