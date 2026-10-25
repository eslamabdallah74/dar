import { reactive } from 'vue';

export interface ToastItem {
    id: string;
    type: 'error' | 'success' | 'warning' | 'info';
    title?: string;
    message: string;
    messages?: string[];
    duration?: number;
    remaining?: number;
}

interface ToastOptions {
    title?: string;
    messages?: string[];
    duration?: number;
}

const state = reactive<{
    toasts: ToastItem[];
}>({
    toasts: [],
});

let toastIdCounter = 0;

export function useToast() {
    function addToast(type: ToastItem['type'], message: string, options: ToastOptions = {}) {
        // Prevent duplicate spam of the exact same message if already showing
        const existing = state.toasts.find(t => t.type === type && t.message === message);
        if (existing) {
            existing.remaining = options.duration ?? 7000;
            return existing.id;
        }

        const id = `toast-${Date.now()}-${++toastIdCounter}`;
        const duration = options.duration ?? (type === 'error' ? 8000 : 5000);

        const toast: ToastItem = {
            id,
            type,
            title: options.title,
            message,
            messages: options.messages,
            duration,
            remaining: duration,
        };

        // Prepend so the newest toast appears at the top
        state.toasts.unshift(toast);

        return id;
    }

    function error(message: string, options?: ToastOptions) {
        return addToast('error', message, {
            title: options?.title ?? 'تنبيه: حدث خطأ',
            ...options,
        });
    }

    function success(message: string, options?: ToastOptions) {
        return addToast('success', message, {
            title: options?.title ?? 'تم بنجاح',
            ...options,
        });
    }

    function warning(message: string, options?: ToastOptions) {
        return addToast('warning', message, {
            title: options?.title ?? 'تنبيه',
            ...options,
        });
    }

    function info(message: string, options?: ToastOptions) {
        return addToast('info', message, {
            title: options?.title ?? 'معلومة',
            ...options,
        });
    }

    function remove(id: string) {
        const index = state.toasts.findIndex(t => t.id === id);
        if (index !== -1) {
            state.toasts.splice(index, 1);
        }
    }

    function clear() {
        state.toasts.splice(0, state.toasts.length);
    }

    /**
     * Parse errors object from backend (e.g. ValidationException / FormRequest)
     * and display in a unified, styled toast.
     */
    function fromErrors(errors: Record<string, string | string[] | any>, customTitle?: string) {
        if (!errors || Object.keys(errors).length === 0) {
            return;
        }

        const messages: string[] = [];
        for (const [key, value] of Object.entries(errors)) {
            if (Array.isArray(value)) {
                value.forEach(msg => {
                    if (typeof msg === 'string') {
                        messages.push(msg);
                    }
                });
            } else if (typeof value === 'string') {
                messages.push(value);
            }
        }

        if (messages.length === 0) {
            return;
        }

        if (messages.length === 1) {
            error(messages[0], {
                title: customTitle ?? 'تعذر إتمام العملية',
                duration: 9000,
            });
        } else {
            error('يرجى مراجعة وتصحيح البيانات المدخلة:', {
                title: customTitle ?? `تنبيه: توجد ${messages.length} ملاحظات مدخلة`,
                messages,
                duration: 10000,
            });
        }
    }

    return {
        toasts: state.toasts,
        error,
        success,
        warning,
        info,
        remove,
        clear,
        fromErrors,
    };
}

