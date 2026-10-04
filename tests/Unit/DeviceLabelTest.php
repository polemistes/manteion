<?php

use App\Services\DeviceLabel;

test('a user agent is reduced to its browser and platform', function (?string $userAgent, ?string $browser, ?string $platform) {
    expect(DeviceLabel::parse($userAgent))->toBe(['browser' => $browser, 'platform' => $platform]);
})->with([
    'Chrome on Android' => [
        'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Mobile Safari/537.36',
        'Chrome', 'Android',
    ],
    'Chrome on Windows' => [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36',
        'Chrome', 'Windows',
    ],
    'Chrome on Mac' => [
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36',
        'Chrome', 'Mac',
    ],
    'Chrome on Linux' => [
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36',
        'Chrome', 'Linux',
    ],
    'headless Chrome' => [
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/129.0.0.0 Safari/537.36',
        'Chrome', 'Linux',
    ],
    'Chrome on ChromeOS' => [
        'Mozilla/5.0 (X11; CrOS x86_64 14541.0.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36',
        'Chrome', 'ChromeOS',
    ],
    'Chrome on iPhone' => [
        'Mozilla/5.0 (iPhone; CPU iPhone OS 17_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/129.0.6668.69 Mobile/15E148 Safari/604.1',
        'Chrome', 'iPhone',
    ],
    'Safari on iPhone' => [
        'Mozilla/5.0 (iPhone; CPU iPhone OS 17_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.6 Mobile/15E148 Safari/604.1',
        'Safari', 'iPhone',
    ],
    'a home screen web app on an iPhone' => [
        'Mozilla/5.0 (iPhone; CPU iPhone OS 17_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148',
        'Safari', 'iPhone',
    ],
    'a home screen web app on an iPad, which claims to be a Mac' => [
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko)',
        'Safari', 'Mac',
    ],
    'Safari on an iPad asking for the mobile site' => [
        'Mozilla/5.0 (iPad; CPU OS 17_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.6 Mobile/15E148 Safari/604.1',
        'Safari', 'iPad',
    ],
    'Safari on Mac' => [
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.6 Safari/605.1.15',
        'Safari', 'Mac',
    ],
    'Firefox on Windows' => [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:131.0) Gecko/20100101 Firefox/131.0',
        'Firefox', 'Windows',
    ],
    'Firefox on Android' => [
        'Mozilla/5.0 (Android 14; Mobile; rv:131.0) Gecko/131.0 Firefox/131.0',
        'Firefox', 'Android',
    ],
    'Firefox on Linux' => [
        'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:131.0) Gecko/20100101 Firefox/131.0',
        'Firefox', 'Linux',
    ],
    'Firefox on iPhone' => [
        'Mozilla/5.0 (iPhone; CPU iPhone OS 17_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) FxiOS/131.0 Mobile/15E148 Safari/605.1.15',
        'Firefox', 'iPhone',
    ],
    'Edge on Windows' => [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36 Edg/129.0.0.0',
        'Edge', 'Windows',
    ],
    'Edge on Android' => [
        'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Mobile Safari/537.36 EdgA/129.0.0.0',
        'Edge', 'Android',
    ],
    'Opera on Windows' => [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36 OPR/114.0.0.0',
        'Opera', 'Windows',
    ],
    'Samsung Internet on Android' => [
        'Mozilla/5.0 (Linux; Android 14; SAMSUNG SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/26.0 Chrome/122.0.0.0 Mobile Safari/537.36',
        'Samsung Internet', 'Android',
    ],
    'Vivaldi on Linux' => [
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36 Vivaldi/6.9',
        'Vivaldi', 'Linux',
    ],
    'a known browser on an unknown platform' => [
        'Mozilla/5.0 (X11; FreeBSD amd64; rv:131.0) Gecko/20100101 Firefox/131.0',
        'Firefox', null,
    ],
    'an unknown browser on a known platform' => [
        'SomeApp/1.0 (Windows NT 10.0; Win64; x64)',
        null, 'Windows',
    ],
    'a command-line client' => ['curl/8.9.1', null, null],
    'an empty user agent' => ['', null, null],
    'no user agent at all' => [null, null, null],
]);
