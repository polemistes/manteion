import type { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
    appId: 'no.manteion.app',
    appName: 'Manteion',
    // Capacitor requires a valid webDir even when running against a remote
    // server below — it's only used as the offline-fallback shell, never the
    // primary content source, since this app is a server-driven Inertia SPA.
    webDir: 'public/build',
    server: {
        url: 'https://manteion.no',
    },
};

export default config;
