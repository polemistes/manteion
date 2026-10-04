import { createInertiaApp, router } from '@inertiajs/vue3';
import { configureEcho } from '@laravel/echo-vue';
import { createAppI18n } from './i18n';
import AppLayout from './layouts/AppLayout.vue';
import { setupNativeShell } from './native';
import { setupPushNotifications } from './push';
import { applyTheme } from './theme';

configureEcho({
    broadcaster: 'reverb',
});

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

setupNativeShell();
setupPushNotifications();

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    progress: {
        color: '#4B5563',
    },
    layout: (name) =>
        name === 'Welcome' || name.startsWith('auth/') ? null : AppLayout,
    withApp(app, { page }) {
        applyTheme(page.props.theme);
        router.on('navigate', (event) =>
            applyTheme(event.detail.page.props.theme),
        );

        app.use(createAppI18n(page.props.locale as string));
    },
});
