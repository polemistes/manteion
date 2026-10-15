import { createInertiaApp, router } from '@inertiajs/vue3';
import { createAppI18n } from './i18n';
import AppLayout from './layouts/AppLayout.vue';
import { setupNativeShell, startNativePush } from './native/shell';
import { setupInstallPrompt } from './pwa/install';
import { registerServiceWorker } from './pwa/serviceWorker';
import { setupRealtime } from './realtime';
import { applyTheme } from './theme';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

setupNativeShell();

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    progress: {
        color: '#4B5563',
    },
    layout: (name) =>
        name === 'Welcome' || name.startsWith('auth/') ? null : AppLayout,
    withApp(app, { page, ssr }) {
        applyTheme(page.props.theme);
        router.on('navigate', (event) =>
            applyTheme(event.detail.page.props.theme),
        );

        const i18n = createAppI18n(page.props.locale as string);

        app.use(i18n);

        // Browser only, and before the app mounts, so the badge store holds
        // the page's counts when hydration compares against the server.
        if (!ssr) {
            setupRealtime(page);
            registerServiceWorker();
            setupInstallPrompt();
            startNativePush(page, (key, named) =>
                i18n.global.t(key, named ?? {}),
            );
        }
    },
});
