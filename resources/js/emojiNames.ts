/**
 * Emoji names for tooltips, from the same Unicode list the picker uses.
 * Loaded once, on demand, since most pages never need it.
 */
let names: Promise<Map<string, string>> | null = null;

export function loadEmojiNames(): Promise<Map<string, string>> {
    names ??= import('@/emoji-data.json').then(
        (module) =>
            new Map(
                (module.default.emoji as [number, string, string][]).map(
                    ([, emoji, name]) => [emoji, name],
                ),
            ),
    );

    return names;
}

export function capitalize(name: string): string {
    return name.charAt(0).toUpperCase() + name.slice(1);
}
