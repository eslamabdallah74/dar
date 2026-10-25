<script setup lang="ts">
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    Bell,
    Building2,
    Save,
} from 'lucide-vue-next';

const props = defineProps<{
    workspace: {
        id: number;
        name: string;
        currency: string;
        cr_number: string;
        tax_number: string;
        phone: string;
        address: string;
    };
    preferences: {
        auto_whatsapp_reminders: boolean;
        auto_instant_receipts: boolean;
        due_reminder_days_before: number;
        overdue_warning_days_after: number;
        weekly_summary_report: boolean;
    };
}>();

const activeTab = ref<'general' | 'notifications'>('general');

const form = useForm({
    name: props.workspace.name,
    currency: props.workspace.currency,
    cr_number: props.workspace.cr_number,
    tax_number: props.workspace.tax_number,
    phone: props.workspace.phone,
    address: props.workspace.address,
});

const prefsForm = useForm({
    auto_whatsapp_reminders: props.preferences.auto_whatsapp_reminders,
    auto_instant_receipts: props.preferences.auto_instant_receipts,
    due_reminder_days_before: props.preferences.due_reminder_days_before,
    overdue_warning_days_after: props.preferences.overdue_warning_days_after,
    weekly_summary_report: props.preferences.weekly_summary_report,
});

const submitGeneral = () => {
    form.put(route('settings.update'), {
        preserveScroll: true,
    });
};

