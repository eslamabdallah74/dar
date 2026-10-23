<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/Common/StatusBadge.vue';
import EmptyState from '@/Components/Common/EmptyState.vue';
import {
    Calendar,
    Coins,
    FileCheck,
    FilePlus,
    FileText,
    Home,
    Plus,
    Search,
    User,
} from 'lucide-vue-next';

interface LeaseItem {
    id: number;
    contract_number: string;
    renter_id: number;
    renter_name: string;
    renter_phone: string;
    property_name: string;
    unit_name: string;
    unit_code: string;
    starts_on: string;
    ends_on: string;
    rent_amount: number;
    billing_interval: string;
    status: string;
    status_label: string;
    is_expiring_soon: boolean;
}

const props = defineProps<{
    leases: LeaseItem[];
    stats: {
        active_leases: number;
        expiring_soon: number;
        total_annual_value: number;
        total_leases: number;
    };
}>();

const searchQuery = ref('');
const statusFilter = ref('all');

const filteredLeases = computed(() => {
    return props.leases.filter(l => {
        const matchesSearch = l.contract_number.includes(searchQuery.value)
            || l.renter_name.includes(searchQuery.value)
            || l.unit_name.includes(searchQuery.value);

        const matchesStatus = statusFilter.value === 'all'
            || (statusFilter.value === 'active' && l.status === 'active' && !l.is_expiring_soon)
            || (statusFilter.value === 'expiring' && l.is_expiring_soon);

        return matchesSearch && matchesStatus;
    });
});
</script>

