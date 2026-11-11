<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import { useLocale } from '@/composables/useLocale';
import { Globe, Check } from 'lucide-vue-next';

withDefaults(defineProps<{
    variant?: 'navbar' | 'guest' | 'sidebar';
}>(), {
    variant: 'navbar',
});

const { currentLocale, switchLocale } = useLocale();
const open = ref(false);

function toggle(event: MouseEvent) {
    event.stopPropagation();
    open.value = !open.value;
}

function selectLanguage(locale: 'ar' | 'en') {
    if (locale !== currentLocale.value) {
        switchLocale(locale);
    }
    open.value = false;
}

const close = () => {
    open.value = false;
};

onMounted(() => {
    window.addEventListener('click', close);
});

onUnmounted(() => {
    window.removeEventListener('click', close);
});
</script>

<template>
    <div class="relative inline-block text-start">
        <!-- Variant: Navbar -->
        <button
            v-if="variant === 'navbar'"
            type="button"
            @click="toggle"
            class="flex h-10 shrink-0 items-center gap-1.5 rounded-xl border border-[#D6E0E7] bg-white px-2.5 text-xs font-semibold text-[#163E3E] shadow-2xs transition hover:border-[#163E3E] sm:h-11 sm:px-3 cursor-pointer"
            :aria-expanded="open"
            aria-label="تبديل لغة الموقع / Change website language"
        >
            <Globe class="w-4 h-4 text-[#5B6770] shrink-0" />
            <span class="font-bold uppercase tracking-wider text-[11px]">
                {{ currentLocale === 'ar' ? 'العربية' : 'English' }}
            </span>
        </button>

        <!-- Variant: Guest / Auth Pages -->
        <button
            v-else-if="variant === 'guest'"
            type="button"
            @click="toggle"
            class="inline-flex items-center gap-2 rounded-xl border border-white/20 bg-white/10 px-3 py-2 text-xs font-semibold text-[#F8F4EA] shadow-md backdrop-blur-md transition duration-200 hover:border-[#F7C33D]/70 hover:bg-[#F7C33D] hover:text-[#163E3E] cursor-pointer"
            :aria-expanded="open"
            aria-label="تبديل لغة الموقع / Change website language"
        >
            <Globe class="w-4 h-4 shrink-0" />
            <span class="font-bold text-xs">
                {{ currentLocale === 'ar' ? 'العربية' : 'English' }}
            </span>
        </button>

        <!-- Variant: Sidebar -->
        <button
            v-else
            type="button"
            @click="toggle"
            class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl text-xs font-medium text-[#F8F4EA]/85 hover:bg-white/10 hover:text-white transition cursor-pointer"
            :aria-expanded="open"
            aria-label="تبديل لغة الموقع / Change website language"
        >
            <div class="flex items-center gap-3">
                <Globe class="w-4 h-4 text-[#F8F4EA]/70" />
                <span>{{ currentLocale === 'ar' ? 'العربية' : 'English' }}</span>
            </div>
            <span class="text-[10px] text-[#F8F4EA]/60 uppercase tracking-widest font-mono">
                {{ currentLocale }}
            </span>
        </button>

        <!-- Dropdown Menu -->
        <div
            v-if="open"
            class="absolute end-0 top-full mt-2 w-44 rounded-2xl border border-[#D6E0E7] bg-white p-1.5 shadow-xl z-50 animate-in fade-in zoom-in-95 text-start"
            :class="{ 'border-white/10 bg-[#163E3E] text-white': variant === 'sidebar' }"
        >
            <button
                type="button"
                @click="selectLanguage('ar')"
                class="w-full px-3 py-2 rounded-xl flex items-center justify-between text-xs font-semibold transition cursor-pointer"
                :class="currentLocale === 'ar'
                    ? (variant === 'sidebar' ? 'bg-[#F7C33D] text-[#163E3E]' : 'bg-[#163E3E] text-white')
                    : (variant === 'sidebar' ? 'hover:bg-white/10 text-white' : 'hover:bg-[#F8F4EA] text-[#163E3E]')"
            >
                <div class="flex items-center gap-2">
                    <span class="text-sm">🇪🇬</span>
                    <span>العربية (مصر)</span>
                </div>
                <Check v-if="currentLocale === 'ar'" class="w-4 h-4 shrink-0" :class="variant === 'sidebar' ? 'text-[#163E3E]' : 'text-[#F7C33D]'" />
            </button>

            <button
                type="button"
                @click="selectLanguage('en')"
                class="w-full px-3 py-2 rounded-xl flex items-center justify-between text-xs font-semibold transition cursor-pointer mt-1"
                :class="currentLocale === 'en'
                    ? (variant === 'sidebar' ? 'bg-[#F7C33D] text-[#163E3E]' : 'bg-[#163E3E] text-white')
                    : (variant === 'sidebar' ? 'hover:bg-white/10 text-white' : 'hover:bg-[#F8F4EA] text-[#163E3E]')"
            >
                <div class="flex items-center gap-2">
                    <span class="text-sm">🇬🇧</span>
                    <span>English (US)</span>
                </div>
                <Check v-if="currentLocale === 'en'" class="w-4 h-4 shrink-0" :class="variant === 'sidebar' ? 'text-[#163E3E]' : 'text-[#F7C33D]'" />
            </button>
        </div>
    </div>
</template>
