import type { CapacitorConfig } from '@capacitor/cli';

/**
 * The site the app shows. MANTEION_APP_URL points a development build at
 * another server while syncing, e.g. `MANTEION_APP_URL=http://10.0.2.2:8000
 * npx cap sync android` for `php artisan serve` on the machine running the
 * Android emulator. Release builds always sync without it.
 */
const siteUrl = process.env.MANTEION_APP_URL || 'https://www.manteion.no';

const config: CapacitorConfig = {
    appId: 'no.manteion.app',
    appName: 'Manteion',
    // The app shows the live site (server.url); the only page built into it
    // is the one shown when the site can't be reached.
    webDir: 'resources/native-shell',
    server: {
        url: siteUrl,
        errorPath: 'index.html',
        cleartext: siteUrl.startsWith('http://'),
    },
    android: {
        // So the server can tell the app from a browser (DeviceLabel).
        appendUserAgent: 'Manteion-Android',
    },
};

export default config;