const submitNotifications = () => {
    prefsForm.put(route('settings.update'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout
        title="الإعدادات — Nook Mate"
        active-tab="settings"
        header-title="إعدادات المنشأة والحساب"
        header-subtitle="إدارة الملف التجاري، العملة، وقواعد التذكيرات التلقائية"
        breadcrumb="الإعدادات"
    >
        <!-- التبويبات الرئيسية -->
        <div class="flex items-center gap-2 border-b border-[#D6E0E7] pb-3">
            <button
                @click="activeTab = 'general'"
                type="button"
                class="px-5 py-2.5 rounded-xl text-xs font-bold transition cursor-pointer flex items-center gap-2"
                :class="activeTab === 'general'
                    ? 'bg-[#163E3E] text-white shadow-xs'
                    : 'bg-white text-[#5B6770] hover:text-[#163E3E] border border-[#D6E0E7]'"
            >
                <Building2 class="w-4 h-4" />
                <span>بيانات المنشأة التجارية</span>
            </button>

            <button
                @click="activeTab = 'notifications'"
                type="button"
                class="px-5 py-2.5 rounded-xl text-xs font-bold transition cursor-pointer flex items-center gap-2"
                :class="activeTab === 'notifications'
                    ? 'bg-[#163E3E] text-white shadow-xs'
                    : 'bg-white text-[#5B6770] hover:text-[#163E3E] border border-[#D6E0E7]'"
            >
                <Bell class="w-4 h-4" />
                <span>قواعد الإشعارات والتذكير</span>
            </button>
        </div>

        <!-- 1. تبويب بيانات المنشأة -->
        <div v-if="activeTab === 'general'" class="w-full space-y-6">
            <form @submit.prevent="submitGeneral" class="rounded-3xl border border-[#D6E0E7] bg-white p-6 sm:p-8 shadow-2xs space-y-6">
                <div class="space-y-1 pb-4 border-b border-[#D6E0E7]">
                    <h3 class="text-base font-bold text-[#163E3E]">المعلومات الرسمية للمنشأة العقارية</h3>
                    <p class="text-xs text-[#5B6770]">تظهر هذه البيانات على سندات القبض، عقود الإيجار والفواتير الضريبية المطبوعة</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                    <div>
                        <label class="block text-xs font-bold text-[#163E3E] mb-1.5">اسم المنشأة / المحفظة *</label>
                        <input
                            v-model="form.name"
                            type="text"
                            required
                            minlength="3"
                            class="w-full h-11 px-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#163E3E] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition"
                        />
                        <p v-if="form.errors.name" class="text-xs text-rose-600 font-semibold mt-1">
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#163E3E] mb-1.5">عملة الحساب *</label>
                        <select
                            v-model="form.currency"
                            class="w-full h-11 px-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#163E3E] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition"
                        >
                            <option value="EGP">جنيه مصري (EGP)</option>
                            <option value="SAR">ريال سعودي (SAR)</option>
                            <option value="AED">درهم إماراتي (AED)</option>
                            <option value="USD">دولار أمريكي (USD)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#163E3E] mb-1.5">رقم السجل التجاري <span class="text-[#8A9AA7] font-normal text-[11px]">(اختياري)</span></label>
                        <input
                            v-model="form.cr_number"
                            type="text"
                            class="w-full h-11 px-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#163E3E] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition text-right"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#163E3E] mb-1.5">الرقم الضريبي <span class="text-[#8A9AA7] font-normal text-[11px]">(اختياري)</span></label>
                        <input
                            v-model="form.tax_number"
                            type="text"
                            class="w-full h-11 px-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#163E3E] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition text-right"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#163E3E] mb-1.5">رقم هاتف المنشأة <span class="text-[#8A9AA7] font-normal text-[11px]">(اختياري)</span></label>
                        <input
                            v-model="form.phone"
                            type="tel"
                            placeholder="01XXXXXXXXX"
                            class="w-full h-11 px-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#163E3E] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition text-right font-mono"
                        />
                        <p v-if="form.errors.phone" class="text-xs text-rose-600 font-semibold mt-1">
                            {{ form.errors.phone }}
                        </p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#163E3E] mb-1.5">العنوان والمقر الرئيسي <span class="text-[#8A9AA7] font-normal text-[11px]">(اختياري)</span></label>
                        <input
                            v-model="form.address"
                            type="text"
                            class="w-full h-11 px-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#163E3E] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition"
                        />
                    </div>
                </div>

                <div class="pt-4 flex items-center justify-end border-t border-[#D6E0E7]">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="h-11 px-6 rounded-xl bg-[#163E3E] hover:bg-[#102F2F] text-white text-xs font-bold transition flex items-center gap-2 shadow-xs cursor-pointer"
                    >
                        <Save class="w-4 h-4" />
                        <span>{{ form.processing ? 'جاري الحفظ...' : 'حفظ التغييرات' }}</span>
                    </button>
                </div>
            </form>
        </div>

        <!-- 2. تبويب قواعد التذكيرات -->
        <div v-if="activeTab === 'notifications'" class="w-full space-y-6">
            <form @submit.prevent="submitNotifications" class="rounded-3xl border border-[#D6E0E7] bg-white p-6 sm:p-8 shadow-2xs space-y-6">
                <div class="space-y-1 pb-4 border-b border-[#D6E0E7]">
                    <h3 class="text-base font-bold text-[#163E3E]">أتمتة الرسائل والتنبيهات الذكية</h3>
                    <p class="text-xs text-[#5B6770]">تخصيص توقيتات إرسال تذكيرات الاستحقاق وسندات القبض</p>
                </div>

                <div class="space-y-4">
                    <label class="flex items-start gap-3 p-4 rounded-2xl bg-[#F8FAFC] border border-[#D6E0E7] cursor-pointer hover:bg-slate-100 transition">
                        <input
                            type="checkbox"
                            v-model="prefsForm.auto_whatsapp_reminders"
                            class="w-4 h-4 mt-0.5 rounded text-[#163E3E] focus:ring-[#163E3E]"
                        />
                        <div>
                            <span class="text-sm font-bold text-[#163E3E] block">تذكيرات الاستحقاق التلقائية قبل موعد الدفع</span>
                            <span class="text-xs text-[#5B6770] block mt-0.5">
                                إرسال رسالة تذكير للمستأجر قبل موعد الاستحقاق بـ {{ prefsForm.due_reminder_days_before }} أيام تلقائياً
                            </span>
                        </div>
                    </label>

                    <label class="flex items-start gap-3 p-4 rounded-2xl bg-[#F8FAFC] border border-[#D6E0E7] cursor-pointer hover:bg-slate-100 transition">
                        <input
                            type="checkbox"
                            v-model="prefsForm.auto_instant_receipts"
                            class="w-4 h-4 mt-0.5 rounded text-[#163E3E] focus:ring-[#163E3E]"
                        />
                        <div>
                            <span class="text-sm font-bold text-[#163E3E] block">إصدار وإرسال سند قبض إلكتروني فوري</span>
                            <span class="text-xs text-[#5B6770] block mt-0.5">
                                إرسال رابط سند القبض المعتمد للمستأجر فور تسجيل أي دفعة يدوية في النظام
                            </span>
                        </div>
                    </label>

                    <label class="flex items-start gap-3 p-4 rounded-2xl bg-[#F8FAFC] border border-[#D6E0E7] cursor-pointer hover:bg-slate-100 transition">
                        <input
                            type="checkbox"
                            v-model="prefsForm.weekly_summary_report"
                            class="w-4 h-4 mt-0.5 rounded text-[#163E3E] focus:ring-[#163E3E]"
                        />
                        <div>
                            <span class="text-sm font-bold text-[#163E3E] block">تقرير التحصيل الأسبوعي لمالك المحفظة</span>
                            <span class="text-xs text-[#5B6770] block mt-0.5">
                                ملخص دوري كل صباح أحد بنسب الإشغال، المبالغ المحصلة والمتأخرات المعلقة
                            </span>
                        </div>
                    </label>
                </div>

                <div class="pt-4 flex items-center justify-end border-t border-[#D6E0E7]">
                    <button
                        type="submit"
                        :disabled="prefsForm.processing"
                        class="h-11 px-6 rounded-xl bg-[#163E3E] hover:bg-[#102F2F] text-white text-xs font-bold transition flex items-center gap-2 shadow-xs cursor-pointer"
                    >
                        <Save class="w-4 h-4" />
                        <span>{{ prefsForm.processing ? 'جاري الحفظ...' : 'حفظ تفضيلات التنبيه' }}</span>
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
