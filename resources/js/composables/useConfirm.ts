import { reactive } from 'vue';

interface ConfirmOptions {
    /** The question, in the serif ("Delete this event?"). Optional. */
    title?: string;
    message: string;
    confirmLabel?: string;
    cancelLabel?: string;
    /** `danger` (the default) for destructive actions, `primary` otherwise. */
    tone?: 'danger' | 'primary';
}

interface ConfirmState extends ConfirmOptions {
    isOpen: boolean;
    resolve: ((value: boolean) => void) | null;
}

const state = reactive<ConfirmState>({
    isOpen: false,
    title: undefined,
    message: '',
    confirmLabel: undefined,
    cancelLabel: undefined,
    tone: 'danger',
    resolve: null,
});

export function useConfirmState() {
    return state;
}

export function confirmAction(options: ConfirmOptions): Promise<boolean> {
    return new Promise((resolve) => {
        state.title = options.title;
        state.message = options.message;
        state.confirmLabel = options.confirmLabel;
        state.cancelLabel = options.cancelLabel;
        state.tone = options.tone ?? 'danger';
        state.isOpen = true;
        state.resolve = resolve;
    });
}

export function resolveConfirm(value: boolean): void {
    state.isOpen = false;
    state.resolve?.(value);
    state.resolve = null;
}
