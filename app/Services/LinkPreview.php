<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Fetches the title, description and preview image a page advertises
 * (Open Graph, Twitter cards, then plain <title>/<meta description>).
 */
class LinkPreview
{
    private const MAX_BYTES = 512 * 1024;

    /**
     * @return array{title: string|null, description: string|null, image_url: string|null}|null
     */
    public function fetch(string $url): ?array
    {
        if (! $this->isFetchable($url)) {
            return null;
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; ManteionBot/1.0; +https://manteion.no)', 'Accept' => 'text/html'])
                ->withOptions(['allow_redirects' => ['max' => 3]])
                ->get($url);
        } catch (ConnectionException|RequestException) {
            return null;
        }

        if (! $response->successful() || ! Str::contains($response->header('Content-Type'), 'html', true)) {
            return null;
        }

        return $this->parse(substr($response->body(), 0, self::MAX_BYTES), $url);
    }

    /**
     * Only public web addresses are fetched: a preview must never become
     * a way to make the server read something on its own network.
     */
    private function isFetchable(string $url): bool
    {
        $parts = parse_url($url);
        $host = $parts['host'] ?? null;

        if (! in_array($parts['scheme'] ?? null, ['http', 'https'], true) || $host === null) {
            return false;
        }

        if ($host === 'localhost' || Str::endsWith($host, ['.local', '.localhost', '.internal'])) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        }

        return true;
    }

    /**
     * @return array{title: string|null, description: string|null, image_url: string|null}
     */
    private function parse(string $html, string $url): array
    {
        $document = new DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();

        $xpath = new DOMXPath($document);

        $meta = function (string ...$names) use ($xpath): ?string {
            foreach ($names as $name) {
                $nodes = $xpath->query("//meta[@property='{$name}' or @name='{$name}']/@content");
                $value = $nodes !== false && $nodes->length > 0 ? trim($nodes->item(0)->nodeValue ?? '') : '';

                if ($value !== '') {
                    return $value;
                }
            }

            return null;
        };

        $titleNodes = $xpath->query('//title');
        $title = $meta('og:title', 'twitter:title')
            ?? ($titleNodes !== false && $titleNodes->length > 0 ? trim($titleNodes->item(0)->nodeValue ?? '') : null);
        $description = $meta('og:description', 'twitter:description', 'description');
        $image = $meta('og:image', 'og:image:url', 'twitter:image');

        return [
            'title' => $title === null || $title === '' ? null : Str::limit($title, 250),
            'description' => $description === null ? null : Str::limit($description, 490),
            'image_url' => $image === null ? null : $this->absolute($image, $url),
        ];
    }

    private function absolute(string $candidate, string $base): ?string
    {
        if (Str::startsWith($candidate, ['http://', 'https://'])) {
            return Str::limit($candidate, 2040, '');
        }

        $parts = parse_url($base);

        if (! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $origin = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');

        if (Str::startsWith($candidate, '//')) {
            return $parts['scheme'].':'.$candidate;
        }

        if (Str::startsWith($candidate, '/')) {
            return $origin.$candidate;
        }

        $directory = rtrim(dirname($parts['path'] ?? '/'), '/');

        return $origin.$directory.'/'.$candidate;
    }
}
