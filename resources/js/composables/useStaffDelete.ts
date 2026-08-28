import { reactive } from 'vue';

export type StaffDeleteChoice = 'delete' | 'placeholder' | null;

interface StaffDeleteOptions {
    message: string;
}

interface StaffDeleteState extends StaffDeleteOptions {
    isOpen: boolean;
    resolve: ((value: StaffDeleteChoice) => void) | null;
}

const state = reactive<StaffDeleteState>({
    isOpen: false,
    message: '',
    resolve: null,
});

export function useStaffDeleteState() {
    return state;
}

/**
 * Like `confirmAction`, but for staff deleting someone else's content: lets
 * them choose between removing it entirely or leaving a "Deleted by a
 * moderator" placeholder in its place.
 */
export function staffDelete(
    options: StaffDeleteOptions,
): Promise<StaffDeleteChoice> {
    return new Promise((resolve) => {
        state.message = options.message;
        state.isOpen = true;
        state.resolve = resolve;
    });
}

export function resolveStaffDelete(value: StaffDeleteChoice): void {
    state.isOpen = false;
    state.resolve?.(value);
    state.resolve = null;
}
