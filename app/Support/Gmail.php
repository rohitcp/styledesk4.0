<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Tenant;
use App\Models\TenantGmailConnection;
use Illuminate\Support\Facades\Http;

/**
 * Connecting a business's Gmail account, and sending through it.
 *
 * Hand-rolled against Google's HTTP endpoints rather than through a client
 * library: the whole of what StyleDesk needs is three calls — swap a code for
 * tokens, refresh a token, post a message — and a dependency for three calls
 * is a dependency to keep patched forever.
 *
 * The reader never sees any of this. The settings screen says "Connect Gmail"
 * and shows a connected address; it does not mention OAuth, scopes or tokens,
 * because a salon owner is connecting their email, not administering an
 * identity provider.
 */
class Gmail
{
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const PROFILE_URL = 'https://www.googleapis.com/oauth2/v3/userinfo';

    private const SEND_URL = 'https://gmail.googleapis.com/gmail/v1/users/me/messages/send';

    /**
     * Everything StyleDesk asks Google for, and nothing else.
     *
     * `gmail.send` only — not read, not modify. StyleDesk sends on the
     * business's behalf and has no business reading their inbox, and a scope
     * asked for is a scope that has to be justified on the consent screen the
     * owner is looking at.
     */
    private const SCOPES = [
        'openid',
        'email',
        'https://www.googleapis.com/auth/gmail.send',
    ];

