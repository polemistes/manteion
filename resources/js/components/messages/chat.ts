import type { PreparedImage } from '@/lib/image';
import type { MessageSummary } from '@/types';

export type ChatMessage = MessageSummary & {
    /** Set while the server hasn't confirmed a message this tab sent. */
    pending?: 'sending' | 'failed';
    tempId?: string;
    /** The picture a pending message is sending, kept for a retry. */
    imageFile?: File;
};

/** What the composer hands over to send: words, a picture, or both. */
export type MessageDraft = {
    body: string;
    image: PreparedImage | null;
};

/** The longest edge a picture is sent at; the server keeps the same. */
export const IMAGE_MAX_EDGE = 1600;

/** The box a picture is framed in inside a bubble, in CSS pixels. */
export const IMAGE_FRAME = 320;

export type BubblePosition = 'single' | 'first' | 'middle' | 'last';

/** The quick reactions offered beside a bubble and in the touch sheet. */
export const QUICK_REACTIONS = ['👍', '❤️', '😂', '😮', '😢'];

/** Tightened corners on the sender's side inside a group (full class names for Tailwind). */
export const BUBBLE_CORNERS: Record<
    'mine' | 'theirs',
    Record<BubblePosition, string>
> = {
    mine: {
        single: '',
        first: 'rounded-br-xs',
        middle: 'rounded-tr-xs rounded-br-xs',
        last: 'rounded-tr-xs',
    },
    theirs: {
        single: '',
        first: 'rounded-bl-xs',
        middle: 'rounded-tl-xs rounded-bl-xs',
        last: 'rounded-tl-xs',
    },
};
