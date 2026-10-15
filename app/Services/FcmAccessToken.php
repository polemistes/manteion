<?php

namespace App\Services;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * The OAuth access token the FCM HTTP v1 API wants, fetched from Google with
 * the service-account key in config('services.fcm.credentials') and cached
 * until shortly before it runs out (about an hour). Bound in the container,
 * so tests swap it for one that never calls Google.
 */
class FcmAccessToken
{
    private const CACHE_KEY = 'fcm.access_token';

    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    /**
     * Stop using a token this many seconds before Google says it expires.
     */
    private const EXPIRY_MARGIN = 300;

    public function get(): string
    {
        $cached = Cache::get(self::CACHE_KEY);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $token = (new ServiceAccountCredentials(self::SCOPE, (string) config('services.fcm.credentials')))->fetchAuthToken();
        $accessToken = $token['access_token'] ?? null;

        if (! is_string($accessToken) || $accessToken === '') {
            throw new RuntimeException('Google returned no FCM access token.');
        }

        $lifetime = (int) ($token['expires_in'] ?? 3600) - self::EXPIRY_MARGIN;

        if ($lifetime > 0) {
            Cache::put(self::CACHE_KEY, $accessToken, $lifetime);
        }

        return $accessToken;
    }

    /**
     * Drop the cached token, after FCM refused it.
     */
    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
