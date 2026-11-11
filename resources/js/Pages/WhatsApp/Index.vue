<script setup lang="ts">
import { ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/Common/StatusBadge.vue';
import ResourceTableHeading from '@/Components/Common/ResourceTableHeading.vue';
import { Clock, MessageSquare, Send, X, Zap } from 'lucide-vue-next';

interface TemplateItem {
    id: string;
    title: string;
    trigger: string;
    sample: string;
    active: boolean;
}

interface LogItem {
    id: number;
    renter_id: number;
    renter_name: string;
    recipient_phone: string;
    template_name: string;
    status: string;
    status_label: string;
    scheduled_at: string;
    sent_at: string;
}

const props = defineProps<{
    templates: TemplateItem[];
    logs: LogItem[];
    renters: Array<{ id: number; name: string; phone: string }>;
    stats: {
        total_sent: number;
        delivered_rate: number;
        active_templates: number;
    };
    connection: {
        is_connected: boolean;
        sender_number: string;
        provider: string;
        status_label: string;
    };
}>();

const showTestModal = ref(false);

const testForm = useForm({
    renter_id: props.renters[0]?.id ?? '',
    template_id: props.templates[0]?.id ?? '',
});

const sendTestMessage = () => {
    testForm.post(route('whatsapp.test-send'), {
        onSuccess: () => {
            showTestModal.value = false;
        },
    });
};
</script>

<template>
    <AppLayout
        :title="`${$t('whatsapp.title')} — Nook Mate`"
        active-tab="whatsapp"
        :header-title="$t('whatsapp.title')"
        :header-subtitle="$t('whatsapp.subtitle')"
        breadcrumb="WhatsApp"
    >
        <template #actions>
            <button
                @click="showTestModal = true"
                type="button"
                class="h-11 px-5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold transition shadow-xs flex items-center gap-2 cursor-pointer"
            >
                <Send class="h-4 w-4" />
                <span>{{ $t('whatsapp.test_send') }}</span>
            </button>
        </template>

        <!-- كروت مؤشرات WhatsApp (4 KPIs) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-2xs space-y-2">
                <span class="text-xs font-semibold text-[#5B6770]">{{ $t('whatsapp.stats_sent') }}</span>
                <div class="flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-[#163E3E] font-mono">{{ stats.total_sent }}</span>
                </div>
            </div>

            <div class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-2xs space-y-2">
                <span class="text-xs font-semibold text-[#5B6770]">{{ $t('whatsapp.stats_delivered_rate') }}</span>
                <div class="flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-emerald-700 font-mono">{{ stats.delivered_rate }}%</span>
                </div>
            </div>

            <div class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-2xs space-y-2">
                <span class="text-xs font-semibold text-[#5B6770]">{{ $t('whatsapp.stats_active_templates') }}</span>
                <div class="flex items-baseline justify-between">
                    <span class="text-2xl font-bold text-[#163E3E] font-mono">{{ stats.active_templates }}</span>
                </div>
            </div>

            <div class="rounded-2xl border border-[#D6E0E7] bg-white p-5 shadow-2xs space-y-2">
                <span class="text-xs font-semibold text-[#5B6770]">{{ stats.delivered_rate > 0 ? (stats.delivered_rate + '%') : '—' }}</span>
                <div class="flex items-baseline justify-between">
                    <span class="text-xs font-semibold text-emerald-700 font-mono">{{ connection.status_label }}</span>
                </div>
            </div>
        </div>

        <!-- قسم قوالب الرسائل التلقائية (Automation Templates) -->
        <div class="space-y-4">
            <div class="flex items-center justify-between pb-2 border-b border-[#D6E0E7]">
                <div class="flex items-center gap-2">
                    <Zap class="w-5 h-5 text-[#163E3E]" />
                    <h2 class="text-lg font-bold text-[#163E3E]">قوالب الرسائل والتذكيرات الآلية</h2>
                </div>
                <span class="text-xs text-[#5B6770]">يتم إرسالها تلقائياً للمستأجرين بناءً على المواعيد المحددة</span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div
                    v-for="tpl in templates"
                    :key="tpl.id"
                    class="rounded-3xl border border-[#D6E0E7] bg-white p-5 shadow-2xs space-y-3.5 flex flex-col justify-between hover:border-[#163E3E]/40 transition"
                >
                    <div class="space-y-3">
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="text-sm font-bold text-[#163E3E] flex items-center gap-2">
                                <MessageSquare class="w-4 h-4 text-[#163E3E]" />
                                <span>{{ tpl.title }}</span>
                            </h3>
                        </div>

                        <div class="text-xs text-[#5B6770] flex items-center gap-1.5">
                            <Clock class="w-3.5 h-3.5 text-[#5B6770]" />
                            <span>{{ tpl.trigger }}</span>
                        </div>

                        <!-- نص المعاينة للرسالة -->
                        <div class="p-3.5 rounded-2xl bg-[#F8FAFC] border border-[#D6E0E7] text-xs text-[#163E3E] leading-relaxed font-sans">
                            {{ tpl.sample }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- سجل الرسائل المرسلة (Outbox Log Table) -->
        <div class="space-y-4">
            <div class="flex items-center justify-between pb-2 border-b border-[#D6E0E7]">
                <div class="flex items-center gap-2">
                    <Clock class="w-5 h-5 text-[#163E3E]" />
                    <h2 class="text-lg font-bold text-[#163E3E]">سجل الرسائل المرسلة (Outbox)</h2>
                </div>
                <span class="text-xs text-[#5B6770]">تحديث لحظي لجميع إشعارات النظام</span>
            </div>

            <div v-if="logs.length > 0" class="rounded-3xl border border-[#D6E0E7] bg-white overflow-hidden shadow-2xs">
                <div class="overflow-x-auto">
                    <table class="w-full text-right text-sm">
                        <thead>
                            <tr class="bg-[#F8FAFC] border-b border-[#D6E0E7] text-[#5B6770] text-xs font-bold">
                                <th class="py-3.5 px-6">
                                    <ResourceTableHeading :href="route('renters.index')">المستأجر المستلم</ResourceTableHeading>
                                </th>
                                <th class="py-3.5 px-6">رقم الجوال</th>
                                <th class="py-3.5 px-6">القالب المستخدم</th>
                                <th class="py-3.5 px-6">وقت الجدولة</th>
                                <th class="py-3.5 px-6">وقت التسليم</th>
                                <th class="py-3.5 px-6">الحالة</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#D6E0E7]">
                            <tr v-for="log in logs" :key="log.id" class="hover:bg-[#F8F4EA] transition">
                                <td class="py-4 px-6 font-bold text-[#163E3E]">
                                    <Link
                                        :href="route('renters.show', log.renter_id)"
                                        class="rounded-sm hover:text-[#36594F] hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#52766C]"
                                    >
                                        {{ log.renter_name }}
                                    </Link>
                                </td>
                                <td class="py-4 px-6 font-mono text-xs text-[#5B6770]" dir="ltr">
                                    {{ log.recipient_phone }}
                                </td>
                                <td class="py-4 px-6 text-xs text-[#163E3E]">
                                    {{ log.template_name }}
                                </td>
                                <td class="py-4 px-6 font-mono text-xs text-[#5B6770]">
                                    {{ log.scheduled_at }}
                                </td>
                                <td class="py-4 px-6 font-mono text-xs text-[#5B6770]">
                                    {{ log.sent_at }}
                                </td>
                                <td class="py-4 px-6">
                                    <StatusBadge :status="log.status" :label="log.status_label" size="sm" />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-else class="p-8 text-center text-xs text-[#5B6770] bg-white rounded-3xl border border-[#D6E0E7]">
                لا توجد رسائل مجدولة أو مرسلة حالياً.
            </div>
        </div>

        <!-- نافذة إرسال رسالة تجريبية -->
        <div v-if="showTestModal" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
            <div @click="showTestModal = false" class="fixed inset-0 bg-[#163E3E]/60 backdrop-blur-xs"></div>
            <div class="relative w-full max-w-md bg-white rounded-3xl shadow-2xl border border-[#D6E0E7] p-6 z-10 animate-in fade-in zoom-in-95" dir="rtl">
                <div class="flex items-center justify-between pb-4 border-b border-[#D6E0E7] mb-5">
                    <h3 class="text-base font-bold text-[#163E3E]">إرسال رسالة WhatsApp تجريبية</h3>
                    <button @click="showTestModal = false" type="button" class="p-1.5 text-[#5B6770] hover:text-[#163E3E]">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="sendTestMessage" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-[#163E3E] mb-1.5">اختر المستأجر المستلم *</label>
                        <select
                            v-model="testForm.renter_id"
                            required
                            class="w-full h-11 px-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#163E3E] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition"
                        >
                            <option v-for="r in renters" :key="r.id" :value="r.id">
                                {{ r.name }} ({{ r.phone }})
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#163E3E] mb-1.5">اختر قالب الرسالة *</label>
                        <select
                            v-model="testForm.template_id"
                            required
                            class="w-full h-11 px-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#163E3E] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition"
                        >
                            <option v-for="t in templates" :key="t.id" :value="t.id">
                                {{ t.title }}
                            </option>
                        </select>
                    </div>

                    <div class="pt-4 flex items-center justify-end gap-3 border-t border-[#D6E0E7]">
                        <button
                            @click="showTestModal = false"
                            type="button"
                            class="h-10 px-4 rounded-xl border border-[#D6E0E7] text-xs font-semibold text-[#5B6770]"
                        >
                            إلغاء
                        </button>
                        <button
                            type="submit"
                            :disabled="testForm.processing"
                            class="h-10 px-5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition flex items-center gap-2"
                        >
                            <Send class="w-4 h-4" />
                            <span>{{ testForm.processing ? 'جاري الإرسال...' : 'إرسال الآن' }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
