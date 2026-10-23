<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Calendar,
    CreditCard,
    DollarSign,
    FileText,
    Home,
    MessageCircle,
    User,
    Wrench,
    X,
} from 'lucide-vue-next';
import StatusBadge from '@/Components/Common/StatusBadge.vue';

const props = defineProps<{
    show: boolean;
    unit: {
        id: number;
        code?: string;
        name: string;
        unit_type?: string;
        type_label?: string;
        status: string;
        status_label?: string;
        is_rentable?: boolean;
        active_lease?: {
            id: number;
            renter_id: number;
            renter_name: string;
            renter_phone: string;
            rent_amount: number;
            starts_on: string;
            ends_on: string;
            billing_interval?: string;
        } | null;
        has_maintenance?: boolean;
    } | null;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'recordPayment', leaseId: number, renterId: number, amount: number): void;
}>();
</script>

<template>
    <div v-if="show && unit" class="fixed inset-0 z-50 overflow-hidden" dir="rtl">
        <!-- Backdrop -->
        <div
            @click="emit('close')"
            class="absolute inset-0 bg-[#0F2747]/50 backdrop-blur-xs transition-opacity"
        ></div>

        <div class="fixed inset-y-0 left-0 max-w-full flex pl-0 sm:pl-10">
            <!-- Slide Drawer Panel (Width 400px) -->
            <div class="w-screen max-w-md bg-white shadow-2xl border-r border-[#D6E0E7] flex flex-col justify-between animate-in slide-in-from-left duration-200">
                <!-- Top: Header & Unit details -->
                <div class="overflow-y-auto p-6 space-y-6 flex-1">
                    <!-- Drawer Header -->
                    <div class="flex items-center justify-between pb-4 border-b border-[#D6E0E7]">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-[#E3F1F4] text-[#0E6E7E] flex items-center justify-center">
                                <Home class="w-5 h-5" />
                            </div>
                            <div>
                                <h3 class="text-base font-bold text-[#0F2747]">{{ unit.name }}</h3>
                                <p class="text-xs text-[#5B6770] font-mono">{{ unit.code || 'UNIT-' + unit.id }}</p>
                            </div>
                        </div>

                        <button
                            @click="emit('close')"
                            type="button"
                            class="p-2 text-[#5B6770] hover:text-[#0F2747] hover:bg-slate-100 rounded-xl transition cursor-pointer"
                        >
                            <X class="w-5 h-5" />
                        </button>
                    </div>

                    <!-- Status & Type -->
                    <div class="flex items-center justify-between p-4 rounded-2xl bg-[#F8FAFC] border border-[#D6E0E7]">
                        <span class="text-xs text-[#5B6770]">حالة الوحدة الحالية:</span>
                        <StatusBadge :status="unit.status" :label="unit.status_label" />
                    </div>

                    <!-- Active Lease Card (if occupied) -->
                    <div v-if="unit.active_lease" class="rounded-2xl border border-[#0E6E7E]/30 bg-[#E3F1F4]/20 p-5 space-y-4">
                        <div class="flex items-center justify-between border-b border-[#0E6E7E]/10 pb-3">
                            <span class="text-xs font-bold text-[#0E6E7E]">العقد الساري الحالي</span>
                            <Link
                                :href="route('leases.index')"
                                class="text-[11px] font-semibold text-[#0E6E7E] hover:underline"
                            >
                                كشف العقود ↗
                            </Link>
                        </div>

                        <!-- Tenant info -->
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-[#5B6770]">المستأجر:</span>
                                <Link
                                    :href="route('renters.show', unit.active_lease.renter_id)"
                                    class="text-sm font-bold text-[#0F2747] hover:text-[#0E6E7E] transition flex items-center gap-1.5"
                                >
                                    <User class="w-3.5 h-3.5 text-[#0E6E7E]" />
                                    <span>{{ unit.active_lease.renter_name }}</span>
                                </Link>
                            </div>

                            <div class="flex items-center justify-between">
                                <span class="text-xs text-[#5B6770]">الهاتف / WhatsApp:</span>
                                <a
                                    :href="`https://wa.me/20${unit.active_lease.renter_phone.replace(/^0+/, '')}`"
                                    target="_blank"
                                    class="text-xs font-semibold text-[#0E6E7E] flex items-center gap-1 font-mono hover:underline"
                                >
                                    <MessageCircle class="w-3.5 h-3.5 text-emerald-600" />
                                    <span>{{ unit.active_lease.renter_phone }}</span>
                                </a>
                            </div>

                            <div class="flex items-center justify-between">
                                <span class="text-xs text-[#5B6770]">القيمة الإيجارية:</span>
                                <span class="text-sm font-bold text-[#0F2747]">
                                    {{ unit.active_lease.rent_amount.toLocaleString() }} ج.م / شهرياً
                                </span>
                            </div>

                            <div class="flex items-center justify-between">
                                <span class="text-xs text-[#5B6770]">مدة العقد:</span>
                                <span class="text-xs font-medium text-[#0F2747] font-mono">
                                    {{ unit.active_lease.starts_on }} — {{ unit.active_lease.ends_on }}
                                </span>
                            </div>
                        </div>

                        <div class="pt-2">
                            <button
                                @click="emit('recordPayment', unit.active_lease.id, unit.active_lease.renter_id, unit.active_lease.rent_amount)"
                                type="button"
                                class="w-full h-10 rounded-xl bg-[#0E6E7E] hover:bg-[#0B5A68] text-white text-xs font-bold transition flex items-center justify-center gap-2 shadow-xs cursor-pointer"
                            >
                                <CreditCard class="w-4 h-4" />
                                <span>تسجيل دفعة لهذا العقد</span>
                            </button>
                        </div>
                    </div>

                    <!-- Vacant State in Drawer -->
                    <div v-else class="rounded-2xl border border-[#D6E0E7] bg-[#F8FAFC] p-6 text-center space-y-3">
                        <div class="w-12 h-12 rounded-xl bg-slate-200 text-slate-600 mx-auto flex items-center justify-center">
                            <Home class="w-6 h-6" />
                        </div>
                        <h4 class="text-sm font-bold text-[#0F2747]">الوحدة شاغرة حالياً</h4>
                        <p class="text-xs text-[#5B6770] leading-relaxed">
                            يمكنك إنشاء عقد تأجير جديد وربطه بمستأجر مباشرة مع فحص التعارض الزمني.
                        </p>
                        <Link
                            :href="route('leases.create', { unit_id: unit.id })"
                            class="inline-flex items-center justify-center gap-2 h-10 px-5 rounded-xl bg-[#0E6E7E] hover:bg-[#0B5A68] text-white text-xs font-bold transition shadow-xs w-full"
                        >
                            <FileText class="w-4 h-4" />
                            <span>تأجير الوحدة الآن</span>
                        </Link>
                    </div>

                    <!-- Maintenance quick shortcut -->
                    <div class="p-4 rounded-2xl border border-[#D6E0E7] bg-white space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-[#0F2747] flex items-center gap-1.5">
                                <Wrench class="w-4 h-4 text-[#5B6770]" />
                                <span>خدمات الصيانة للوحدة</span>
                            </span>
                            <span v-if="unit.has_maintenance" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800">
                                بلاغ معلق
                            </span>
                        </div>
                        <Link
                            :href="route('maintenance.index')"
                            class="text-xs text-[#0E6E7E] font-semibold hover:underline block"
                        >
                            + فتح بلاغ صيانة لهذه الوحدة
                        </Link>
                    </div>
                </div>

                <!-- Footer Drawer -->
                <div class="p-4 border-t border-[#D6E0E7] bg-[#F8FAFC] flex justify-end">
                    <button
                        @click="emit('close')"
                        type="button"
                        class="h-10 px-5 rounded-xl border border-[#D6E0E7] text-xs font-semibold text-[#5B6770] hover:bg-slate-200/50 transition cursor-pointer"
                    >
                        إغلاق
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

