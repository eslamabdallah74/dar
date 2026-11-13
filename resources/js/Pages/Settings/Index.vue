<script setup lang="ts">
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    Bell,
    Building2,
    Globe,
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
        whatsapp_message_language?: string;
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
    whatsapp_message_language: props.preferences.whatsapp_message_language ?? 'ar',
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
        :title="`${$t('settings.title')} — Nook Mate`"
        active-tab="settings"
        :header-title="$t('settings.title')"
        :header-subtitle="$t('settings.subtitle')"
        :breadcrumb="$t('nav.settings')"
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
                <span>{{ $t('settings.tab_general') }}</span>
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
                <span>{{ $t('settings.tab_notifications') }}</span>
            </button>
        </div>

        <!-- 1. تبويب بيانات المنشأة -->
        <div v-if="activeTab === 'general'" class="w-full space-y-6">
            <form @submit.prevent="submitGeneral" class="rounded-3xl border border-[#D6E0E7] bg-white p-6 sm:p-8 shadow-2xs space-y-6">
                <div class="space-y-1 pb-4 border-b border-[#D6E0E7]">
                    <h3 class="text-base font-bold text-[#163E3E]">{{ $t('settings.tab_general') }}</h3>
                    <p class="text-xs text-[#5B6770]">{{ $t('settings.subtitle') }}</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                    <div>
                        <label for="workspace-name" class="block text-xs font-bold text-[#163E3E] mb-1.5">{{ $t('settings.workspace_name') }} *</label>
                        <input
                            id="workspace-name"
                            v-model="form.name"
                            type="text"
                            required
                            minlength="3"
                            :aria-label="$t('settings.workspace_name')"
                            class="w-full h-11 px-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#163E3E] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition"
                        />
                        <p v-if="form.errors.name" class="text-xs text-rose-600 font-semibold mt-1">
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <div>
                        <label for="settings-currency" class="block text-xs font-bold text-[#163E3E] mb-1.5">{{ $t('settings.currency') }} *</label>
                        <select
                            id="settings-currency"
                            :aria-label="$t('settings.currency')"
                            v-model="form.currency"
                            class="w-full h-11 px-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#163E3E] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition"
                        >
                            <option value="EGP">{{ $t('common.currency_egp') }} (EGP)</option>
                            <option value="SAR">{{ $t('common.currency_sar') }} (SAR)</option>
                            <option value="AED">AED</option>
                            <option value="USD">USD ($)</option>
                        </select>
                        <p v-if="form.errors.currency" class="text-xs text-rose-600 font-semibold mt-1">
                            {{ form.errors.currency }}
                        </p>
                    </div>

                    <div>
                        <label for="settings-cr-number" class="block text-xs font-bold text-[#163E3E] mb-1.5">{{ $t('settings.cr_number') }}</label>
                        <input
                            id="settings-cr-number"
                            :aria-label="$t('settings.cr_number')"
                            v-model="form.cr_number"
                            type="text"
                            class="w-full h-11 px-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#163E3E] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition text-start"
                        />
                        <p v-if="form.errors.cr_number" class="text-xs text-rose-600 font-semibold mt-1">
                            {{ form.errors.cr_number }}
                        </p>
                    </div>

                    <div>
                        <label for="settings-tax-number" class="block text-xs font-bold text-[#163E3E] mb-1.5">{{ $t('settings.tax_number') }}</label>
                        <input
                            id="settings-tax-number"
                            :aria-label="$t('settings.tax_number')"
                            v-model="form.tax_number"
                            type="text"
                            class="w-full h-11 px-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#163E3E] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition text-start"
                        />
                        <p v-if="form.errors.tax_number" class="text-xs text-rose-600 font-semibold mt-1">
                            {{ form.errors.tax_number }}
                        </p>
                    </div>

                    <div>
                        <label for="settings-phone" class="block text-xs font-bold text-[#163E3E] mb-1.5">{{ $t('settings.phone') }}</label>
                        <input
                            id="settings-phone"
                            :aria-label="$t('settings.phone')"
                            v-model="form.phone"
                            type="tel"
                            placeholder="01XXXXXXXXX"
                            class="w-full h-11 px-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#163E3E] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition text-start font-mono"
                        />
                        <p v-if="form.errors.phone" class="text-xs text-rose-600 font-semibold mt-1">
                            {{ form.errors.phone }}
                        </p>
                    </div>

                    <div>
                        <label for="settings-address" class="block text-xs font-bold text-[#163E3E] mb-1.5">{{ $t('settings.address') }}</label>
                        <input
                            id="settings-address"
                            :aria-label="$t('settings.address')"
                            v-model="form.address"
                            type="text"
                            class="w-full h-11 px-3.5 rounded-xl border border-[#D6E0E7] bg-[#F8FAFC] text-sm text-[#163E3E] focus:border-[#163E3E] focus:bg-white focus:ring-1 focus:ring-[#163E3E] transition"
                        />
                        <p v-if="form.errors.address" class="text-xs text-rose-600 font-semibold mt-1">
                            {{ form.errors.address }}
                        </p>
                    </div>
                </div>

                <div class="pt-4 flex items-center justify-end border-t border-[#D6E0E7]">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="h-11 px-6 rounded-xl bg-[#163E3E] hover:bg-[#102F2F] text-white text-xs font-bold transition flex items-center gap-2 shadow-xs cursor-pointer"
                    >
                        <Save class="w-4 h-4" />
                        <span>{{ form.processing ? $t('common.loading') : $t('common.save_changes') }}</span>
                    </button>
                </div>
            </form>
        </div>

        <!-- 2. تبويب قواعد التذكيرات ولغة المراسلات -->
        <div v-if="activeTab === 'notifications'" class="w-full space-y-6">
            <form @submit.prevent="submitNotifications" class="rounded-3xl border border-[#D6E0E7] bg-white p-6 sm:p-8 shadow-2xs space-y-6">
                <div class="space-y-1 pb-4 border-b border-[#D6E0E7]">
                    <h3 class="text-base font-bold text-[#163E3E]">{{ $t('settings.tab_notifications') }}</h3>
                    <p class="text-xs text-[#5B6770]">{{ $t('settings.subtitle') }}</p>
                </div>

                <div class="space-y-4">
                    <!-- WhatsApp Language Preference Selector -->
                    <div class="p-4 rounded-2xl bg-[#F8FAFC] border border-[#D6E0E7] space-y-2">
                        <div class="flex items-center gap-2">
                            <Globe class="w-4 h-4 text-[#163E3E]" />
                            <label for="pref-whatsapp-language" class="text-sm font-bold text-[#163E3E]">
                                {{ $t('settings.whatsapp_language') }}
                            </label>
                        </div>
                        <p class="text-xs text-[#5B6770]">
                            {{ $t('settings.whatsapp_language_desc') }}
                        </p>
                        <select
                            id="pref-whatsapp-language"
                            v-model="prefsForm.whatsapp_message_language"
                            :aria-label="$t('settings.whatsapp_language')"
                            class="w-full sm:w-72 h-11 px-3.5 rounded-xl border border-[#D6E0E7] bg-white text-sm text-[#163E3E] font-medium focus:border-[#163E3E] focus:ring-1 focus:ring-[#163E3E] transition"
                        >
                            <option value="ar">{{ $t('common.arabic') }} (Arabic — مصري/عربي)</option>
                            <option value="en">{{ $t('common.english') }} (English)</option>
                        </select>
                        <p v-if="prefsForm.errors.whatsapp_message_language" class="text-xs text-rose-600 font-semibold mt-1">
                            {{ prefsForm.errors.whatsapp_message_language }}
                        </p>
                    </div>

                    <label for="pref-auto-whatsapp-reminders" class="flex items-start gap-3 p-4 rounded-2xl bg-[#F8FAFC] border border-[#D6E0E7] cursor-pointer hover:bg-slate-100 transition">
                        <input
                            id="pref-auto-whatsapp-reminders"
                            :aria-label="$t('settings.whatsapp_reminders')"
                            type="checkbox"
                            v-model="prefsForm.auto_whatsapp_reminders"
                            class="w-4 h-4 mt-0.5 rounded text-[#163E3E] focus:ring-[#163E3E]"
                        />
                        <div>
                            <span class="text-sm font-bold text-[#163E3E] block">{{ $t('settings.whatsapp_reminders') }}</span>
                            <span class="text-xs text-[#5B6770] block mt-0.5">
                                {{ prefsForm.due_reminder_days_before }} {{ $t('settings.due_reminder_days') }}
                            </span>
                        </div>
                    </label>

                    <label for="pref-auto-instant-receipts" class="flex items-start gap-3 p-4 rounded-2xl bg-[#F8FAFC] border border-[#D6E0E7] cursor-pointer hover:bg-slate-100 transition">
                        <input
                            id="pref-auto-instant-receipts"
                            :aria-label="$t('settings.instant_receipts')"
                            type="checkbox"
                            v-model="prefsForm.auto_instant_receipts"
                            class="w-4 h-4 mt-0.5 rounded text-[#163E3E] focus:ring-[#163E3E]"
                        />
                        <div>
                            <span class="text-sm font-bold text-[#163E3E] block">{{ $t('settings.instant_receipts') }}</span>
                            <span class="text-xs text-[#5B6770] block mt-0.5">
                                {{ $t('settings.instant_receipts') }}
                            </span>
                        </div>
                    </label>

                    <label for="weekly-summary-report" class="flex items-start gap-3 p-4 rounded-2xl bg-[#F8FAFC] border border-[#D6E0E7] cursor-pointer hover:bg-slate-100 transition">
                        <input
                            id="weekly-summary-report"
                            type="checkbox"
                            v-model="prefsForm.weekly_summary_report"
                            :aria-label="$t('settings.weekly_summary')"
                            class="w-4 h-4 mt-0.5 rounded text-[#163E3E] focus:ring-[#163E3E]"
                        />
                        <div>
                            <span class="text-sm font-bold text-[#163E3E] block">{{ $t('settings.weekly_summary') }}</span>
                            <span class="text-xs text-[#5B6770] block mt-0.5">
                                {{ $t('settings.weekly_summary') }}
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
                        <span>{{ prefsForm.processing ? $t('common.loading') : $t('common.save_changes') }}</span>
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
