<script setup lang="ts">
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { Check, ChevronDown, ShieldCheck, AlertCircle, AlertTriangle } from 'lucide-vue-next';

interface StatusDetails {
    value?: string;
    label?: string;
    description?: string;
    color?: string;
    badge_class?: string;
    dot_class?: string;
}

const props = withDefaults(
    defineProps<{
        status?: 'green' | 'yellow' | 'red' | string | null;
        details?: StatusDetails | null;
        size?: 'xs' | 'sm' | 'md';
        showLabel?: boolean;
        interactive?: boolean;
        renterId?: number | null;
    }>(),
    {
        status: 'green',
        details: null,
        size: 'sm',
        showLabel: false,
        interactive: false,
        renterId: null,
    }
);

const emit = defineEmits<{
    (e: 'change', status: 'green' | 'yellow' | 'red'): void;
}>();

const currentStatus = computed<'green' | 'yellow' | 'red'>(() => {
    const val = (props.status || props.details?.value || 'green').toLowerCase();
    if (val === 'yellow') return 'yellow';
    if (val === 'red') return 'red';
    return 'green';
});

interface StatusMeta {
    label: string;
    description: string;
    dotClass: string;
    badgeClass: string;
    textColor: string;
    icon: typeof ShieldCheck;
}

const statusOptions: Record<'green' | 'yellow' | 'red', StatusMeta> = {
    green: {
        label: 'ملتزم',
        description: 'مستأجر ملتزم بالسداد وموثوق في التعامل',
        dotClass: 'bg-emerald-500 ring-2 ring-emerald-200/70',
        badgeClass: 'bg-emerald-50 text-emerald-800 border-emerald-200 hover:bg-emerald-100/70',
        textColor: 'text-emerald-800',
        icon: ShieldCheck,
    },
    yellow: {
        label: 'يحتاج متابعة',
        description: 'مستأجر يحتاج متابعة دورية أو لديه تأخيرات',
        dotClass: 'bg-amber-500 ring-2 ring-amber-200/70',
        badgeClass: 'bg-amber-50 text-amber-800 border-amber-200 hover:bg-amber-100/70',
        textColor: 'text-amber-800',
        icon: AlertCircle,
    },
    red: {
        label: 'غير ملتزم',
        description: 'مستأجر غير ملتزم بالسداد أو لديه مشكلات سابقة',
        dotClass: 'bg-rose-500 ring-2 ring-rose-200/70',
        badgeClass: 'bg-rose-50 text-rose-800 border-rose-200 hover:bg-rose-100/70',
        textColor: 'text-rose-800',
        icon: AlertTriangle,
    },
};

const meta = computed<StatusMeta>(() => {
    return statusOptions[currentStatus.value];
});

const labelText = computed(() => {
    return props.details?.label || meta.value.label;
});

const descriptionText = computed(() => {
    return props.details?.description || meta.value.description;
});

const tooltipText = computed(() => {
    return `تقييم المستأجر: ${labelText.value} (${descriptionText.value})`;
});

// Dropdown state for interactive mode
const isOpen = ref(false);
const isUpdating = ref(false);
const dropdownRef = ref<HTMLElement | null>(null);

const toggleDropdown = (e: MouseEvent) => {
    if (!props.interactive || !props.renterId) return;
    e.stopPropagation();
    e.preventDefault();
    isOpen.value = !isOpen.value;
};

const selectStatus = (newStatus: 'green' | 'yellow' | 'red', e: MouseEvent) => {
    e.stopPropagation();
    e.preventDefault();
    isOpen.value = false;

    if (newStatus === currentStatus.value) return;

    emit('change', newStatus);

    if (props.renterId) {
        isUpdating.value = true;
        router.patch(
            route('renters.status.update', props.renterId),
            { status: newStatus },
            {
                preserveScroll: true,
                onFinish: () => {
                    isUpdating.value = false;
                },
            }
        );
    }
};

const handleClickOutside = (e: MouseEvent) => {
    if (dropdownRef.value && !dropdownRef.value.contains(e.target as Node)) {
        isOpen.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});
</script>

