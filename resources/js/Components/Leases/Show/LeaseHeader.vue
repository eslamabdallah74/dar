<script setup lang="ts">
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import StatusBadge from '@/Components/Common/StatusBadge.vue';
import type {
    LeaseData,
    RentTotals,
    RenterData,
    SecurityDepositData,
    UnitData,
} from './types';
import {
    calculateTimeline,
    deriveLeaseChip,
    deriveSmartPrimaryButton,
} from './useLeaseUtils';
import {
    ArrowRight,
    Ban,
    Coins,
    FileCheck,
    FileText,
    MoreVertical,
    RotateCcw,
    Trash2,
    Upload,
} from 'lucide-vue-next';

const props = defineProps<{
    lease: LeaseData;
    unit: UnitData;
    renter: RenterData;
    rentTotals: RentTotals;
    securityDeposit: SecurityDepositData;
    currencySymbol: string;
}>();

const emit = defineEmits<{
    (e: 'smartAction', action: string): void;
    (e: 'openExtend'): void;
    (e: 'openTerminate'): void;
    (e: 'openUploadContract'): void;
    (e: 'deleteContractImage'): void;
    (e: 'openRentPayment'): void;
    (e: 'openFinalizeSettlement'): void;
    (e: 'openPendingRefund'): void;
}>();

const isLeaseActive = computed(() => props.lease.status === 'active');

const timeline = computed(() => {
    return calculateTimeline(
        props.lease.raw_starts_on,
        props.lease.raw_ends_on,
        isLeaseActive.value
    );
});

const leaseChip = computed(() => {
    return deriveLeaseChip(props.lease, timeline.value.remainingDays);
});

const smartButton = computed(() => {
    return deriveSmartPrimaryButton(
        props.lease,
        props.rentTotals,
        props.securityDeposit,
        timeline.value.remainingDays
    );
});

const handleSmartButtonClick = () => {
    switch (smartButton.value.action) {
        case 'record-rent-payment':
            emit('openRentPayment');
            break;
        case 'extend-lease':
            emit('openExtend');
            break;
        case 'finalize-settlement':
            emit('openFinalizeSettlement');
            break;
        case 'pending-refund':
            emit('openPendingRefund');
            break;
        default:
            emit('smartAction', smartButton.value.action);
            break;
    }
};

// Dropdown state
const isMenuOpen = ref(false);
const menuRef = ref<HTMLElement | null>(null);

const toggleMenu = () => {
    isMenuOpen.value = !isMenuOpen.value;
};

const closeMenu = () => {
    isMenuOpen.value = false;
};

