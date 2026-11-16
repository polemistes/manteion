<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Reads the details of a public Facebook event from the page Facebook
 * serves a link-preview crawler: its name, description, times, place and
 * cover picture. Nothing is read that a signed-out visitor could not see,
 * so a private event (answered with a sign-in page) comes back as null.
 *
 * The page is Facebook's own to change: everything here is read from the
 * JSON it embeds for its scripts, and a page that no longer carries it
 * simply imports nothing.
 */
class FacebookEventImporter
{
    private const USER_AGENT = 'Mozilla/5.0 (compatible; ManteionBot/1.0; +https://manteion.no)';

    /** A short share link reaches the event in two hops; this leaves room. */
    private const MAX_REDIRECTS = 4;

    private const MAX_IMAGE_BYTES = 4 * 1024 * 1024;

    /** @var list<string> */
    private const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    /**
     * Paths a link to an event may have: the event itself, the old
     * event.php address, and the short share links that redirect to it.
     *
     * @var list<string>
     */
    private const EVENT_PATHS = ['/events/', '/event.php', '/e/', '/share/', '/event_invite/'];

    private const NAME_MAX = 255;

    private const DESCRIPTION_MAX = 5000;

    /**
     * Whether the address is one a Facebook event can be reached at.
     */
    public function isEventLink(string $url): bool
    {
        return $this->facebookAddress($url) !== null;
    }

    /**
     * The event's details, shaped as the event form takes them, or null
     * when the link leads to nothing a signed-out visitor can read.
     *
     * @return array{name: string, description: string, starts_at: string, ends_at: string|null, place: string|null, latitude: float|null, longitude: float|null, city: string|null, image: string|null}|null
     */
    public function import(string $url): ?array
    {
        $page = $this->fetchEventPage($url);

        if ($page === null) {
            return null;
        }

        $details = $this->parse($page['html'], $page['id']);

        if ($details === null) {
            return null;
        }

        $coverUrl = $details['cover_url'];
        unset($details['cover_url']);

        return [...$details, 'image' => $coverUrl === null ? null : $this->fetchImage($coverUrl)];
    }

    /**
     * Follows the link to the event's page. Only Facebook's own addresses
     * are ever requested, redirects included, and an address that names
     * the event is swapped for the event's plain one.
     *
     * @return array{id: string, html: string}|null
     */
    private function fetchEventPage(string $url): ?array
    {
        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            $id = $this->eventIdFrom($url);
            $address = $id === null ? $this->facebookAddress($url) : "https://www.facebook.com/events/{$id}/";

            if ($address === null) {
                return null;
            }

            $response = $this->get($address, 'text/html');

            if ($response === null) {
                return null;
            }

            if ($response->redirect()) {
                $url = $this->absolute($response->header('Location'), $address);

                continue;
            }

            if (! $response->successful()) {
                return null;
            }

            $html = $response->body();
            $id ??= $this->eventIdFrom($this->canonicalUrl($html) ?? '');

            return $id === null ? null : ['id' => $id, 'html' => $html];
        }

