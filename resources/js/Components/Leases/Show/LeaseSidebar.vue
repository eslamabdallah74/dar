<script setup lang="ts">
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import type { LeaseData, RenterData, UnitData } from './types';
import {
    ArrowLeft,
    ChevronDown,
    ChevronUp,
    FileText,
    MessageCircle,
    MoreVertical,
    Phone,
    Trash2,
    Upload,
} from 'lucide-vue-next';

defineProps<{
    lease: LeaseData;
    renter: RenterData;
    unit: UnitData;
    currencySymbol: string;
}>();

const emit = defineEmits<{
    (e: 'openUploadContract'): void;
    (e: 'deleteContractImage'): void;
}>();

const showRenterExtra = ref(false);
const showContractExtra = ref(false);
const showDocMenu = ref(false);
</script>

<template>
    <aside class="space-y-6">
        <!-- 1. كارت المستأجر -->
        <section aria-labelledby="sidebar-renter-heading" class="rounded-3xl border border-[#D6E0E7] bg-white p-5 shadow-xs space-y-4">
            <header class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p id="sidebar-renter-heading" class="text-xs font-semibold text-[#5B6770]">المستأجر</p>
                    <Link
                        :href="route('renters.show', renter.id)"
                        class="mt-0.5 block truncate text-base font-extrabold text-[#163E3E] underline-offset-4 hover:underline"
                    >
                        {{ renter.name }}
                    </Link>
                </div>

                <div v-if="renter.phone" class="flex shrink-0 items-center gap-1.5">
                    <a
                        :href="`https://wa.me/20${renter.phone.replace(/^0+/, '')}`"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[#D6E0E7] text-[#163E3E] transition hover:bg-[#F8F4EA]"
                        aria-label="محادثة واتساب"
                        title="محادثة واتساب"
                    >
                        <MessageCircle class="h-4 w-4" />
                    </a>
                    <a
                        :href="`tel:${renter.phone}`"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[#D6E0E7] text-[#163E3E] transition hover:bg-[#F8F4EA]"
                        aria-label="اتصال هاتفي"
                        title="اتصال هاتفي"
                    >
                        <Phone class="h-4 w-4" />
                    </a>
                </div>
            </header>

            <div class="flex items-center justify-between text-xs py-1 border-t border-[#EEF2F4]">
                <span class="text-[#5B6770]">رقم الجوال:</span>
                <span class="font-mono font-semibold text-[#163E3E]" dir="ltr">{{ renter.phone || '—' }}</span>
            </div>

            <!-- Collapsible Extra Details -->
            <div class="border-t border-[#EEF2F4] pt-2">
                <button
                    type="button"
                    class="flex w-full cursor-pointer items-center justify-between py-1 text-xs font-semibold text-[#5B6770] hover:text-[#163E3E]"
                    @click="showRenterExtra = !showRenterExtra"
                >
                    <span>بيانات إضافية</span>
                    <ChevronUp v-if="showRenterExtra" class="h-3.5 w-3.5" />
                    <ChevronDown v-else class="h-3.5 w-3.5" />
                </button>

                <div v-if="showRenterExtra" class="mt-2 space-y-2 text-xs divide-y divide-[#EEF2F4] text-[#163E3E]">
                    <div class="flex items-center justify-between pt-1.5">
                        <span class="text-[#5B6770]">رقم الهوية:</span>
                        <span class="font-mono tabular-nums">{{ renter.national_id || 'غير مسجل' }}</span>
                    </div>
                    <div v-if="renter.email" class="flex items-center justify-between pt-1.5">
                        <span class="text-[#5B6770]">البريد:</span>
                        <span class="truncate" dir="ltr">{{ renter.email }}</span>
                    </div>
                    <div class="flex items-center justify-between pt-1.5">
                        <span class="text-[#5B6770]">الجنس:</span>
                        <span>{{ renter.gender_label || 'غير محدد' }}</span>
                    </div>
                </div>
            </div>

            <div class="border-t border-[#EEF2F4] pt-3">
                <Link
                    :href="route('renters.show', renter.id)"
                    class="inline-flex w-full items-center justify-between rounded-xl bg-[#FBFAF6] px-3.5 py-2.5 text-xs font-bold text-[#163E3E] transition hover:bg-[#F8F4EA]"
                >
                    <span>ملف المستأجر وسجل مدفوعاته</span>
                    <ArrowLeft class="h-3.5 w-3.5" />
                </Link>
            </div>
        </section>

        <!-- 2. كارت الوحدة والعقار (سطرين) -->
        <section aria-labelledby="sidebar-unit-heading" class="rounded-3xl border border-[#D6E0E7] bg-white p-5 shadow-xs space-y-3">
            <header class="flex items-center justify-between">
                <p id="sidebar-unit-heading" class="text-xs font-semibold text-[#5B6770]">الوحدة المؤجرة</p>
                <div class="flex items-center gap-1.5">
                    <span class="rounded-md bg-[#E8F0F0] px-2 py-0.5 text-[11px] font-bold text-[#163E3E]">
                        {{ unit.unit_type_label }}
                    </span>
                    <span
                        v-if="unit.apartment_gender"
                        class="rounded-md border px-1.5 py-0.5 text-[11px] font-semibold"
                        :class="unit.apartment_gender.badge_class"
                    >
                        {{ unit.apartment_gender.short_label }}
                    </span>
                </div>
            </header>

            <!-- Line 1: Unit name & Code -->
            <div class="flex items-baseline justify-between text-sm">
                <span class="font-extrabold text-[#163E3E]">{{ unit.name }}</span>
                <span v-if="unit.code" class="font-mono text-xs text-[#5B6770]">كود: {{ unit.code }}</span>
            </div>

            <!-- Line 2: Property & Address -->
            <div class="border-t border-[#EEF2F4] pt-2 text-xs text-[#5B6770] space-y-1">
                <div class="flex items-center justify-between">
                    <span>العقار:</span>
                    <Link
                        :href="route('properties.show', unit.property_id)"
                        class="font-bold text-[#163E3E] underline-offset-4 hover:underline truncate max-w-[180px]"
                    >
                        {{ unit.property_name }}
                    </Link>
                </div>
                <p v-if="unit.property_address" class="text-[11px] text-[#8A9AA7] truncate">
                    {{ unit.property_address }}
                </p>
            </div>
        </section>

        <!-- 3. كارت وثيقة العقد والمستندات -->
        <section aria-labelledby="sidebar-doc-heading" class="rounded-3xl border border-[#D6E0E7] bg-white p-5 shadow-xs space-y-4">
            <header class="flex items-center justify-between">
                <p id="sidebar-doc-heading" class="text-xs font-semibold text-[#5B6770]">وثيقة العقد الموقعة</p>
                <!-- Menu for doc actions -->
                <div v-if="lease.has_contract_image" class="relative">
                    <button
                        type="button"
                        class="p-1 rounded-lg text-[#5B6770] hover:text-[#163E3E] hover:bg-[#F8F4EA]"
                        aria-label="خيارات وثيقة العقد"
                        @click="showDocMenu = !showDocMenu"
                    >
                        <MoreVertical class="h-4 w-4" />
                    </button>
                    <div
                        v-if="showDocMenu"
                        class="absolute end-0 z-20 mt-1 w-44 rounded-xl border border-[#D6E0E7] bg-white p-1.5 shadow-lg text-xs"
                    >
                        <button
                            type="button"
                            class="flex w-full items-center gap-2 rounded-lg px-2.5 py-1.5 font-semibold text-[#163E3E] hover:bg-[#F8F4EA]"
                            @click="showDocMenu = false; emit('openUploadContract');"
                        >
                            <Upload class="h-3.5 w-3.5" />
                            <span>استبدال الوثيقة</span>
                        </button>
                        <button
                            type="button"
                            class="flex w-full items-center gap-2 rounded-lg px-2.5 py-1.5 font-semibold text-rose-700 hover:bg-rose-50"
                            @click="showDocMenu = false; emit('deleteContractImage');"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                            <span>حذف النسخة</span>
                        </button>
                    </div>
                </div>
            </header>

            <div v-if="lease.has_contract_image && lease.contract_image_url" class="space-y-2">
                <a
                    :href="lease.contract_image_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="flex h-10 w-full items-center justify-center gap-2 rounded-xl bg-[#163E3E] text-xs font-bold text-white shadow-xs transition hover:bg-[#102F2F]"
                >
                    <FileText class="h-4 w-4" />
                    <span>عرض وتحميل نسخة العقد</span>
                </a>
            </div>

            <div v-else class="space-y-2.5">
                <p class="text-xs leading-relaxed text-[#5B6770]">
                    لم يتم إرفاق نسخة العقد الموقعة بعد. يُحفظ الملف بتشفير أمني فوري.
                </p>
                <button
                    type="button"
                    class="flex h-10 w-full items-center justify-center gap-2 rounded-xl border border-[#D6E0E7] bg-white text-xs font-bold text-[#163E3E] transition hover:bg-[#F8F4EA]"
                    @click="emit('openUploadContract')"
                >
                    <Upload class="h-4 w-4" />
                    <span>رفع نسخة العقد الموقعة</span>
                </button>
            </div>

            <!-- بيانات إضافية (أُنشئ في، ملاحظات) -->
            <div class="border-t border-[#EEF2F4] pt-2">
                <button
                    type="button"
                    class="flex w-full cursor-pointer items-center justify-between py-1 text-xs font-semibold text-[#5B6770] hover:text-[#163E3E]"
                    @click="showContractExtra = !showContractExtra"
                >
                    <span>معلومات إضافية عن العقد</span>
                    <ChevronUp v-if="showContractExtra" class="h-3.5 w-3.5" />
                    <ChevronDown v-else class="h-3.5 w-3.5" />
                </button>

                <div v-if="showContractExtra" class="mt-2 space-y-2 text-xs divide-y divide-[#EEF2F4] text-[#163E3E]">
                    <div class="flex items-center justify-between pt-1.5">
                        <span class="text-[#5B6770]">تاريخ إنشاء العقد:</span>
                        <span class="font-mono tabular-nums">{{ lease.created_at }}</span>
                    </div>
                    <div v-if="lease.notes" class="pt-1.5 space-y-1">
                        <span class="text-[#5B6770] block">ملاحظات العقد:</span>
                        <p class="rounded-xl bg-[#FBFAF6] p-2 text-[11px] leading-relaxed text-[#334155] whitespace-pre-line">
                            {{ lease.notes }}
                        </p>
                    </div>
                </div>
            </div>
        </section>
    </aside>
</template>