const handleClickOutside = (e: MouseEvent) => {
    if (menuRef.value && !menuRef.value.contains(e.target as Node)) {
        closeMenu();
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
    <header class="rounded-3xl border border-[#D6E0E7] bg-white p-5 sm:p-7 shadow-xs space-y-5">
        <!-- Top row: Contract number, chip, smart button, and more menu -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="font-mono text-2xl sm:text-3xl font-extrabold tracking-tight text-[#163E3E]">
                    عقد {{ lease.contract_number }}
                </h1>
                <StatusBadge
                    :status="leaseChip.status"
                    :label="leaseChip.label"
                    size="md"
                />
            </div>

            <!-- Action buttons -->
            <div class="flex items-center gap-2.5 self-end sm:self-auto">
                <!-- Smart Primary Button -->
                <button
                    type="button"
                    class="inline-flex h-11 cursor-pointer items-center gap-2 rounded-2xl bg-[#163E3E] px-5 text-xs sm:text-sm font-bold text-white shadow-xs transition hover:bg-[#102F2F] active:translate-y-px focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#163E3E]/40 focus-visible:ring-offset-2"
                    @click="handleSmartButtonClick"
                >
                    <Coins v-if="smartButton.action === 'record-rent-payment'" class="h-4 w-4" />
                    <RotateCcw v-else-if="smartButton.action === 'extend-lease'" class="h-4 w-4" />
                    <FileCheck v-else-if="smartButton.action === 'finalize-settlement' || smartButton.action === 'pending-refund'" class="h-4 w-4" />
                    <ArrowRight v-else class="h-4 w-4" />
                    <span>{{ smartButton.label }}</span>
                </button>

                <!-- More Actions Dropdown (⋯) -->
                <div ref="menuRef" class="relative">
                    <button
                        type="button"
                        class="inline-flex h-11 w-11 cursor-pointer items-center justify-center rounded-2xl border border-[#D6E0E7] bg-white text-[#163E3E] transition hover:bg-[#F8F4EA] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#163E3E]/30"
                        aria-label="المزيد من الإجراءات"
                        :aria-expanded="isMenuOpen"
                        title="المزيد من الإجراءات"
                        @click.stop="toggleMenu"
                    >
                        <MoreVertical class="h-4 w-4 text-[#163E3E]" />
                    </button>

                    <!-- Dropdown Content -->
                    <div
                        v-if="isMenuOpen"
                        class="absolute end-0 z-30 mt-2 w-56 rounded-2xl border border-[#D6E0E7] bg-white p-2 shadow-xl animate-in fade-in zoom-in-95"
                    >
                        <div class="space-y-1">
                            <!-- تجديد العقد -->
                            <button
                                v-if="isLeaseActive && smartButton.action !== 'extend-lease'"
                                type="button"
                                class="flex w-full cursor-pointer items-center gap-2.5 rounded-xl px-3 py-2 text-start text-xs font-semibold text-[#163E3E] transition hover:bg-[#F8F4EA]"
                                @click="closeMenu(); emit('openExtend');"
                            >
                                <RotateCcw class="h-3.5 w-3.5 text-[#5B6770]" />
                                <span>تجديد وتمديد العقد</span>
                            </button>

                            <!-- تسجيل دفعة إيجار -->
                            <button
                                v-if="isLeaseActive && smartButton.action !== 'record-rent-payment'"
                                type="button"
                                class="flex w-full cursor-pointer items-center gap-2.5 rounded-xl px-3 py-2 text-start text-xs font-semibold text-[#163E3E] transition hover:bg-[#F8F4EA]"
                                @click="closeMenu(); emit('openRentPayment');"
                            >
                                <Coins class="h-3.5 w-3.5 text-[#5B6770]" />
                                <span>تسجيل دفعة إيجار</span>
                            </button>

                            <!-- المستندات -->
                            <a
                                v-if="lease.has_contract_image && lease.contract_image_url"
                                :href="lease.contract_image_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="flex w-full cursor-pointer items-center gap-2.5 rounded-xl px-3 py-2 text-start text-xs font-semibold text-[#163E3E] transition hover:bg-[#F8F4EA]"
                                @click="closeMenu()"
                                @keydown.enter="closeMenu()"
                            >
                                <FileText class="h-3.5 w-3.5 text-[#5B6770]" />
                                <span>عرض نسخة العقد</span>
                            </a>

                            <button
                                type="button"
                                class="flex w-full cursor-pointer items-center gap-2.5 rounded-xl px-3 py-2 text-start text-xs font-semibold text-[#163E3E] transition hover:bg-[#F8F4EA]"
                                @click="closeMenu(); emit('openUploadContract');"
                            >
                                <Upload class="h-3.5 w-3.5 text-[#5B6770]" />
                                <span>{{ lease.has_contract_image ? 'استبدال نسخة العقد' : 'رفع نسخة العقد الموقعة' }}</span>
                            </button>

                            <button
                                v-if="lease.has_contract_image"
                                type="button"
                                class="flex w-full cursor-pointer items-center gap-2.5 rounded-xl px-3 py-2 text-start text-xs font-semibold text-rose-700 transition hover:bg-rose-50"
                                @click="closeMenu(); emit('deleteContractImage');"
                            >
                                <Trash2 class="h-3.5 w-3.5" />
                                <span>حذف نسخة العقد المرفوعة</span>
                            </button>

                            <div class="my-1 border-t border-[#EEF2F4]"></div>

                            <!-- إنهاء العقد (Destructive) -->
                            <button
                                v-if="isLeaseActive"
                                type="button"
                                class="flex w-full cursor-pointer items-center gap-2.5 rounded-xl px-3 py-2 text-start text-xs font-bold text-rose-700 transition hover:bg-rose-50"
                                @click="closeMenu(); emit('openTerminate');"
                            >
                                <Ban class="h-3.5 w-3.5" />
                                <span>إنهاء العقد وإخلاء الوحدة</span>
                            </button>

                            <!-- كل العقود -->
                            <Link
                                :href="route('leases.index')"
                                class="flex w-full items-center gap-2.5 rounded-xl px-3 py-2 text-start text-xs font-semibold text-[#5B6770] transition hover:bg-[#F8F4EA] hover:text-[#163E3E]"
                                @click="closeMenu()"
                                @keydown.enter="closeMenu()"
                            >
                                <ArrowRight class="h-3.5 w-3.5" />
                                <span>العودة لقائمة كل العقود</span>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sub row: Renter & Unit Links -->
        <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs sm:text-sm text-[#5B6770]">
            <span>المستأجر:</span>
            <Link
                :href="route('renters.show', renter.id)"
                class="font-bold text-[#163E3E] underline-offset-4 hover:underline"
            >
                {{ renter.name }}
            </Link>
            <span class="text-[#D6E0E7]" aria-hidden="true">·</span>
            <span>الوحدة:</span>
            <Link
                :href="route('properties.show', unit.property_id)"
                class="font-semibold text-[#163E3E] underline-offset-4 hover:underline"
            >
                {{ unit.property_name }} / {{ unit.name }}
                <span v-if="unit.code" class="font-mono text-xs text-[#5B6770]">({{ unit.code }})</span>
            </Link>
        </div>

        <!-- Timeline: Starts on — Ends on with Progress bar inside header -->
        <div class="rounded-2xl bg-[#FBFAF6] p-4 border border-[#EEF2F4] space-y-2.5">
            <div class="flex items-center justify-between text-xs">
                <div class="flex items-center gap-1.5 font-mono tabular-nums text-[#163E3E]">
                    <span class="text-[#5B6770]">البداية:</span>
                    <span class="font-semibold">{{ lease.starts_on }}</span>
                </div>
                <div class="text-xs font-semibold text-[#163E3E]">
                    {{ timeline.caption }}
                </div>
                <div class="flex items-center gap-1.5 font-mono tabular-nums text-[#163E3E]">
                    <span class="text-[#5B6770]">النهاية:</span>
                    <span class="font-semibold">{{ lease.ends_on }}</span>
                </div>
            </div>

            <!-- Progress Track -->
            <progress
                class="h-2 w-full overflow-hidden rounded-full bg-[#E6ECF0] accent-[#163E3E]"
                :value="timeline.elapsedPercent"
                max="100"
                aria-label="المنقضي من مدة العقد"
            ></progress>
        </div>
    </header>
</template>

