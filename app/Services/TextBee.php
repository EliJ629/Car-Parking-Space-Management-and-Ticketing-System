<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Sends a text message through TextBee (textbee.dev): POST /gateway/send-sms with the API key in the x-api-key header.
 * A 200 answer means TextBee queued the message on the Android phone, not that it was delivered.
 */
class TextBee
{
    public function configured(): bool
    {
        return filled(config('textbee.api_key')) && filled(config('textbee.device_id'));
    }

    /** 09171234567 and +639171234567 become +639171234567, the form TextBee needs. Anything else gives null. */
    public static function international(?string $phone): ?string
    {
        $phone = trim((string) $phone);

        if (preg_match('/^09(\d{9})$/', $phone, $m)) {
            return '+639'.$m[1];
        }

        return preg_match('/^\+639\d{9}$/', $phone) ? $phone : null;
    }

    /**
     * @return string the batch id TextBee gives, for the delivery log on its dashboard
     *
     * @throws TextBeeFailed
     */
    public function send(string $phone, string $message): string
    {
        if (! $this->configured()) {
            throw new TextBeeFailed('TextBee is not set up. Put TEXTBEE_API_KEY and TEXTBEE_DEVICE_ID in .env.');
        }

        $to = self::international($phone);

        if ($to === null) {
            throw new TextBeeFailed('The mobile number is not a Philippine number.');
        }

        try {
            $response = Http::withHeaders(['x-api-key' => (string) config('textbee.api_key')])
                ->acceptJson()
                ->timeout(15)
                ->post(config('textbee.base_url').'/gateway/send-sms', [
                    'deviceId' => (string) config('textbee.device_id'),
                    'recipients' => [$to],
                    'message' => $message,
                ]);
        } catch (ConnectionException $e) {
            throw new TextBeeFailed('Could not reach TextBee. Check the internet connection (on Windows, the cacert.pem setting in php.ini).', 0, $e);
        }

        if ($response->failed()) {
            throw new TextBeeFailed(match ($response->status()) {
                401 => 'TextBee refused the API key. Check TEXTBEE_API_KEY.',
                404 => 'TextBee does not know this device. Check TEXTBEE_DEVICE_ID.',
                400 => 'TextBee has no enabled phone online. Open the TextBee app on the phone and check that the device is enabled.',
                429 => 'The TextBee plan limit is used up. Nothing was sent.',
                default => 'TextBee answered with error '.$response->status().'.',
            });
        }

        if ($response->json('data.success') !== true) {
            throw new TextBeeFailed('TextBee did not accept the message.');
        }

        return (string) $response->json('data.smsBatchId', '');
    }
}