        return null;
    }

    /**
     * The link as an https address on Facebook, or null when it is not a
     * Facebook address or not one an event lives at.
     */
    private function facebookAddress(string $url): ?string
    {
        $parts = parse_url(trim($url));

        if (! is_array($parts) || ! in_array(Str::lower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
            return null;
        }

        $host = Str::lower($parts['host'] ?? '');
        $path = $parts['path'] ?? '/';

        if (! $this->isFacebookHost($host) || ! Str::startsWith($path, self::EVENT_PATHS)) {
            return null;
        }

        return "https://{$host}{$path}".(isset($parts['query']) ? '?'.$parts['query'] : '');
    }

    private function isFacebookHost(string $host): bool
    {
        return in_array($host, ['facebook.com', 'fb.com', 'fb.me'], true)
            || Str::endsWith($host, ['.facebook.com', '.fb.com']);
    }

    /**
     * The event a Facebook address names, if it names one: the number
     * that ends /events/…, or `eid` on the old event.php address. One
     * occurrence of a repeating event has its own id (`event_time_id`).
     */
    private function eventIdFrom(string $url): ?string
    {
        if ($this->facebookAddress($url) === null) {
            return null;
        }

        $segments = array_values(array_filter(explode('/', (string) parse_url($url, PHP_URL_PATH)), fn (string $segment) => $segment !== ''));
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $candidates = match ($segments[0] ?? null) {
            'events' => [$query['event_time_id'] ?? null, ...array_reverse(array_slice($segments, 1)), $query['eid'] ?? null],
            'event.php' => [$query['eid'] ?? null],
            default => [],
        };

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && preg_match('/^\d{5,25}$/', $candidate) === 1) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * The address a page gives as its own (og:url).
     */
    private function canonicalUrl(string $html): ?string
    {
        if (preg_match('/<meta property="og:url" content="([^"]+)"/', $html, $matches) !== 1) {
            return null;
        }

        return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5);
    }

    private function absolute(string $location, string $base): string
    {
        if (Str::startsWith($location, '/') && ! Str::startsWith($location, '//')) {
            return 'https://'.parse_url($base, PHP_URL_HOST).$location;
        }

        return $location;
    }

    /**
     * One request, never following a redirect by itself: where a
     * redirect leads is checked before it is followed.
     */
    private function get(string $address, string $accept): ?Response
    {
        try {
            return Http::timeout(15)
                ->withHeaders(['User-Agent' => self::USER_AGENT, 'Accept' => $accept])
                ->withOptions(['allow_redirects' => false])
                ->get($address);
        } catch (ConnectionException) {
            return null;
        }
    }

    /**
     * Facebook describes the event in several pieces of embedded JSON,
     * each carrying the event's id (the page also lists other, suggested
     * events, told apart by theirs). The end time sits in a piece without
     * an id, recognised by its matching start time.
     *
     * @return array{name: string, description: string, starts_at: string, ends_at: string|null, place: string|null, latitude: float|null, longitude: float|null, city: string|null, cover_url: string|null}|null
     */
    private function parse(string $html, string $eventId): ?array
    {
        $pieces = [];
        $times = [];

        foreach ($this->embeddedJson($html) as $json) {
            if (! str_contains($json, $eventId) && ! str_contains($json, '"end_timestamp"')) {
                continue;
            }

            $data = json_decode($json, true);

            if (is_array($data)) {
                $this->collect($data, $eventId, $pieces, $times);
            }
        }

        $first = function (string ...$paths) use ($pieces): mixed {
            foreach ($paths as $path) {
                foreach ($pieces as $piece) {
                    $value = data_get($piece, $path);

                    if ($value !== null && $value !== '') {
                        return $value;
                    }
                }
            }

            return null;
        };

        $name = $this->text($first('name'), self::NAME_MAX);
        $start = $first('start_timestamp', 'current_start_timestamp');

        if ($name === null || ! is_int($start) || $start <= 0) {
            return null;
        }

        $end = collect($times)
            ->first(fn (array $time) => ($time['start_timestamp'] ?? null) === $start && is_int($time['end_timestamp'] ?? null) && $time['end_timestamp'] > $start);

        $latitude = $first('event_place.location.latitude');
        $longitude = $first('event_place.location.longitude');
        $hasPin = is_numeric($latitude) && is_numeric($longitude) && abs((float) $latitude) <= 90 && abs((float) $longitude) <= 180;

        // "Oslo, Norge": the city is the part before the comma.
        $city = $this->text($first('event_place.city.contextual_name', 'location.reverse_geocode.city_page.name'), self::NAME_MAX);

        return [
            'name' => $name,
            'description' => $this->text($first('event_description.text'), self::DESCRIPTION_MAX) ?? '',
            'starts_at' => CarbonImmutable::createFromTimestampUTC($start)->toIso8601ZuluString(),
            'ends_at' => $end === null ? null : CarbonImmutable::createFromTimestampUTC($end['end_timestamp'])->toIso8601ZuluString(),
            'place' => $this->text($first('event_place.name', 'event_place.contextual_name'), self::NAME_MAX),
            'latitude' => $hasPin ? (float) $latitude : null,
            'longitude' => $hasPin ? (float) $longitude : null,
            'city' => $city === null ? null : trim(Str::before($city, ',')),
            'cover_url' => is_string($cover = $first('cover_media_renderer.cover_photo.photo.full_image.uri')) ? $cover : null,
        ];
    }

    /**
     * The contents of the page's <script type="application/json"> tags.
     *
     * @return list<string>
     */
    private function embeddedJson(string $html): array
    {
        $blocks = [];
        $offset = 0;

        while (($open = strpos($html, '<script type="application/json"', $offset)) !== false) {
            $start = strpos($html, '>', $open);
            $end = $start === false ? false : strpos($html, '</script>', $start);

            if ($start === false || $end === false) {
                break;
            }

            $blocks[] = substr($html, $start + 1, $end - $start - 1);
            $offset = $end;
        }

        return $blocks;
    }

    /**
     * Gathers every object carrying the event's id, and every object
     * carrying an end time, from anywhere in the decoded JSON.
     *
     * @param  array<array-key, mixed>  $node
     * @param  list<array<array-key, mixed>>  $pieces
     * @param  list<array<array-key, mixed>>  $times
     */
    private function collect(array $node, string $eventId, array &$pieces, array &$times): void
    {
        if (is_scalar($node['id'] ?? null) && (string) $node['id'] === $eventId) {
            $pieces[] = $node;
        }

        if (array_key_exists('end_timestamp', $node)) {
            $times[] = $node;
        }

        foreach ($node as $child) {
            if (is_array($child)) {
                $this->collect($child, $eventId, $pieces, $times);
            }
        }
    }

    private function text(mixed $value, int $maxLength): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $text = trim(mb_substr(trim(str_replace("\r\n", "\n", $value)), 0, $maxLength));

        return $text === '' ? null : $text;
    }

    /**
     * The cover picture as a data: URL, so the browser never asks
     * Facebook for it. Only Facebook's picture servers are asked, and
     * only a real picture of a sensible size comes back.
     */
    private function fetchImage(string $url): ?string
    {
        $host = Str::lower((string) parse_url($url, PHP_URL_HOST));

        if (parse_url($url, PHP_URL_SCHEME) !== 'https' || ! Str::endsWith($host, '.fbcdn.net')) {
            return null;
        }

        $response = $this->get($url, 'image/jpeg, image/png, image/webp');

        if ($response === null || ! $response->successful()) {
            return null;
        }

        $body = $response->body();

        if ($body === '' || strlen($body) > self::MAX_IMAGE_BYTES) {
            return null;
        }

        $type = (@getimagesizefromstring($body) ?: [])['mime'] ?? null;

        if (! in_array($type, self::IMAGE_TYPES, true)) {
            return null;
        }

        return "data:{$type};base64,".base64_encode($body);
    }
}
