export type TextSegment =
    | { kind: 'text'; value: string }
    | { kind: 'link'; value: string; href: string };

const URL_PATTERN = /\b(?:https?:\/\/|www\.)[^\s<>"']+[^\s<>"'.,;:!?)\]}]/gi;

/**
 * Splits text into plain runs and web links, so a template can render the
 * links as <a> elements without ever using v-html.
 */
export function linkify(text: string): TextSegment[] {
    const segments: TextSegment[] = [];
    let last = 0;

    for (const match of text.matchAll(URL_PATTERN)) {
        const start = match.index ?? 0;

        if (start > last) {
            segments.push({ kind: 'text', value: text.slice(last, start) });
        }

        const value = match[0];
        const href = value.toLowerCase().startsWith('www.')
            ? `https://${value}`
            : value;

        segments.push({ kind: 'link', value, href });
        last = start + value.length;
    }

    if (last < text.length) {
        segments.push({ kind: 'text', value: text.slice(last) });
    }

    return segments;
}

const PICTOGRAPHIC = /\p{Extended_Pictographic}/u;

/** True for a message that is just one to three emoji (drawn large, no bubble). */
export function isEmojiOnly(text: string | null): boolean {
    const trimmed = text?.trim() ?? '';

    if (trimmed === '' || trimmed.length > 40) {
        return false;
    }

    const graphemes = [
        ...new Intl.Segmenter(undefined, { granularity: 'grapheme' }).segment(
            trimmed.replace(/\s+/g, ''),
        ),
    ];

    return (
        graphemes.length >= 1 &&
        graphemes.length <= 3 &&
        graphemes.every(({ segment }) => PICTOGRAPHIC.test(segment))
    );
}
