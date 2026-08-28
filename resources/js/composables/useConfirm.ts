import { reactive } from 'vue';

interface ConfirmOptions {
    message: string;
    confirmLabel?: string;
    cancelLabel?: string;
}

interface ConfirmState extends ConfirmOptions {
    isOpen: boolean;
    resolve: ((value: boolean) => void) | null;
}

const state = reactive<ConfirmState>({
    isOpen: false,
    message: '',
    confirmLabel: undefined,
    cancelLabel: undefined,
    resolve: null,
});

export function useConfirmState() {
    return state;
}

export function confirmAction(options: ConfirmOptions): Promise<boolean> {
    return new Promise((resolve) => {
        state.message = options.message;
        state.confirmLabel = options.confirmLabel;
        state.cancelLabel = options.cancelLabel;
        state.isOpen = true;
        state.resolve = resolve;
    });
}

export function resolveConfirm(value: boolean): void {
    state.isOpen = false;
    state.resolve?.(value);
    state.resolve = null;
}