    /**
     * Whether the platform can offer Gmail at all.
     *
     * Credentials belong to StyleDesk, not to the salon. Without them the
     * provider is switched off everywhere rather than offering a Connect
     * button that lands on a Google error page.
     */
    public static function isConfigured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.redirect'));
    }

    public static function connectionFor(?Tenant $tenant): ?TenantGmailConnection
    {
        return $tenant === null
            ? null
            : TenantGmailConnection::query()->where('tenant_id', $tenant->getTenantKey())->first();
    }

    /**
     * Where to send somebody to say yes.
     *
     * `access_type=offline` with `prompt=consent` because the refresh token is
     * the whole point: without one the connection works until the access token
     * lapses an hour later and then cannot renew itself. Google only issues a
     * refresh token on first consent unless consent is asked for again, so it
     * is asked for again — a second click now beats a mysteriously broken
     * connection in a month.
     */
    public static function authorisationUrl(string $state): string
    {
        return self::AUTH_URL.'?'.http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => config('services.google.redirect'),
            'response_type' => 'code',
            'scope' => implode(' ', self::SCOPES),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ]);
    }

    /**
     * Swap the code Google sent back for tokens, and find out who it is.
     *
     * The address comes from Google rather than from the person connecting:
     * somebody who mistypes their own address should not end up with the
     * salon's mail going out as it.
     *
     * @return array{email: string, name: ?string, access_token: string, refresh_token: ?string, expires_in: int, scope: ?string}
     *
     * @throws \RuntimeException when Google refuses
     */
    public static function exchangeCode(string $code): array
    {
        $token = Http::asForm()->post(self::TOKEN_URL, [
            'code' => $code,
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'redirect_uri' => config('services.google.redirect'),
            'grant_type' => 'authorization_code',
        ]);

        if ($token->failed()) {
            throw new \RuntimeException(self::reason($token->json()));
        }

        $profile = Http::withToken($token->json('access_token'))->get(self::PROFILE_URL);

        if ($profile->failed()) {
            throw new \RuntimeException(self::reason($profile->json()));
        }

        return [
            'email' => (string) $profile->json('email'),
            'name' => $profile->json('name'),
            'access_token' => (string) $token->json('access_token'),
            'refresh_token' => $token->json('refresh_token'),
            'expires_in' => (int) $token->json('expires_in', 3600),
            'scope' => $token->json('scope'),
        ];
    }

    /**
     * A live access token, renewed if the stored one has lapsed.
     *
     * A connection that cannot renew is marked Needs Attention rather than
     * left to fail on every send: the business believes it is set up, and the
     * screen has to be the thing that tells them otherwise.
     *
     * @throws \RuntimeException when the connection can no longer be used
     */
    public static function accessToken(TenantGmailConnection $connection): string
    {
        if (! $connection->accessTokenHasExpired()) {
            return $connection->access_token;
        }

        if (blank($connection->refresh_token)) {
            $connection->flagNeedsAttention(__('client_email.gmail.no_refresh_token'));

            throw new \RuntimeException(__('client_email.gmail.reconnect_needed'));
        }

        $response = Http::asForm()->post(self::TOKEN_URL, [
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'refresh_token' => $connection->refresh_token,
            'grant_type' => 'refresh_token',
        ]);

        if ($response->failed()) {
            /* A revoked grant, a changed password, a deleted account — all of
               them arrive here, and all of them mean the same thing to the
               business: connect it again. */
            $connection->flagNeedsAttention(self::reason($response->json()));

            throw new \RuntimeException(__('client_email.gmail.reconnect_needed'));
        }

        $connection->forceFill([
            'access_token' => $response->json('access_token'),
            'access_expires_at' => now()->addSeconds((int) $response->json('expires_in', 3600)),
            'status' => TenantGmailConnection::STATUS_CONNECTED,
            'last_error' => null,
        ])->save();

        return $connection->access_token;
    }

    /**
     * Put a message in the client's inbox, from the business's own address.
     *
     * Gmail takes a whole RFC 2822 message rather than fields, so the MIME is
     * assembled here. base64url, not base64: the API rejects `+` and `/`.
     *
     * @throws \RuntimeException when Google refuses to send it
     */
    public static function send(
        TenantGmailConnection $connection,
        string $to,
        string $subject,
        string $html,
        ?string $replyTo = null,
        ?string $senderName = null,
    ): void {
        $mime = self::mime(
            from: $connection->email,
            /* The business's own Sender Name, not the Google account's display
               name. An owner who connected a mailbox called "Nadia K" should
               still sign as Smile Spa, and the settings screen is where they
               said so. Google's name is the fallback, not the answer. */
            fromName: $senderName ?: ($connection->google_name ?? ''),
            to: $to,
            subject: $subject,
            html: $html,
            replyTo: $replyTo,
        );

        $response = Http::withToken(self::accessToken($connection))
            ->post(self::SEND_URL, [
                'raw' => rtrim(strtr(base64_encode($mime), '+/', '-_'), '='),
            ]);

        if ($response->failed()) {
            throw new \RuntimeException(self::reason($response->json()));
        }
    }

    /**
     * The message itself.
     *
     * The subject is encoded rather than written through: a subject with an
     * accent in it is not ASCII, and a raw one arrives as mojibake in every
     * client that reads the header literally.
     */
    private static function mime(
        string $from,
        string $fromName,
        string $to,
        string $subject,
        string $html,
        ?string $replyTo,
    ): string {
        $headers = [
            'From: '.($fromName === '' ? $from : '=?UTF-8?B?'.base64_encode($fromName).'?= <'.$from.'>'),
            'To: '.$to,
            'Subject: =?UTF-8?B?'.base64_encode($subject).'?=',
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
        ];

        if (filled($replyTo)) {
            array_splice($headers, 2, 0, ['Reply-To: '.$replyTo]);
        }

        /* Wrapped at 76 characters: base64 in a mail body is a transfer
           encoding with a line limit, and a single long line is rejected or
           silently mangled by some relays. */
        return implode("\r\n", $headers)."\r\n\r\n".chunk_split(base64_encode($html), 76, "\r\n");
    }

    /**
     * Google's own words, or a fallback.
     *
     * Kept rather than replaced with something friendly, because this is
     * written to `last_error` for whoever has to work out why a salon cannot
     * send — and "something went wrong" has never helped anybody do that.
     *
     * @param  array<string, mixed>|null  $body
     */
    private static function reason(?array $body): string
    {
        return (string) (
            data_get($body, 'error.message')
            ?? data_get($body, 'error_description')
            ?? data_get($body, 'error')
            ?? __('client_email.gmail.unknown_error')
        );
    }
}
