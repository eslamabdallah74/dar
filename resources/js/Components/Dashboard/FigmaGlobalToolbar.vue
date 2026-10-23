<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { Bell, Menu, Plus, Search } from 'lucide-vue-next';

const props = defineProps<{
    searchQuery?: string;
}>();

const emit = defineEmits<{
    (e: 'toggleMobile'): void;
    (e: 'newAction'): void;
}>();

const term = ref(props.searchQuery || '');

function handleSearch() {
    if (!term.value.trim()) return;
    router.visit(route('renters.index', { search: term.value.trim() }));
}
</script>

<template>
    <header class="h-20 bg-white border-b border-[#D6E0E7] px-4 sm:px-8 flex items-center justify-between sticky top-0 z-30 shadow-2xs">
        <!-- الجانب الأيمن (في RTL): زر قائمة الموبايل + حقل البحث العام -->
        <div class="flex items-center gap-3 sm:gap-4 flex-1 max-w-[850px]">
            <!-- زر فتح القائمة للموبايل -->
            <button
                @click="emit('toggleMobile')"
                type="button"
                class="lg:hidden p-2 rounded-xl text-[#0F2747] hover:bg-[#F3F7FA] transition border border-[#D6E0E7]"
            >
                <Menu class="h-5 w-5" />
            </button>

            <!-- حقل البحث العام المتطابق مع فيجما (Global search) -->
            <div class="relative w-full">
                <input
                    v-model="term"
                    @keydown.enter="handleSearch"
                    type="text"
                    placeholder="ابحث عن عقار، مستأجر أو عقد…"
                    class="w-full h-11 pr-11 pl-4 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#0F2747] placeholder:text-[#5B6770] focus:border-[#0E6E7E] focus:bg-white focus:ring-1 focus:ring-[#0E6E7E] transition shadow-2xs font-sans text-right"
                />
                <Search class="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-[#5B6770]" />
            </div>
        </div>

        <!-- الجانب الأيسر: محدد اللغة + التنبيهات + زر جديد -->
        <div class="flex items-center gap-3 sm:gap-5 shrink-0 mr-4">
            <!-- محدد اللغة (EN / عربي) -->
            <button
                type="button"
                class="hidden sm:inline-flex items-center text-xs font-semibold text-[#5B6770] hover:text-[#0F2747] transition px-2 py-1.5 rounded-lg hover:bg-[#F3F7FA]"
            >
                EN / عربي
            </button>

            <!-- زر التنبيهات -->
            <button
                type="button"
                class="p-2 rounded-xl text-[#5B6770] hover:text-[#0F2747] hover:bg-[#F3F7FA] transition relative"
                title="التنبيهات"
            >
                <Bell class="h-5 w-5" />
                <span class="absolute top-1.5 right-1.5 w-2 h-2 rounded-full bg-[#0E6E7E]"></span>
            </button>

            <!-- زر + جديد الرئيسي المتطابق مع فيجما (Button primary default - height 44px) -->
            <button
                @click="emit('newAction')"
                type="button"
                class="h-11 px-4 sm:px-5 rounded-xl bg-[#0E6E7E] hover:bg-[#0B5A68] text-white text-sm font-semibold transition shadow-xs flex items-center gap-2 shrink-0"
            >
                <Plus class="h-4 w-4 stroke-[2.5]" />
                <span>جديد</span>
            </button>
        </div>
    </header>
</template>
