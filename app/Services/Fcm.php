<?php

namespace App\Services;

use App\Enums\FcmDelivery;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends pushes to the Android app through Firebase Cloud Messaging's HTTP v1
 * API. Configured by FCM_CREDENTIALS (the Firebase project's service-account
 * key file) and optionally FCM_PROJECT_ID (read from the key file when
 * empty); without them nothing is sent.
 *
 * One device's failure never throws: the channel goes on to the user's next
 * device, and a thrown exception would fail the whole queued notification.
 */
class Fcm
{
    public function __construct(private FcmAccessToken $accessToken) {}

    /**
     * Whether the server has what it needs to send through FCM.
     */
    public static function configured(): bool
    {
        return self::projectId() !== null;
    }

    /**
     * The Firebase project id: FCM_PROJECT_ID, or the key file's own.
     */
    public static function projectId(): ?string
    {
        $credentials = config('services.fcm.credentials');

        if (! is_string($credentials) || $credentials === '' || ! is_readable($credentials)) {
            return null;
        }

        $projectId = config('services.fcm.project_id');

        if (is_string($projectId) && $projectId !== '') {
            return $projectId;
        }

        $key = json_decode((string) file_get_contents($credentials), true);
        $projectId = is_array($key) ? ($key['project_id'] ?? null) : null;

        return is_string($projectId) && $projectId !== '' ? $projectId : null;
    }

    /**
     * Send one message (FCM's `message` object without the token) to one
     * app install.
     *
     * @param  array<string, mixed>  $message
     */
    public function send(string $token, array $message): FcmDelivery
    {
        $projectId = self::projectId();

        if ($projectId === null) {
            return FcmDelivery::Failed;
        }

        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";
        $body = ['message' => ['token' => $token, ...$message]];

        try {
            $response = $this->post($url, $body);

            // The cached access token was revoked or ran out early: once more
            // with a fresh one.
            if ($response->status() === 401) {
                $this->accessToken->forget();
                $response = $this->post($url, $body);
            }
        } catch (ConnectionException $exception) {
            Log::warning('FCM could not be reached.', ['error' => $exception->getMessage()]);

            return FcmDelivery::Failed;
        } catch (Throwable $exception) {
            Log::warning('An FCM push failed.', ['error' => $exception->getMessage()]);

            return FcmDelivery::Failed;
        }

        if ($response->successful()) {
            return FcmDelivery::Sent;
        }

        if (self::isGone($response)) {
            return FcmDelivery::Unregistered;
        }

        Log::warning('FCM refused a push.', ['status' => $response->status(), 'body' => $response->json() ?? $response->body()]);

        return FcmDelivery::Failed;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function post(string $url, array $body): Response
    {
        return Http::withToken($this->accessToken->get())
            ->timeout(10)
            ->connectTimeout(5)
            ->acceptJson()
            ->post($url, $body);
    }

    /**
     * Whether FCM says the token itself is dead: the app was uninstalled or
     * its token deleted (UNREGISTERED), the token belongs to another
     * Firebase project (SENDER_ID_MISMATCH), or it isn't a token at all. An
     * INVALID_ARGUMENT about anything else is our message's fault, and must
     * not cost the user their device.
     */
    private static function isGone(Response $response): bool
    {
        $error = $response->json('error');

        if (! is_array($error)) {
            return false;
        }

        $codes = [];

        foreach (is_array($error['details'] ?? null) ? $error['details'] : [] as $detail) {
            if (is_array($detail) && is_string($detail['errorCode'] ?? null)) {
                $codes[] = $detail['errorCode'];
            }
        }

        if (array_intersect($codes, ['UNREGISTERED', 'SENDER_ID_MISMATCH']) !== []) {
            return true;
        }

        return ($error['status'] ?? null) === 'INVALID_ARGUMENT'
            && str_contains(strtolower((string) ($error['message'] ?? '')), 'registration token');
    }
}
