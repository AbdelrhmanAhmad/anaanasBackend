<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Lightweight bridge between Laravel and the Node.js websocket-server.
 *
 * The Node.js server exposes an HTTP `/publish` endpoint that fans out
 * payloads to every websocket subscribed to a given channel. We use this
 * to broadcast events scoped per country (the channel is the country
 * subdomain ISO code, e.g. "jo", "us").
 */
class RealtimeBroadcaster
{
    /**
     * Publish an arbitrary event to the websocket-server.
     *
     * @param  string  $channel  e.g. "country:jo" or "global"
     * @param  string  $event    e.g. "post.created"
     * @param  array   $payload  any json-serializable data
     */
    public static function publish(string $channel, string $event, array $payload = []): bool
    {
        $url = (string) config('services.realtime.publish_url', env('REALTIME_PUBLISH_URL', 'http://127.0.0.1:6001/publish'));
        $secret = (string) config('services.realtime.secret', env('REALTIME_PUBLISH_SECRET', ''));

        if ($url === '') {
            return false;
        }

        try {
            $response = Http::withHeaders([
                    'X-Publish-Secret' => $secret,
                    'Content-Type' => 'application/json',
                ])
                ->timeout(2)
                ->connectTimeout(1)
                ->post($url, [
                    'channel' => $channel,
                    'event' => $event,
                    'payload' => $payload,
                ]);

            return $response->successful();
        } catch (\Throwable $e) {
            // Best-effort: never let realtime issues take the request down.
            Log::warning('[realtime] publish failed', [
                'channel' => $channel,
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Convenience helper to publish an event to a country-scoped channel.
     */
    public static function publishToCountry(?string $countryCode, string $event, array $payload = []): bool
    {
        $code = strtolower(trim((string) $countryCode));
        if ($code === '') {
            return self::publish('global', $event, $payload);
        }
        return self::publish('country:' . $code, $event, $payload);
    }
}
