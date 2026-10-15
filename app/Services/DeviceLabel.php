<?php

namespace App\Services;

/**
 * A short name for the browser and device behind a user agent string
 * ("Chrome on Android", "Safari on iPhone"), so people can tell apart the
 * devices that get their push notifications. Deliberately coarse: it knows
 * the browsers and platforms people actually use and falls back to
 * whichever half it recognised, or to "Unknown device".
 */
class DeviceLabel
{
    /**
     * What parse() calls the Manteion app; for() puts it in the locale.
     */
    public const APP = 'Manteion app';

    /**
     * Browsers by the user agent token that gives them away, most specific
     * first: every Chromium-based browser also claims to be Chrome, and
     * nearly every browser claims to be Safari.
     *
     * @var array<string, string>
     */
    private const BROWSERS = [
        // The Android app's WebView adds this to Chrome's user agent.
        '/Manteion-Android/' => self::APP,
        '/Edg(e|A|iOS)?\//' => 'Edge',
        '/OPR\/|OPT\/|OPiOS\/|Opera/' => 'Opera',
        '/SamsungBrowser\//' => 'Samsung Internet',
        '/Vivaldi\//' => 'Vivaldi',
        '/Firefox\/|FxiOS\//' => 'Firefox',
        '/Chrome\/|CriOS\/|Chromium\//' => 'Chrome',
        '/Safari\//' => 'Safari',
    ];

    /**
     * Platforms, again most specific first: Android also claims to be
     * Linux, and iPhones and iPads to be "like Mac OS X". An iPad asking for
     * the desktop site (the default since iPadOS 13) is indistinguishable
     * from a Mac.
     *
     * @var array<string, string>
     */
    private const PLATFORMS = [
        '/iPhone|iPod/' => 'iPhone',
        '/iPad/' => 'iPad',
        '/Android/' => 'Android',
        '/Windows/' => 'Windows',
        '/CrOS/' => 'ChromeOS',
        '/Macintosh|Mac OS X/' => 'Mac',
        '/Linux/' => 'Linux',
    ];

    /**
     * The label in the current locale.
     */
    public static function for(?string $userAgent): string
    {
        ['browser' => $browser, 'platform' => $platform] = self::parse($userAgent);

        if ($browser === self::APP) {
            $appName = __('devices.app');
            $browser = is_string($appName) ? $appName : self::APP;
        }

        if ($browser !== null && $platform !== null) {
            return __('devices.browser_on_platform', ['browser' => $browser, 'platform' => $platform]);
        }

        return $browser ?? $platform ?? __('devices.unknown');
    }

    /**
     * The browser and platform names a user agent reveals, each null when
     * it isn't one this recognises.
     *
     * @return array{browser: string|null, platform: string|null}
     */
    public static function parse(?string $userAgent): array
    {
        $userAgent ??= '';

        $platform = self::firstMatch(self::PLATFORMS, $userAgent);
        $browser = self::firstMatch(self::BROWSERS, $userAgent);

        // A web app added to the home screen (the only way iOS gets web push)
        // or the Dock leaves Safari's own tokens out of its user agent.
        if ($browser === null && in_array($platform, ['iPhone', 'iPad', 'Mac'], true) && str_contains($userAgent, 'AppleWebKit/')) {
            $browser = 'Safari';
        }

        return ['browser' => $browser, 'platform' => $platform];
    }

    /**
     * @param  array<string, string>  $namesByPattern
     */
    private static function firstMatch(array $namesByPattern, string $userAgent): ?string
    {
        foreach ($namesByPattern as $pattern => $name) {
            if (preg_match($pattern, $userAgent) === 1) {
                return $name;
            }
        }

        return null;
    }
}
