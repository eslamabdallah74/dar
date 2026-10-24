<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';

defineProps<{
    mustVerifyEmail?: boolean;
    status?: string;
}>();
</script>

<template>
    <AppLayout
        title="الملف الشخصي والحساب — دار"
        active-tab="settings"
        header-title="الملف الشخصي والحساب"
        header-subtitle="إدارة بياناتك الشخصية وكلمة المرور والأمان"
    >
        <div class="space-y-6 w-full">
            <!-- 1. تعديل البيانات الشخصية -->
            <div class="bg-white rounded-2xl border border-[#D6E0E7] p-6 sm:p-8 shadow-2xs">
                <UpdateProfileInformationForm
                    :must-verify-email="mustVerifyEmail"
                    :status="status"
                    class="max-w-2xl"
                />
            </div>

            <!-- 2. تغيير كلمة المرور -->
            <div class="bg-white rounded-2xl border border-[#D6E0E7] p-6 sm:p-8 shadow-2xs">
                <UpdatePasswordForm class="max-w-2xl" />
            </div>

            <!-- 3. حذف الحساب -->
            <div class="bg-white rounded-2xl border border-rose-200/80 p-6 sm:p-8 shadow-2xs">
                <DeleteUserForm class="max-w-2xl" />
            </div>
        </div>
    </AppLayout>
</template>
