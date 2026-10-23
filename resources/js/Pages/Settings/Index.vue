<script setup lang="ts">
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    Bell,
    Building2,
    Check,
    Coins,
    Lock,
    Mail,
    Phone,
    Save,
    Settings,
    Shield,
    Sliders,
    UserCheck,
    UserPlus,
    Users,
} from 'lucide-vue-next';

interface MemberItem {
    id: number;
    user_id: number;
    name: string;
    email: string;
    role: string;
    role_label: string;
    joined_at: string;
}

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
    members: MemberItem[];
    preferences: {
        auto_whatsapp_reminders: boolean;
        auto_instant_receipts: boolean;
        due_reminder_days_before: number;
        overdue_warning_days_after: number;
        weekly_summary_report: boolean;
    };
}>();

const activeTab = ref<'general' | 'team' | 'notifications'>('general');

const form = useForm({
    name: props.workspace.name,
    currency: props.workspace.currency,
    cr_number: props.workspace.cr_number,
    tax_number: props.workspace.tax_number,
    phone: props.workspace.phone,
    address: props.workspace.address,
});

const prefs = ref({ ...props.preferences });

const submitGeneral = () => {
    form.put(route('settings.update'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout
        title="الإعدادات — دار"
        active-tab="settings"
        header-title="إعدادات المنشأة والحساب"
        header-subtitle="إدارة الملف التجاري، فريق العمل والصلاحيات وقواعد التذكيرات التلقائية"
        breadcrumb="الإعدادات"
    >
        <!-- التبويبات الثلاثة الرئيسية -->
        <div class="flex items-center gap-2 border-b border-[#D6E0E7] pb-3">
            <button
                @click="activeTab = 'general'"
                type="button"
                class="px-5 py-2.5 rounded-xl text-xs font-bold transition cursor-pointer flex items-center gap-2"
                :class="activeTab === 'general'
                    ? 'bg-[#0E6E7E] text-white shadow-xs'
                    : 'bg-white text-[#5B6770] hover:text-[#0F2747] border border-[#D6E0E7]'"
            >
                <Building2 class="w-4 h-4" />
                <span>بيانات المنشأة التجارية</span>
            </button>

            <button
                @click="activeTab = 'team'"
                type="button"
                class="px-5 py-2.5 rounded-xl text-xs font-bold transition cursor-pointer flex items-center gap-2"
                :class="activeTab === 'team'
                    ? 'bg-[#0E6E7E] text-white shadow-xs'
                    : 'bg-white text-[#5B6770] hover:text-[#0F2747] border border-[#D6E0E7]'"
            >
                <Users class="w-4 h-4" />
                <span>فريق العمل والصلاحيات ({{ members.length }})</span>
            </button>

            <button
                @click="activeTab = 'notifications'"
                type="button"
                class="px-5 py-2.5 rounded-xl text-xs font-bold transition cursor-pointer flex items-center gap-2"
                :class="activeTab === 'notifications'
                    ? 'bg-[#0E6E7E] text-white shadow-xs'
                    : 'bg-white text-[#5B6770] hover:text-[#0F2747] border border-[#D6E0E7]'"
            >
                <Bell class="w-4 h-4" />
                <span>قواعد الإشعارات والتذكير</span>
            </button>
        </div>

        <!-- 1. تبويب بيانات المنشأة -->
        <div v-if="activeTab === 'general'" class="max-w-3xl space-y-6">
            <form @submit.prevent="submitGeneral" class="rounded-3xl border border-[#D6E0E7] bg-white p-6 sm:p-8 shadow-2xs space-y-6">
                <div class="space-y-1 pb-4 border-b border-[#D6E0E7]">
                    <h3 class="text-base font-bold text-[#0F2747]">المعلومات الرسمية للمنشأة العقارية</h3>
                    <p class="text-xs text-[#5B6770]">تظهر هذه البيانات على سندات القبض، عقود الإيجار والفواتير الضريبية المطبوعة</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-xs font-bold text-[#0F2747] mb-1.5">اسم المنشأة / المحفظة *</label>
                        <input
                            v-model="form.name"
                            type="text"
                            required
                            class="w-full h-11 px-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#0F2747] focus:border-[#0E6E7E] focus:bg-white focus:ring-1 focus:ring-[#0E6E7E] transition"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#0F2747] mb-1.5">عملة الحساب *</label>
                        <select
                            v-model="form.currency"
                            class="w-full h-11 px-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#0F2747] focus:border-[#0E6E7E] focus:bg-white focus:ring-1 focus:ring-[#0E6E7E] transition"
                        >
                            <option value="SAR">ريال سعودي (SAR)</option>
                            <option value="AED">درهم إماراتي (AED)</option>
                            <option value="USD">دولار أمريكي (USD)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#0F2747] mb-1.5">رقم السجل التجاري</label>
                        <input
                            v-model="form.cr_number"
                            type="text"
                            class="w-full h-11 px-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm font-mono text-[#0F2747] focus:border-[#0E6E7E] focus:bg-white focus:ring-1 focus:ring-[#0E6E7E] transition text-left"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#0F2747] mb-1.5">الرقم الضريبي (VAT)</label>
                        <input
                            v-model="form.tax_number"
                            type="text"
                            class="w-full h-11 px-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm font-mono text-[#0F2747] focus:border-[#0E6E7E] focus:bg-white focus:ring-1 focus:ring-[#0E6E7E] transition text-left"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#0F2747] mb-1.5">رقم هاتف المنشأة المعتمد</label>
                        <input
                            v-model="form.phone"
                            type="text"
                            class="w-full h-11 px-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm font-mono text-[#0F2747] focus:border-[#0E6E7E] focus:bg-white focus:ring-1 focus:ring-[#0E6E7E] transition text-left"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#0F2747] mb-1.5">العنوان والمقر الرئيسي</label>
                        <input
                            v-model="form.address"
                            type="text"
                            class="w-full h-11 px-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#0F2747] focus:border-[#0E6E7E] focus:bg-white focus:ring-1 focus:ring-[#0E6E7E] transition"
                        />
                    </div>
                </div>

                <div class="pt-4 flex items-center justify-end border-t border-[#D6E0E7]">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="h-11 px-6 rounded-xl bg-[#0E6E7E] hover:bg-[#0B5A68] text-white text-xs font-bold transition flex items-center gap-2 shadow-xs cursor-pointer"
                    >
                        <Save class="w-4 h-4" />
                        <span>{{ form.processing ? 'جاري الحفظ...' : 'حفظ التغييرات' }}</span>
                    </button>
                </div>
            </form>
        </div>

        <!-- 2. تبويب فريق العمل -->
        <div v-if="activeTab === 'team'" class="space-y-6 max-w-4xl">
            <div class="rounded-3xl border border-[#D6E0E7] bg-white p-6 shadow-2xs space-y-5">
                <div class="flex items-center justify-between pb-4 border-b border-[#D6E0E7]">
                    <div>
                        <h3 class="text-base font-bold text-[#0F2747]">أعضاء فريق العمل والصلاحيات</h3>
                        <p class="text-xs text-[#5B6770]">الأشخاص المصرح لهم بالدخول وإدارة المحفظة العقارية</p>
                    </div>

                    <button
                        type="button"
                        class="h-10 px-4 rounded-xl bg-[#0E6E7E] hover:bg-[#0B5A68] text-white text-xs font-bold transition flex items-center gap-1.5 shadow-xs cursor-pointer"
                    >
                        <UserPlus class="w-4 h-4" />
                        <span>دعوة عضو جديد</span>
                    </button>
                </div>

                <div class="divide-y divide-[#D6E0E7]">
                    <div
                        v-for="member in members"
                        :key="member.id"
                        class="py-4 flex items-center justify-between"
                    >
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-[#E3F1F4] text-[#0E6E7E] flex items-center justify-center font-bold text-xs">
                                {{ member.name.charAt(0) }}
                            </div>
                            <div>
                                <h4 class="text-sm font-bold text-[#0F2747]">{{ member.name }}</h4>
                                <span class="text-xs text-[#5B6770] font-mono">{{ member.email }}</span>
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            <span class="px-3 py-1 rounded-full text-xs font-bold bg-[#F1F5F9] text-[#0F2747] border border-black/5">
                                {{ member.role_label }}
                            </span>
                            <span class="text-xs text-[#5B6770]">انضم في {{ member.joined_at }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. تبويب قواعد التذكيرات -->
        <div v-if="activeTab === 'notifications'" class="max-w-3xl space-y-6">
            <div class="rounded-3xl border border-[#D6E0E7] bg-white p-6 sm:p-8 shadow-2xs space-y-6">
                <div class="space-y-1 pb-4 border-b border-[#D6E0E7]">
                    <h3 class="text-base font-bold text-[#0F2747]">أتمتة الرسائل والتنبيهات الذكية</h3>
                    <p class="text-xs text-[#5B6770]">تخصيص توقيتات إرسال تذكيرات الاستحقاق وسندات القبض</p>
                </div>

                <div class="space-y-4">
                    <label class="flex items-start gap-3 p-4 rounded-2xl bg-[#F8FAFC] border border-[#D6E0E7] cursor-pointer hover:bg-slate-100 transition">
                        <input
                            type="checkbox"
                            v-model="prefs.auto_whatsapp_reminders"
                            class="w-4 h-4 mt-0.5 rounded text-[#0E6E7E] focus:ring-[#0E6E7E]"
                        />
                        <div>
                            <span class="text-sm font-bold text-[#0F2747] block">تذكيرات الاستحقاق التلقائية قبل موعد الدفع</span>
                            <span class="text-xs text-[#5B6770] block mt-0.5">
                                إرسال رسالة تذكير للمستأجر قبل موعد الاستحقاق بـ 3 أيام تلقائياً
                            </span>
                        </div>
                    </label>

                    <label class="flex items-start gap-3 p-4 rounded-2xl bg-[#F8FAFC] border border-[#D6E0E7] cursor-pointer hover:bg-slate-100 transition">
                        <input
                            type="checkbox"
                            v-model="prefs.auto_instant_receipts"
                            class="w-4 h-4 mt-0.5 rounded text-[#0E6E7E] focus:ring-[#0E6E7E]"
                        />
                        <div>
                            <span class="text-sm font-bold text-[#0F2747] block">إصدار وإرسال سند قبض إلكتروني فوري</span>
                            <span class="text-xs text-[#5B6770] block mt-0.5">
                                إرسال رابط سند القبض المعتمد للمستأجر فور تسجيل أي دفعة يدوية في النظام
                            </span>
                        </div>
                    </label>

                    <label class="flex items-start gap-3 p-4 rounded-2xl bg-[#F8FAFC] border border-[#D6E0E7] cursor-pointer hover:bg-slate-100 transition">
                        <input
                            type="checkbox"
                            v-model="prefs.weekly_summary_report"
                            class="w-4 h-4 mt-0.5 rounded text-[#0E6E7E] focus:ring-[#0E6E7E]"
                        />
                        <div>
                            <span class="text-sm font-bold text-[#0F2747] block">تقرير التحصيل الأسبوعي لمالك المحفظة</span>
                            <span class="text-xs text-[#5B6770] block mt-0.5">
                                ملخص دوري كل صباح أحد بنسب الإشغال، المبالغ المحصلة والمتأخرات المعلقة
                            </span>
                        </div>
                    </label>
                </div>

                <div class="pt-4 flex items-center justify-end border-t border-[#D6E0E7]">
                    <button
                        type="button"
                        class="h-11 px-6 rounded-xl bg-[#0E6E7E] hover:bg-[#0B5A68] text-white text-xs font-bold transition flex items-center gap-2 shadow-xs cursor-pointer"
                    >
                        <Save class="w-4 h-4" />
                        <span>حفظ تفضيلات التنبيه</span>
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

