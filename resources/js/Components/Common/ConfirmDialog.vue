<script setup lang="ts">
import {
    useConfirmDialog,
    type ConfirmDialogTone,
} from '@/composables/useConfirmDialog';
import { AlertTriangle, CircleHelp, Info } from 'lucide-vue-next';
import {
    computed,
    nextTick,
    onUnmounted,
    ref,
    watch,
    type Component,
} from 'vue';

const { dialog, settle } = useConfirmDialog();

const panel = ref<HTMLElement | null>(null);
const confirmButton = ref<HTMLButtonElement | null>(null);
const cancelButton = ref<HTMLButtonElement | null>(null);
let focusBeforeOpen: HTMLElement | null = null;

const toneStyles: Record<
    ConfirmDialogTone,
    { icon: Component; badge: string; button: string }
> = {
    danger: {
        icon: AlertTriangle,
        badge: 'bg-rose-50 text-rose-600 ring-rose-100',
        button: 'bg-rose-600 text-white hover:bg-rose-700 focus-visible:ring-rose-300',
    },
    warning: {
        icon: Info,
        badge: 'bg-amber-50 text-amber-600 ring-amber-100',
        button: 'bg-[#163E3E] text-white hover:bg-[#102F2F] focus-visible:ring-[#163E3E]/30',
    },
    primary: {
        icon: CircleHelp,
        badge: 'bg-[#E8F0F0] text-[#163E3E] ring-[#163E3E]/10',
        button: 'bg-[#163E3E] text-white hover:bg-[#102F2F] focus-visible:ring-[#163E3E]/30',
    },
};

const tone = computed(() => toneStyles[dialog.tone]);
const dialogIcon = computed(() => dialog.icon ?? tone.value.icon);

const focusableElements = (): HTMLElement[] =>
    Array.from(
        panel.value?.querySelectorAll<HTMLElement>(
            'button:not([disabled]), a[href], [tabindex]:not([tabindex="-1"])',
        ) ?? [],
    );

/**
 * Listens on the whole document while open, so Escape and the Tab trap keep working even after a click
 * on plain text moves focus to <body>. Tab always lands on a control inside the dialog, never behind it.
 */
const onKeydown = (event: KeyboardEvent) => {
    if (event.key === 'Escape') {
        event.preventDefault();
        settle(false);

        return;
    }
    if (event.key !== 'Tab') {
        return;
    }

    const elements = focusableElements();
    if (elements.length === 0) {
        return;
    }
    const first = elements[0];
    const last = elements[elements.length - 1];
    const focusIsInside =
        panel.value?.contains(document.activeElement) ?? false;
    if (!focusIsInside) {
        event.preventDefault();
        (event.shiftKey ? last : first).focus();
    } else if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
};

const lockPage = () => {
    focusBeforeOpen =
        document.activeElement instanceof HTMLElement
            ? document.activeElement
            : null;
    document.body.style.overflow = 'hidden';
    document.addEventListener('keydown', onKeydown);
};

const unlockPage = () => {
    document.removeEventListener('keydown', onKeydown);
    document.body.style.overflow = '';
    focusBeforeOpen?.focus();
    focusBeforeOpen = null;
};

watch(
    () => dialog.isOpen,
    (isOpen) => (isOpen ? lockPage() : unlockPage()),
);

/**
 * Runs for every opened dialog, including one that replaces a dialog that was still open.
 * A destructive action never starts focused, so a stray Enter cannot confirm it.
 */
watch(
    () => dialog.openedCount,
    async () => {
        await nextTick();
        (dialog.tone === 'danger' && cancelButton.value
            ? cancelButton.value
            : confirmButton.value
        )?.focus();
    },
);

onUnmounted(() => {
    if (dialog.isOpen) {
        unlockPage();
        settle(false);
    }
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity duration-200 ease-out"
            enter-from-class="opacity-0"
            leave-active-class="transition-opacity duration-150 ease-in"
            leave-to-class="opacity-0"
        >
            <div
                v-if="dialog.isOpen"
                class="fixed inset-0 z-[100] flex items-end justify-center p-3 sm:items-center sm:p-6"
                dir="rtl"
            >
                <div
                    class="absolute inset-0 bg-[#0F2A2A]/55 backdrop-blur-[3px]"
                    aria-hidden="true"
                    @click="settle(false)"
                ></div>

                <Transition
                    appear
                    enter-active-class="transition duration-200 ease-out motion-reduce:transition-none"
                    enter-from-class="translate-y-3 scale-[0.97] opacity-0"
                    enter-to-class="translate-y-0 scale-100 opacity-100"
                >
                    <div
                        ref="panel"
                        role="alertdialog"
                        aria-modal="true"
                        aria-labelledby="confirm-dialog-title"
                        :aria-describedby="
                            dialog.message
                                ? 'confirm-dialog-message'
                                : undefined
                        "
                        class="relative w-full max-w-[420px] rounded-3xl bg-white p-6 shadow-[0_24px_60px_-16px_rgba(15,42,42,0.45)] ring-1 ring-[#163E3E]/10 sm:p-7"
                    >
                        <div class="flex items-start gap-4">
                            <span
                                class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl ring-4"
                                :class="tone.badge"
                            >
                                <component
                                    :is="dialogIcon"
                                    class="h-[22px] w-[22px]"
                                    stroke-width="2.2"
                                />
                            </span>
                            <div class="min-w-0 pt-1">
                                <h2
                                    id="confirm-dialog-title"
                                    class="text-lg font-bold leading-7 text-[#163E3E]"
                                >
                                    {{ dialog.title }}
                                </h2>
                                <p
                                    v-if="dialog.message"
                                    id="confirm-dialog-message"
                                    class="mt-1 text-sm leading-6 text-[#5B6770]"
                                >
                                    {{ dialog.message }}
                                </p>
                            </div>
                        </div>

                        <dl
                            v-if="dialog.details.length"
                            class="mt-5 divide-y divide-[#EFE9DA] rounded-2xl bg-[#FBF8F1] px-4 py-1 text-sm"
                        >
                            <div
                                v-for="detail in dialog.details"
                                :key="detail.label"
                                class="flex items-center justify-between gap-4 py-2.5"
                            >
                                <dt class="shrink-0 text-[#5B6770]">
                                    {{ detail.label }}
                                </dt>
                                <dd
                                    class="truncate font-semibold text-[#163E3E]"
                                >
                                    {{ detail.value }}
                                </dd>
                            </div>
                        </dl>

                        <div class="mt-6 flex flex-col gap-2 sm:flex-row">
                            <button
                                ref="confirmButton"
                                type="button"
                                class="h-11 w-full cursor-pointer rounded-xl px-4 text-sm font-semibold shadow-sm transition focus-visible:outline-none focus-visible:ring-4 active:translate-y-px sm:flex-1"
                                :class="tone.button"
                                @click="settle(true)"
                            >
                                {{ dialog.confirmLabel }}
                            </button>
                            <button
                                v-if="dialog.mode === 'confirm'"
                                ref="cancelButton"
                                type="button"
                                class="h-11 w-full cursor-pointer rounded-xl border border-[#D6E0E7] bg-white px-4 text-sm font-semibold text-[#163E3E] transition hover:bg-[#F8F4EA] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#163E3E]/15 active:translate-y-px sm:flex-1"
                                @click="settle(false)"
                            >
                                {{ dialog.cancelLabel }}
                            </button>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