<template>
    <AppLayout
        title="العقود — دار"
        active-tab="leases"
        header-title="عقود الإيجار"
        header-subtitle="إدارة وتوثيق عقود الإيجار، تجديد العقود ومتابعة مواعيد الانتهاء"
        breadcrumb="العقود"
    >
        <template #actions>
            <Link
                :href="route('leases.create')"
                class="h-11 px-5 rounded-xl bg-[#0E6E7E] hover:bg-[#0B5A68] text-white text-sm font-semibold transition shadow-xs flex items-center gap-2 cursor-pointer"
            >
                <Plus class="h-4 w-4 stroke-[2.5]" />
                <span>إنشاء عقد جديد</span>
            </Link>
        </template>

        <!-- كروت مؤشرات العقود (4 KPIs) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-2xs space-y-2">
                <span class="text-xs font-semibold text-[#5B6770]">العقود السارية</span>
                <div class="flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-[#0F2747] font-mono">{{ stats.active_leases }}</span>
                    <span class="text-xs text-[#0E6E7E] font-medium">عقد نشط</span>
                </div>
            </div>

            <div class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-2xs space-y-2">
                <span class="text-xs font-semibold text-[#5B6770]">تنتهي خلال 30 يوم</span>
                <div class="flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-amber-700 font-mono">{{ stats.expiring_soon }}</span>
                    <span class="text-xs text-amber-800 bg-amber-50 px-2 py-0.5 rounded-md font-semibold">
                        تتطلب تجديد
                    </span>
                </div>
            </div>

            <div class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-2xs space-y-2">
                <span class="text-xs font-semibold text-[#5B6770]">إجمالي القيمة السنوية</span>
                <div class="flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-[#0F2747] font-mono">{{ stats.total_annual_value.toLocaleString() }} <span class="text-xs font-sans font-normal text-[#5B6770]">ر.س</span></span>
                    <span class="text-xs text-[#5B6770]">سنوياً</span>
                </div>
            </div>

            <div class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-2xs space-y-2">
                <span class="text-xs font-semibold text-[#5B6770]">إجمالي العقود المسجلة</span>
                <div class="flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-[#0F2747] font-mono">{{ stats.total_leases }}</span>
                    <span class="text-xs text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-md font-semibold">
                        موثقة إلكترونياً
                    </span>
                </div>
            </div>
        </div>

        <!-- شريط البحث والتصفية -->
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 bg-white p-4 rounded-2xl border border-[#D6E0E7] shadow-2xs">
            <div class="relative flex-1 max-w-md">
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="ابحث برقم العقد، اسم المستأجر أو الوحدة..."
                    class="w-full h-10 pr-10 pl-4 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#0F2747] placeholder:text-[#5B6770] focus:border-[#0E6E7E] focus:bg-white focus:ring-1 focus:ring-[#0E6E7E] transition text-right"
                />
                <Search class="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-[#5B6770]" />
            </div>

            <div class="flex items-center gap-1.5 overflow-x-auto pb-1 sm:pb-0">
                <button
                    v-for="f in [
                        { id: 'all', label: 'الكل' },
                        { id: 'active', label: 'سارية' },
                        { id: 'expiring', label: 'تنتهي قريباً' },
                    ]"
                    :key="f.id"
                    @click="statusFilter = f.id"
                    type="button"
                    class="px-3.5 py-1.5 rounded-xl text-xs font-semibold transition cursor-pointer shrink-0"
                    :class="statusFilter === f.id
                        ? 'bg-[#0E6E7E] text-white shadow-2xs'
                        : 'bg-[#F8FAFC] text-[#5B6770] hover:text-[#0F2747] border border-[#D6E0E7]'"
                >
                    {{ f.label }}
                </button>
            </div>
        </div>

        <!-- جدول العقود -->
        <div v-if="filteredLeases.length > 0" class="rounded-3xl border border-[#D6E0E7] bg-white overflow-hidden shadow-2xs">
            <div class="overflow-x-auto">
                <table class="w-full text-right text-sm">
                    <thead>
                        <tr class="bg-[#F8FAFC] border-b border-[#D6E0E7] text-[#5B6770] text-xs font-bold">
                            <th class="py-3.5 px-6">رقم العقد</th>
                            <th class="py-3.5 px-6">المستأجر</th>
                            <th class="py-3.5 px-6">العقار والوحدة</th>
                            <th class="py-3.5 px-6">مدة العقد</th>
                            <th class="py-3.5 px-6">القيمة الشهرية</th>
                            <th class="py-3.5 px-6">الحالة</th>
                            <th class="py-3.5 px-6 text-left">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#D6E0E7]">
                        <tr
                            v-for="lease in filteredLeases"
                            :key="lease.id"
                            class="hover:bg-[#F3F7FA] transition group"
                        >
                            <!-- رقم العقد -->
                            <td class="py-4 px-6 font-mono font-bold text-[#0E6E7E]">
                                {{ lease.contract_number }}
                            </td>

                            <!-- المستأجر -->
                            <td class="py-4 px-6">
                                <Link
                                    :href="route('renters.show', lease.renter_id)"
                                    class="font-bold text-[#0F2747] hover:text-[#0E6E7E] transition block"
                                >
                                    {{ lease.renter_name }}
                                </Link>
                                <span class="text-xs text-[#5B6770] font-mono" dir="ltr">{{ lease.renter_phone }}</span>
                            </td>

                            <!-- العقار والوحدة -->
                            <td class="py-4 px-6 text-xs text-[#0F2747]">
                                <span class="font-bold block">{{ lease.unit_name }}</span>
                                <span class="text-[#5B6770] block">{{ lease.property_name }}</span>
                            </td>

                            <!-- المدة -->
                            <td class="py-4 px-6 font-mono text-xs text-[#5B6770]">
                                <div class="flex items-center gap-1.5">
                                    <Calendar class="w-3.5 h-3.5 text-[#5B6770]" />
                                    <span>{{ lease.starts_on }} — {{ lease.ends_on }}</span>
                                </div>
                            </td>

                            <!-- القيمة -->
                            <td class="py-4 px-6 font-mono text-xs font-bold text-[#0F2747]">
                                {{ lease.rent_amount.toLocaleString() }} ر.س
                                <span class="text-[10px] font-sans font-normal text-[#5B6770] block">دفع شهري</span>
                            </td>

                            <!-- الحالة -->
                            <td class="py-4 px-6">
                                <StatusBadge
                                    :status="lease.is_expiring_soon ? 'expiring' : lease.status"
                                    :label="lease.status_label"
                                    size="sm"
                                />
                            </td>

                            <!-- إجراءات -->
                            <td class="py-4 px-6 text-left">
                                <Link
                                    :href="route('renters.show', lease.renter_id)"
                                    class="h-8 px-3 rounded-lg border border-[#D6E0E7] hover:bg-slate-100 text-[#5B6770] hover:text-[#0F2747] text-xs font-semibold transition inline-flex items-center gap-1"
                                >
                                    <span>عرض التفاصيل</span>
                                    <span>←</span>
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <EmptyState
            v-else
            type="document"
            title="لم يتم العثور على أي عقود"
            description="لم نجد أي عقد يطابق معايير البحث."
            action-label="إنشاء عقد إيجار جديد الآن"
            @action="$inertia.visit(route('leases.create'))"
        />
    </AppLayout>
</template>