<template>
    <div
        ref="dropdownRef"
        class="relative inline-flex items-center"
        :class="{ 'cursor-pointer': interactive && renterId }"
    >
        <!-- وضع العرض بالشارة الكاملة (showLabel) -->
        <component
            :is="interactive && renterId ? 'button' : 'span'"
            v-if="showLabel"
            :type="interactive && renterId ? 'button' : undefined"
            @click="toggleDropdown"
            class="inline-flex items-center gap-1.5 rounded-full font-bold border transition select-none shadow-2xs"
            :class="[
                meta.badgeClass,
                size === 'xs' ? 'px-2 py-0.5 text-[10px]' : (size === 'md' ? 'px-3 py-1 text-xs' : 'px-2.5 py-0.5 text-[11px]'),
                interactive && renterId ? 'cursor-pointer hover:shadow-xs' : ''
            ]"
            :title="tooltipText"
            :aria-label="tooltipText"
        >
            <span
                class="rounded-full shrink-0 transition"
                :class="[
                    meta.dotClass,
                    size === 'xs' ? 'w-1.5 h-1.5' : (size === 'md' ? 'w-2.5 h-2.5' : 'w-2 h-2'),
                    isUpdating ? 'animate-ping' : ''
                ]"
            ></span>
            <span>{{ labelText }}</span>
            <ChevronDown v-if="interactive && renterId" class="w-3 h-3 opacity-60 -mr-0.5" />
        </component>

        <!-- وضع العرض كنقطة مؤشر مدمجة بجانب الاسم (!showLabel) -->
        <component
            :is="interactive && renterId ? 'button' : 'span'"
            v-else
            :type="interactive && renterId ? 'button' : undefined"
            @click="toggleDropdown"
            class="inline-flex items-center justify-center rounded-full transition select-none group relative"
            :class="[
                size === 'xs' ? 'p-0.5' : (size === 'md' ? 'p-1' : 'p-0.5'),
                interactive && renterId ? 'cursor-pointer hover:scale-110' : ''
            ]"
            :title="tooltipText"
            :aria-label="tooltipText"
        >
            <span
                class="rounded-full shrink-0 transition"
                :class="[
                    meta.dotClass,
                    size === 'xs' ? 'w-2 h-2' : (size === 'md' ? 'w-3 h-3' : 'w-2.5 h-2.5'),
                    isUpdating ? 'animate-ping' : ''
                ]"
            ></span>
        </component>

        <!-- القائمة المنسدلة لتغيير التقييم بنقرة واحدة (في الوضع التفاعلي) -->
        <div
            v-if="isOpen && interactive && renterId"
            class="absolute top-full mt-1.5 z-50 min-w-56 bg-white rounded-2xl shadow-xl border border-[#D6E0E7] p-1.5 text-right space-y-1 animate-in fade-in zoom-in-95 duration-100"
            :class="[
                $page?.props?.locale === 'en' ? 'left-0' : 'right-0'
            ]"
            role="menu"
            aria-orientation="vertical"
        >
            <div class="px-2.5 py-1.5 text-[11px] font-bold text-[#5B6770] border-b border-slate-100">
                تقييم المستأجر
            </div>

            <button
                v-for="(opt, key) in statusOptions"
                :key="key"
                type="button"
                @click="(e) => selectStatus(key, e)"
                class="w-full text-right p-2 rounded-xl text-xs font-semibold flex items-start gap-2.5 transition cursor-pointer hover:bg-slate-50"
                :class="key === currentStatus ? 'bg-slate-50 font-bold' : 'text-[#163E3E]'"
                role="menuitem"
            >
                <span
                    class="w-2.5 h-2.5 rounded-full shrink-0 mt-1"
                    :class="opt.dotClass"
                ></span>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between">
                        <span :class="opt.textColor">{{ opt.label }}</span>
                        <Check v-if="key === currentStatus" class="w-3.5 h-3.5 text-[#163E3E]" />
                    </div>
                    <p class="text-[10px] text-[#5B6770] font-normal leading-tight mt-0.5">
                        {{ opt.description }}
                    </p>
                </div>
            </button>
        </div>
    </div>
</template>

