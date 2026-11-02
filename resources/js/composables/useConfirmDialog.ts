import { markRaw, reactive, readonly, type Component } from 'vue';

export type ConfirmDialogTone = 'danger' | 'warning' | 'primary';

export interface ConfirmDialogDetail {
    label: string;
    value: string;
}

export interface ConfirmDialogOptions {
    title: string;
    message?: string;
    details?: ConfirmDialogDetail[];
    confirmLabel?: string;
    cancelLabel?: string;
    tone?: ConfirmDialogTone;
    icon?: Component;
}

interface ConfirmDialogState {
    isOpen: boolean;
    mode: 'confirm' | 'notice';
    title: string;
    message: string;
    details: ConfirmDialogDetail[];
    confirmLabel: string;
    cancelLabel: string;
    tone: ConfirmDialogTone;
    icon: Component | null;
    /** Increments on every open, so the dialog can react even when one dialog replaces another. */
    openedCount: number;
}

const state = reactive<ConfirmDialogState>({
    isOpen: false,
    mode: 'confirm',
    title: '',
    message: '',
    details: [],
    confirmLabel: '',
    cancelLabel: '',
    tone: 'primary',
    icon: null,
    openedCount: 0,
});

let resolvePending: ((confirmed: boolean) => void) | null = null;

/**
 * Opens the single app-wide dialog. A dialog that is still open is answered "cancel" first,
 * so a caller awaiting it never hangs.
 */
function open(
    mode: ConfirmDialogState['mode'],
    options: ConfirmDialogOptions,
): Promise<boolean> {
    settle(false);

    state.mode = mode;
    state.title = options.title;
    state.message = options.message ?? '';
    state.details = options.details ?? [];
    state.tone = options.tone ?? (mode === 'notice' ? 'warning' : 'primary');
    state.confirmLabel =
        options.confirmLabel ?? (mode === 'notice' ? 'حسناً' : 'تأكيد');
    state.cancelLabel = options.cancelLabel ?? 'تراجع';
    state.icon = options.icon ? markRaw(options.icon) : null;
    state.isOpen = true;
    state.openedCount += 1;

    return new Promise<boolean>((resolve) => {
        resolvePending = resolve;
    });
}

function settle(confirmed: boolean): void {
    const resolve = resolvePending;
    resolvePending = null;
    state.isOpen = false;
    resolve?.(confirmed);
}

/**
 * The app's replacement for window.confirm() / window.alert(), rendered by <ConfirmDialog /> in the layouts.
 */
export function useConfirmDialog() {
    return {
        dialog: readonly(state),
        /** Resolves true when the user confirms, false when they cancel or dismiss. */
        confirm: (options: ConfirmDialogOptions): Promise<boolean> =>
            open('confirm', options),
        /** A message with a single acknowledge button; resolves once it is dismissed. */
        notice: async (options: ConfirmDialogOptions): Promise<void> => {
            await open('notice', options);
        },
        settle,
    };
}
