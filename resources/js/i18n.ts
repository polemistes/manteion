import { createI18n } from 'vue-i18n';

type Messages = Record<string, any>;

/**
 * Every translation namespace lives in its own file,
 * `lang/{locale}/{namespace}.json`, so each area of the app owns its
 * strings. They are merged here into one message tree per locale, keyed
 * by namespace: `lang/en/events.json` becomes `t('events.…')`.
 */
const files = import.meta.glob<Messages>('./lang/*/*.json', {
    eager: true,
    import: 'default',
});

export const messages: Record<string, Messages> = {};

for (const [path, namespaceMessages] of Object.entries(files)) {
    const match = path.match(/\.\/lang\/([^/]+)\/([^/]+)\.json$/);

    if (!match) {
        continue;
    }

    const [, locale, namespace] = match;

    messages[locale] ??= {};
    messages[locale][namespace] = namespaceMessages;
}

export function createAppI18n(locale: string) {
    return createI18n({
        legacy: false,
        locale,
        fallbackLocale: 'en',
        messages,
    });
}
