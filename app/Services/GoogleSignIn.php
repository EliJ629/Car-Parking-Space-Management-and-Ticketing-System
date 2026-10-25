<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Sign in with Google (OAuth 2.0 authorization code flow), written against Google's three public
 * addresses so the project needs no extra package.
 */
class GoogleSignIn
{
    public const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    public const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    public const USERINFO_URL = 'https://openidconnect.googleapis.com/v1/userinfo';

    /** True when GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET are both set. */
    public function configured(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    /** The address Google sends the person back to. It must match the one entered in Google Cloud exactly. */
    public function redirectUri(): string
    {
        return (string) (config('services.google.redirect') ?: route('driver.google.callback'));
    }

    public function authUrl(string $state): string
    {
        return self::AUTH_URL.'?'.http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'access_type' => 'online',
            'prompt' => 'select_account',
        ]);
    }

    /**
     * Trade the code from Google for the person's profile.
     *
     * @return array{sub:string,email:string,verified:bool,name:string}
     */
    public function profile(string $code): array
    {
        try {
            $token = Http::asForm()->timeout(10)->post(self::TOKEN_URL, [
                'code' => $code,
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'redirect_uri' => $this->redirectUri(),
                'grant_type' => 'authorization_code',
            ]);

            if (! $token->successful() || ! is_string($token->json('access_token'))) {
                throw new GoogleSignInFailed('Google did not accept the sign in. Please try again.');
            }

            $info = Http::withToken($token->json('access_token'))->timeout(10)->get(self::USERINFO_URL);
        } catch (ConnectionException) {
            throw new GoogleSignInFailed('We could not reach Google. Check your connection and try again.');
        }

        if (! $info->successful() || ! is_string($info->json('sub')) || ! is_string($info->json('email'))) {
            throw new GoogleSignInFailed('Google did not share your email address. Please try again.');
        }

        return [
            'sub' => $info->json('sub'),
            'email' => mb_strtolower(trim($info->json('email'))),
            'verified' => filter_var($info->json('email_verified'), FILTER_VALIDATE_BOOLEAN),
            'name' => trim((string) $info->json('name')),
        ];
    }
}
