<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Building2, Home, Users, FileText, ArrowLeft, Plus, Sparkles } from 'lucide-vue-next';

defineProps<{
    userName?: string;
}>();

const steps = [
    {
        num: 1,
        title: 'أضف أول عقار لك',
        desc: 'سجّل بيانات المبنى أو المجمع السكني وعنوانه ونوعه (عمارة، فيلا، مجمع تجاري).',
        icon: Building2,
        actionLabel: 'إضافة أول عقار',
        routeName: 'properties.create',
    },
    {
        num: 2,
        title: 'أضف الوحدات القابلة للتأجير',
        desc: 'قسّم عقارك إلى وحدات وشقق أو مساحات مستقلة وحدد القيمة الإيجارية التقديرية.',
        icon: Home,
        actionLabel: 'إدارة الوحدات',
        routeName: 'properties.index',
    },
    {
        num: 3,
        title: 'سجّل بيانات المستأجرين',
        desc: 'أدخل أسماء المستأجرين وأرقام هواتفهم لإرسال تذكيرات الإيجار التلقائية عبر واتساب.',
        icon: Users,
        actionLabel: 'إضافة مستأجر',
        routeName: 'renters.create',
    },
    {
        num: 4,
        title: 'وثّق أول عقد إيجار',
        desc: 'اربط المستأجر بالوحدة، وحدد تاريخ بداية ونهاية العقد ومواعيد استحقاق الإيجار.',
        icon: FileText,
        actionLabel: 'إنشاء عقد',
        routeName: 'leases.create',
    },
];
</script>

<template>
    <div class="rounded-3xl border border-[#D6E0E7] bg-white p-6 sm:p-10 shadow-sm space-y-8">
        <!-- الترويسة الترحيبية -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6 pb-6 border-b border-[#E8EFF4]">
            <div class="space-y-2">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-[#E8F3F5] text-[#0E6E7E] text-xs font-bold">
                    <Sparkles class="h-3.5 w-3.5" />
                    <span>مرحباً بك في دار لإدارة وتأجير العقارات</span>
                </div>
                <h2 class="text-2xl sm:text-3xl font-black text-[#0F2747] tracking-tight">
                    ابدأ بإعداد محفظتك العقارية في 4 خطوات بسيطة
                </h2>
                <p class="text-sm text-[#5B6770] font-medium max-w-2xl leading-relaxed">
                    منصة دار مصممة لمساعدتك على متابعة الإيجارات، وتحصيل المستحقات، وتتبع الإشغال، وإرسال تذكيرات واتساب لمستأجريك بكل دقة وسهولة.
                </p>
            </div>

            <Link
                :href="route('properties.create')"
                class="h-12 px-6 rounded-xl bg-[#0E6E7E] hover:bg-[#0B5A68] text-white text-sm font-bold transition shadow-xs flex items-center justify-center gap-2 shrink-0 self-start md:self-auto"
            >
                <Plus class="h-5 w-5" />
                <span>إضافة أول عقار الآن</span>
            </Link>
        </div>

        <!-- الخطوات الأربعة المرتبة -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
            <div
                v-for="step in steps"
                :key="step.num"
                class="rounded-2xl border border-[#E8EFF4] bg-[#FBFDFF] p-5 flex flex-col justify-between hover:border-[#0E6E7E]/40 hover:shadow-xs transition group"
            >
                <div>
                    <!-- الترقيم والأيقونة -->
                    <div class="flex items-center justify-between">
                        <span class="w-8 h-8 rounded-xl bg-[#E8F3F5] text-[#0E6E7E] font-black text-sm font-mono flex items-center justify-center">
                            {{ step.num }}
                        </span>
                        <div class="w-9 h-9 rounded-xl bg-[#F0F4F8] text-[#5B6770] group-hover:text-[#0E6E7E] group-hover:bg-[#E8F3F5] flex items-center justify-center transition-colors">
                            <component :is="step.icon" class="h-4 w-4" />
                        </div>
                    </div>

                    <!-- العنوان والوصف -->
                    <h3 class="text-sm font-bold text-[#0F2747] mt-3 group-hover:text-[#0E6E7E] transition">
                        {{ step.title }}
                    </h3>
                    <p class="text-xs text-[#5B6770] font-medium mt-1.5 leading-relaxed">
                        {{ step.desc }}
                    </p>
                </div>

                <!-- زر الإجراء للخطوة -->
                <div class="mt-5 pt-3 border-t border-[#E8EFF4]">
                    <Link
                        :href="route(step.routeName)"
                        class="w-full py-2 px-3 rounded-lg border border-[#D6E0E7] hover:border-[#0E6E7E] hover:bg-[#E8F3F5]/40 text-xs font-bold text-[#0F2747] group-hover:text-[#0E6E7E] transition flex items-center justify-center gap-1.5"
                    >
                        <span>{{ step.actionLabel }}</span>
                        <ArrowLeft class="h-3 w-3" />
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>

