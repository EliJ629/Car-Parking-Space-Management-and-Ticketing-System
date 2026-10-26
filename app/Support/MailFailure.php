<?php

namespace App\Support;

use Throwable;

/** Turns a mail error into a plain sentence that says what to check. */
class MailFailure
{
    public static function explain(Throwable $e): string
    {
        $text = strtolower($e->getMessage().' '.($e->getPrevious()?->getMessage() ?? ''));

        return match (true) {
            str_contains($text, 'not yet activated') => 'The mail service has not activated this SMTP account yet. Ask its support to activate it.',
            str_contains($text, 'authenticate') || str_contains($text, '535') || str_contains($text, 'authentication') => 'The mail service refused the login. Check MAIL_USERNAME and MAIL_PASSWORD in .env (use the SMTP login and SMTP key, or the Gmail app password).',
            str_contains($text, 'certificate') || str_contains($text, 'ssl') => 'The secure connection failed on a certificate. Set curl.cainfo and openssl.cafile in php.ini to a cacert.pem file.',
            str_contains($text, 'connection could not be established') || str_contains($text, 'timed out') || str_contains($text, 'getaddrinfo') || str_contains($text, 'connection refused') => 'The mail server could not be reached. Check MAIL_HOST and MAIL_PORT in .env, then your internet or firewall.',
            str_contains($text, 'sender') || str_contains($text, 'from address') || str_contains($text, 'not valid') => 'The From address is not allowed. Set MAIL_FROM_ADDRESS to an address the mail service has verified.',
            default => 'The mail service refused the message. The exact reason is in storage/logs/laravel.log.',
        };
    }

    /** What the screen may add to its message. Only shown while APP_DEBUG is on, so visitors never see mail settings. */
    public static function screenDetail(Throwable $e): string
    {
        if (! config('app.debug')) {
            return '';
        }

        return ' ('.self::explain($e).')';
    }
}
