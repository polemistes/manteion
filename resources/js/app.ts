import { createInertiaApp, router } from '@inertiajs/vue3';
import { createI18n } from 'vue-i18n';
import en from './lang/en.json';
import nb from './lang/nb.json';
import AppLayout from './layouts/AppLayout.vue';
import { setupNativeShell } from './native';
import { setupPushNotifications } from './push';
import { applyTheme } from './theme';

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

        const i18n = createI18n({
            legacy: false,
            locale: page.props.locale as string,
            fallbackLocale: 'en',
            messages: { en, nb },
        });

        app.use(i18n);
    },
});
