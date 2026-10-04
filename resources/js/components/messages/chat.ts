import type { MessageSummary } from '@/types';

export type ChatMessage = MessageSummary & {
    /** Set while the server hasn't confirmed a message this tab sent. */
    pending?: 'sending' | 'failed';
    tempId?: string;
};

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
