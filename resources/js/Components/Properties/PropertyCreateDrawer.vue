<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Building2, Check, House, X } from 'lucide-vue-next';

defineProps<{
    show: boolean;
}>();

const emit = defineEmits<{
    (event: 'close'): void;
}>();

const propertyTypes = [
    {
        value: 'building',
        title: 'عمارة',
        description: 'أضف عمارة ثم أنشئ الشقق الموجودة بداخلها.',
        icon: Building2,
    },
    {
        value: 'apartment',
        title: 'شقة',
        description: 'أضف شقة مستقلة، ثم أجّرها كاملة أو قسّمها إلى أوض وسراير.',
        icon: House,
    },
] as const;

const form = useForm({
    name: '',
    property_type: 'building' as 'building' | 'apartment',
    address: '',
    after_save: 'continue' as 'continue' | 'index',
});

function submit(afterSave: 'continue' | 'index') {
    form.after_save = afterSave;
    form.post(route('properties.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            form.clearErrors();
        },
    });
}

function closeDrawer() {
    if (!form.processing) {
        form.reset();
        form.clearErrors();
    }

    emit('close');
}
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-50" dir="rtl">
        <button
            type="button"
            aria-label="إغلاق"
            class="absolute inset-0 h-full w-full bg-[#10251D]/45 backdrop-blur-[2px]"
            @click="closeDrawer"
        ></button>

        <section
            role="dialog"
            aria-modal="true"
            aria-labelledby="property-drawer-title"
            class="absolute inset-y-0 right-0 flex w-full max-w-xl flex-col bg-white shadow-2xl"
        >
            <header class="flex items-start justify-between gap-5 border-b border-[#E3EAE6] px-5 py-5 sm:px-8 sm:py-7">
                <div>
                    <span class="mb-2 block text-xs font-bold text-[#18705A]">خطوة ١ من ٢</span>
                    <h2 id="property-drawer-title" class="text-xl font-extrabold text-[#18372E] sm:text-2xl">
                        إضافة عقار جديد
                    </h2>
                    <p class="mt-2 max-w-md text-sm leading-6 text-[#65766F]">
                        ابدأ بإضافة العمارة أو الشقة، ويمكنك تنظيم الوحدات بداخلها لاحقًا.
                    </p>
                </div>
                <button
                    type="button"
                    aria-label="إغلاق"
                    class="grid size-10 shrink-0 place-items-center rounded-xl border border-[#E3EAE6] text-[#65766F] transition hover:bg-[#F3F7F4] hover:text-[#18372E]"
                    @click="closeDrawer"
                >
                    <X class="size-5" />
                </button>
            </header>

            <form class="flex min-h-0 flex-1 flex-col" @submit.prevent="submit('continue')">
                <div class="flex-1 space-y-8 overflow-y-auto px-5 py-6 sm:px-8 sm:py-8">
                    <section aria-labelledby="property-type-title" class="space-y-3">
                        <div class="flex items-center gap-3">
                            <span class="grid size-8 place-items-center rounded-full bg-[#E8F2ED] text-sm font-bold text-[#18705A]">١</span>
                            <h3 id="property-type-title" class="text-sm font-bold text-[#18372E]">اختار نوع العقار</h3>
                        </div>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <button
                                v-for="type in propertyTypes"
                                :key="type.value"
                                type="button"
                                :aria-pressed="form.property_type === type.value"
                                class="relative flex min-h-40 flex-col items-start rounded-2xl border p-4 text-right transition sm:p-5"
                                :class="form.property_type === type.value
                                    ? 'border-[#18705A] bg-[#EFF7F2] ring-1 ring-[#18705A]'
                                    : 'border-[#DFE8E2] bg-white hover:border-[#9BBEAD] hover:bg-[#F8FBF9]'"
                                @click="form.property_type = type.value"
                            >
                                <span
                                    class="mb-4 grid size-12 place-items-center rounded-xl"
                                    :class="form.property_type === type.value ? 'bg-[#DCEDE3] text-[#18705A]' : 'bg-[#F1F5F2] text-[#60776C]'"
                                >
                                    <component :is="type.icon" class="size-6" :stroke-width="1.8" />
                                </span>
                                <span class="text-base font-extrabold text-[#18372E]">{{ type.title }}</span>
                                <span class="mt-1 text-xs leading-5 text-[#65766F]">{{ type.description }}</span>
                                <span
                                    v-if="form.property_type === type.value"
                                    class="absolute left-4 top-4 grid size-6 place-items-center rounded-full bg-[#18705A] text-white"
                                >
                                    <Check class="size-4" :stroke-width="2.5" />
                                </span>
                            </button>
                        </div>
                        <p v-if="form.errors.property_type" class="text-xs font-medium text-rose-600">{{ form.errors.property_type }}</p>
                    </section>

                    <section aria-labelledby="property-details-title" class="space-y-4">
                        <div class="flex items-center gap-3">
                            <span class="grid size-8 place-items-center rounded-full bg-[#E8F2ED] text-sm font-bold text-[#18705A]">٢</span>
                            <h3 id="property-details-title" class="text-sm font-bold text-[#18372E]">بيانات العقار</h3>
                        </div>

                        <div>
                            <label for="property-name" class="mb-2 block text-sm font-bold text-[#29483D]">اسم العقار <span class="text-rose-600">*</span></label>
                            <input
                                id="property-name"
                                v-model="form.name"
                                type="text"
                                minlength="3"
                                maxlength="255"
                                required
                                autofocus
                                :placeholder="form.property_type === 'building' ? 'مثال: عمارة النور' : 'مثال: شقة 101'"
                                class="h-12 w-full rounded-xl border border-[#D8E4DC] bg-[#FBFCFB] px-4 text-sm text-[#18372E] outline-none transition placeholder:text-[#98A79F] focus:border-[#18705A] focus:bg-white focus:ring-2 focus:ring-[#18705A]/15"
                            />
                            <p v-if="form.errors.name" class="mt-1.5 text-xs font-medium text-rose-600">{{ form.errors.name }}</p>
                        </div>

                        <div>
                            <label for="property-address" class="mb-2 block text-sm font-bold text-[#29483D]">العنوان <span class="font-normal text-[#829189]">(اختياري)</span></label>
                            <input
                                id="property-address"
                                v-model="form.address"
                                type="text"
                                maxlength="500"
                                placeholder="حي المعادي، شارع النصر"
                                class="h-12 w-full rounded-xl border border-[#D8E4DC] bg-[#FBFCFB] px-4 text-sm text-[#18372E] outline-none transition placeholder:text-[#98A79F] focus:border-[#18705A] focus:bg-white focus:ring-2 focus:ring-[#18705A]/15"
                            />
                            <p v-if="form.errors.address" class="mt-1.5 text-xs font-medium text-rose-600">{{ form.errors.address }}</p>
                        </div>
                    </section>
                </div>

                <footer class="grid gap-3 border-t border-[#E3EAE6] bg-white px-5 py-4 sm:grid-cols-2 sm:px-8 sm:py-5">
                    <button
                        type="button"
                        :disabled="form.processing"
                        class="inline-flex min-h-12 items-center justify-center rounded-xl bg-[#176B55] px-4 text-sm font-bold text-white transition hover:bg-[#105742] disabled:cursor-not-allowed disabled:opacity-60"
                        @click="submit('continue')"
                    >
                        {{ form.processing ? 'جاري الحفظ...' : 'حفظ والمتابعة لإضافة الوحدات' }}
                    </button>
                    <button
                        type="button"
                        :disabled="form.processing"
                        class="inline-flex min-h-12 items-center justify-center rounded-xl border border-[#D8E4DC] bg-white px-4 text-sm font-bold text-[#29483D] transition hover:bg-[#F5F8F6] disabled:cursor-not-allowed disabled:opacity-60"
                        @click="submit('index')"
                    >
                        حفظ والعودة للعقارات
                    </button>
                </footer>
            </form>
        </section>
    </div>
</template>