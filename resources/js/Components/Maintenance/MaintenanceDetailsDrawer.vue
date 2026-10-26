<script setup lang="ts">
import { ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import {
    AlertCircle,
    Building2,
    Calendar,
    Check,
    CheckCircle2,
    CreditCard,
    Download,
    ExternalLink,
    Eye,
    FileText,
    History,
    Home,
    Loader2,
    MessageSquare,
    Paperclip,
    Plus,
    Receipt,
    Trash2,
    UploadCloud,
    User,
    Wrench,
    X,
} from 'lucide-vue-next';

interface AttachmentItem {
    id: number;
    original_filename: string;
    file_size: number;
    formatted_size: string;
    mime_type: string;
    is_image: boolean;
    is_pdf: boolean;
    preview_url: string;
    download_url: string;
    uploaded_by_name: string;
    created_at: string;
}

interface NoteItem {
    id: number;
    note: string;
    created_by_name: string;
    created_at: string;
    raw_created_at?: string;
}

interface ActivityItem {
    id: number;
    description: string;
    causer_name: string;
    properties: Record<string, any>;
    created_at: string;
    raw_created_at?: string;
}

interface MaintenanceDetails {
    id: number;
    title: string;
    description: string;
    status: string;
    status_label: string;
    is_pending: boolean;
    is_in_progress: boolean;
    is_completed: boolean;
    can_be_completed: boolean;
    property_id: number;
    property_name: string;
    unit_id: number | null;
    unit_name: string;
    is_common_area: boolean;
    renter_name: string;
    reported_at: string;
    started_at: string | null;
    resolved_at: string | null;
    created_at: string;
    updated_at: string;
    expense: {
        id: number;
        amount: number | null;
        has_amount: boolean;
        status: string;
        payment_status: string;
        is_paid: boolean;
        paid_at: string | null;
        status_label: string;
        is_pending_amount: boolean;
        is_ready: boolean;
        is_recorded: boolean;
    } | null;
    attachments: AttachmentItem[];
    notes: NoteItem[];
    activities: ActivityItem[];
}

const props = withDefaults(defineProps<{
    isOpen: boolean;
    maintenanceId: number | null;
    currency?: string;
}>(), {
    currency: 'ج.م',
});

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'updated'): void;
}>();

// Loading and active state
const isLoading = ref(false);
const error = ref<string | null>(null);
const details = ref<MaintenanceDetails | null>(null);
const activeTab = ref<'overview' | 'attachments' | 'notes' | 'history'>('overview');

// Status transition state
const isTransitioning = ref(false);

// Note submission state
const newNote = ref('');
const isSubmittingNote = ref(false);
const noteError = ref<string | null>(null);

// Attachments upload state
const fileInput = ref<HTMLInputElement | null>(null);
const pendingFiles = ref<File[]>([]);
const pendingPreviews = ref<Array<{ file: File; url?: string; isImage: boolean; isPdf: boolean }>>([]);
const isUploadingFiles = ref(false);
const uploadError = ref<string | null>(null);

// Image lightbox preview modal
const activePreviewImage = ref<{ url: string; title: string } | null>(null);

// Delete confirmation modal
const attachmentToDelete = ref<AttachmentItem | null>(null);
const isDeletingAttachment = ref(false);

// Load maintenance details from JSON API
const loadDetails = async () => {
    if (!props.maintenanceId) {
        details.value = null;
        return;
    }

    isLoading.value = true;
    error.value = null;

    try {
        const response = await fetch(`/maintenance/${props.maintenanceId}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error(`فشل تحميل تفاصيل طلب الصيانة (${response.status})`);
        }

        const data = await response.json();
        details.value = data;
    } catch (err: any) {
        error.value = err?.message || 'حدث خطأ أثناء تحميل تفاصيل طلب الصيانة.';
    } finally {
        isLoading.value = false;
    }
};

watch(() => [props.isOpen, props.maintenanceId], ([isOpen, id]) => {
    if (isOpen && id) {
        activeTab.value = 'overview';
        pendingFiles.value = [];
        pendingPreviews.value = [];
        uploadError.value = null;
        noteError.value = null;
        newNote.value = '';
        loadDetails();
    }
}, { immediate: true });

// Handle status transitions
const handleStatusTransition = (newStatus: 'in_progress' | 'completed' | 'pending') => {
    if (!details.value || isTransitioning.value) return;

    if (newStatus === 'completed' && !details.value.can_be_completed) {
        alert('لا يمكن إنهاء طلب الصيانة قبل تسجيل التكلفة من صفحة المصروفات.');
        return;
    }

    isTransitioning.value = true;

    router.patch(route('maintenance.update-status', details.value.id), {
        status: newStatus,
    }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            loadDetails();
            emit('updated');
        },
        onError: (errs) => {
            alert(errs.status || 'حدث خطأ أثناء تغيير حالة الطلب.');
        },
        onFinish: () => {
            isTransitioning.value = false;
        },
    });
};

// Handle expense payment recording/reversing
const isRecordingPayment = ref(false);

const handleRecordExpensePayment = () => {
    if (!details.value?.expense?.id || isRecordingPayment.value) return;

    if (!confirm('هل أنت متأكد من تسجيل سداد تكلفة الصيانة؟')) {
        return;
    }

    isRecordingPayment.value = true;
    router.post(route('expenses.payment.record', details.value.expense.id), {}, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            loadDetails();
            emit('updated');
        },
        onError: (errs: any) => {
            alert(errs?.amount || 'حدث خطأ أثناء تسجيل السداد.');
        },
        onFinish: () => {
            isRecordingPayment.value = false;
        },
    });
};

const handleReverseExpensePayment = () => {
    if (!details.value?.expense?.id || isRecordingPayment.value) return;

    if (!confirm('هل تريد إلغاء تسجيل سداد هذا المصروف وإعادته كغير مدفوع؟')) {
        return;
    }

    isRecordingPayment.value = true;
    router.post(route('expenses.payment.reverse', details.value.expense.id), {}, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            loadDetails();
            emit('updated');
        },
        onError: () => {
            alert('حدث خطأ أثناء إلغاء السداد.');
        },
        onFinish: () => {
            isRecordingPayment.value = false;
        },
    });
};

// Handle notes submission
const submitNote = async () => {
    if (!details.value || !newNote.value.trim() || isSubmittingNote.value) return;

    noteError.value = null;
    isSubmittingNote.value = true;

    try {
        const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content;
        const response = await fetch(`/maintenance/${details.value.id}/notes`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken || '',
            },
            body: JSON.stringify({
                note: newNote.value.trim(),
            }),
        });

        const resData = await response.json();

        if (!response.ok) {
            throw new Error(resData?.message || resData?.errors?.note?.[0] || 'فشل حفظ الملاحظة.');
        }

        newNote.value = '';
        if (resData.note && details.value) {
            details.value.notes.unshift(resData.note);
        } else {
            await loadDetails();
        }
        emit('updated');
    } catch (err: any) {
        noteError.value = err?.message || 'حدث خطأ أثناء حفظ الملاحظة.';
    } finally {
        isSubmittingNote.value = false;
    }
};

// Handle file selection
const handleFileSelect = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (!target.files || target.files.length === 0) return;

    uploadError.value = null;
    const selected = Array.from(target.files);

    // Validate count
    if (pendingFiles.value.length + selected.length > 10) {
        uploadError.value = 'الحد الأقصى للمرفقات هو 10 ملفات في المرة الواحدة.';
        return;
    }

    // Validate size and mimes
    const allowedMimes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'application/pdf'];
    for (const f of selected) {
        if (!allowedMimes.includes(f.type)) {
            uploadError.value = `الملف "${f.name}" غير مدعوم. الصيغ المسموح بها هي JPG, PNG, WEBP, PDF فقط.`;
            return;
        }
        if (f.size > 10 * 1024 * 1024) {
            uploadError.value = `حجم الملف "${f.name}" يتجاوز الحد الأقصى المسموح به (10 ميجابايت).`;
            return;
        }
    }

    selected.forEach(file => {
        pendingFiles.value.push(file);
        const isImage = file.type.startsWith('image/');
        const isPdf = file.type === 'application/pdf';
        const url = isImage ? URL.createObjectURL(file) : undefined;
        pendingPreviews.value.push({ file, url, isImage, isPdf });
    });

    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

const removePendingFile = (index: number) => {
    const item = pendingPreviews.value[index];
    if (item?.url) {
        URL.revokeObjectURL(item.url);
    }
    pendingPreviews.value.splice(index, 1);
    pendingFiles.value.splice(index, 1);
};

// Upload pending attachments
const uploadPendingAttachments = async () => {
    if (!details.value || pendingFiles.value.length === 0 || isUploadingFiles.value) return;

    uploadError.value = null;
    isUploadingFiles.value = true;

    const formData = new FormData();
    pendingFiles.value.forEach(f => {
        formData.append('files[]', f);
    });

    try {
        const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content;
        const response = await fetch(`/maintenance/${details.value.id}/attachments`, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken || '',
            },
            body: formData,
        });

        const resData = await response.json();

        if (!response.ok) {
            throw new Error(resData?.message || resData?.errors?.files?.[0] || 'فشل رفع المرفقات.');
        }

        // Cleanup preview URLs
        pendingPreviews.value.forEach(p => {
            if (p.url) URL.revokeObjectURL(p.url);
        });
        pendingFiles.value = [];
        pendingPreviews.value = [];

        await loadDetails();
        emit('updated');
    } catch (err: any) {
        uploadError.value = err?.message || 'حدث خطأ أثناء رفع المرفقات.';
    } finally {
        isUploadingFiles.value = false;
    }
};

// Handle attachment deletion
const confirmDeleteAttachment = (att: AttachmentItem) => {
    attachmentToDelete.value = att;
};

const executeDeleteAttachment = async () => {
    if (!details.value || !attachmentToDelete.value || isDeletingAttachment.value) return;

    isDeletingAttachment.value = true;
    const attId = attachmentToDelete.value.id;

    try {
        const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content;
        const response = await fetch(`/maintenance/${details.value.id}/attachments/${attId}`, {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken || '',
            },
        });

        if (!response.ok) {
            const resData = await response.json().catch(() => ({}));
            throw new Error(resData?.message || 'فشل حذف المرفق.');
        }

        details.value.attachments = details.value.attachments.filter(a => a.id !== attId);
        attachmentToDelete.value = null;
        emit('updated');
    } catch (err: any) {
        alert(err?.message || 'حدث خطأ أثناء حذف المرفق.');
    } finally {
        isDeletingAttachment.value = false;
    }
};

const close = () => {
    emit('close');
};
</script>

<template>
    <!-- Slide-over Drawer Wrapper -->
    <div
        v-if="isOpen"
        class="fixed inset-0 z-50 overflow-hidden"
        dir="rtl"
    >
        <!-- Backdrop -->
        <div
            @click="close"
            class="fixed inset-0 bg-[#163E3E]/50 backdrop-blur-xs transition-opacity duration-300"
        ></div>

        <section class="absolute inset-y-0 right-0 max-w-full flex pl-0 sm:pl-10">
            <div class="w-screen max-w-2xl bg-white shadow-2xl flex flex-col border-l border-[#D6E0E7] transform transition duration-300 ease-in-out">
                
                <!-- 1. Drawer Header -->
                <div class="p-5 sm:p-6 pb-4 border-b border-[#E8EFF4] bg-white sticky top-0 z-20">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-start gap-3 min-w-0">
                            <div class="w-12 h-12 rounded-2xl bg-[#E8F0F0] text-[#163E3E] flex items-center justify-center shrink-0 shadow-2xs">
                                <Wrench class="w-6 h-6 stroke-[2]" />
                            </div>
                            <div class="min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <h3 class="text-lg font-bold text-[#163E3E] truncate">
                                        {{ details?.title || 'تفاصيل طلب الصيانة' }}
                                    </h3>
                                    <span v-if="details" class="text-xs font-mono font-bold text-[#163E3E] bg-[#E8F0F0] px-2.5 py-0.5 rounded-lg border border-[#163E3E]/20">
                                        #{{ details.id }}
                                    </span>
                                </div>
                                <p v-if="details" class="text-xs text-[#5B6770] font-medium mt-1 truncate flex items-center gap-1.5">
                                    <Building2 class="w-3.5 h-3.5 text-[#5B6770]" />
                                    <span>{{ details.property_name }}</span>
                                    <span>/</span>
                                    <span :class="details.is_common_area ? 'text-blue-800 font-bold' : 'text-[#163E3E]'">
                                        {{ details.unit_name }}
                                    </span>
                                </p>
                            </div>
                        </div>

                        <button
                            @click="close"
                            type="button"
                            class="p-2 text-[#5B6770] hover:text-[#163E3E] hover:bg-[#F8F4EA] rounded-xl transition cursor-pointer shrink-0"
                            title="إغلاق"
                        >
                            <X class="w-5 h-5" />
                        </button>
                    </div>

                    <!-- Type and Status Row -->
                    <div v-if="details" class="mt-4 flex items-center justify-between gap-2 p-3 rounded-xl bg-[#F8FAFC] border border-[#E8EFF4]">
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-[#5B6770] font-medium">حالة الصيانة:</span>
                            <span
                                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold border"
                                :class="{
                                    'bg-rose-50 text-rose-700 border-rose-200': details.is_pending,
                                    'bg-amber-50 text-amber-800 border-amber-200': details.is_in_progress,
                                    'bg-emerald-50 text-emerald-800 border-emerald-200': details.is_completed,
                                }"
                            >
                                <span
                                    class="w-2 h-2 rounded-full"
                                    :class="{
                                        'bg-rose-500': details.is_pending,
                                        'bg-amber-500': details.is_in_progress,
                                        'bg-emerald-500': details.is_completed,
                                    }"
                                ></span>
                                <span>{{ details.status_label }}</span>
                            </span>
                        </div>

                        <!-- Date reported -->
                        <div class="flex items-center gap-1.5 text-xs text-[#5B6770]">
                            <Calendar class="w-3.5 h-3.5" />
                            <span>تاريخ الإبلاغ:</span>
                            <span class="font-mono font-bold text-[#163E3E]">{{ details.reported_at }}</span>
                        </div>
                    </div>

                    <!-- Tabs Navigation -->
                    <div class="mt-4 flex items-center gap-2 border-b border-[#E8EFF4] -mb-4 overflow-x-auto pb-1 sm:pb-0">
                        <button
                            @click="activeTab = 'overview'"
                            type="button"
                            class="px-4 py-2 text-xs font-bold border-b-2 transition flex items-center gap-1.5 cursor-pointer shrink-0"
                            :class="activeTab === 'overview'
                                ? 'border-[#163E3E] text-[#163E3E]'
                                : 'border-transparent text-[#5B6770] hover:text-[#163E3E]'"
                        >
                            <FileText class="w-4 h-4" />
                            <span>نظرة عامة والتكلفة</span>
                        </button>

                        <button
                            @click="activeTab = 'attachments'"
                            type="button"
                            class="px-4 py-2 text-xs font-bold border-b-2 transition flex items-center gap-1.5 cursor-pointer shrink-0"
                            :class="activeTab === 'attachments'
                                ? 'border-[#163E3E] text-[#163E3E]'
                                : 'border-transparent text-[#5B6770] hover:text-[#163E3E]'"
                        >
                            <Paperclip class="w-4 h-4" />
                            <span>المرفقات والصور</span>
                            <span
                                v-if="details?.attachments?.length"
                                class="px-2 py-0.5 rounded-full text-[10px] font-mono font-bold bg-[#E8F0F0] text-[#163E3E]"
                            >
                                {{ details.attachments.length }}
                            </span>
                        </button>

                        <button
                            @click="activeTab = 'notes'"
                            type="button"
                            class="px-4 py-2 text-xs font-bold border-b-2 transition flex items-center gap-1.5 cursor-pointer shrink-0"
                            :class="activeTab === 'notes'
                                ? 'border-[#163E3E] text-[#163E3E]'
                                : 'border-transparent text-[#5B6770] hover:text-[#163E3E]'"
                        >
                            <MessageSquare class="w-4 h-4" />
                            <span>الملاحظات</span>
                            <span
                                v-if="details?.notes?.length"
                                class="px-2 py-0.5 rounded-full text-[10px] font-mono font-bold bg-[#E8F0F0] text-[#163E3E]"
                            >
                                {{ details.notes.length }}
                            </span>
                        </button>

                        <button
                            @click="activeTab = 'history'"
                            type="button"
                            class="px-4 py-2 text-xs font-bold border-b-2 transition flex items-center gap-1.5 cursor-pointer shrink-0"
                            :class="activeTab === 'history'
                                ? 'border-[#163E3E] text-[#163E3E]'
                                : 'border-transparent text-[#5B6770] hover:text-[#163E3E]'"
                        >
                            <History class="w-4 h-4" />
                            <span>سجل العمليات</span>
                        </button>
                    </div>
                </div>

                <!-- 2. Scrollable Body Content -->
                <div class="overflow-y-auto p-5 sm:p-6 space-y-5 flex-1">
                    
                    <!-- Loading State -->
                    <div v-if="isLoading" class="py-16 text-center space-y-3">
                        <Loader2 class="w-8 h-8 text-[#163E3E] animate-spin mx-auto" />
                        <p class="text-xs text-[#5B6770] font-medium">جاري تحميل بيانات الصيانة والمرفقات...</p>
                    </div>

                    <!-- Error State -->
                    <div v-else-if="error" class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-xs text-rose-800 space-y-2">
                        <div class="flex items-center gap-2 font-bold">
                            <AlertCircle class="w-4 h-4 text-rose-600" />
                            <span>تعذر تحميل البيانات</span>
                        </div>
                        <p>{{ error }}</p>
                        <button
                            @click="loadDetails"
                            type="button"
                            class="h-8 px-3 rounded-lg bg-rose-600 hover:bg-rose-700 text-white font-bold transition text-xs cursor-pointer"
                        >
                            إعادة المحاولة
                        </button>
                    </div>

                    <!-- Details Loaded -->
                    <div v-else-if="details" class="space-y-6">

                        <!-- TAB 1: OVERVIEW & COST -->
                        <div v-if="activeTab === 'overview'" class="space-y-5">
                            
                            <!-- الوصف الأساسي للبلاغ -->
                            <div class="p-4 rounded-2xl bg-[#F8FAFC] border border-[#D6E0E7] space-y-2">
                                <span class="text-xs font-bold text-[#163E3E] block">الوصف التفصيلي للبلاغ:</span>
                                <p class="text-xs text-[#5B6770] leading-relaxed whitespace-pre-wrap">
                                    {{ details.description || 'لم يتم تسجيل وصف تفصيلي عند إنشاء الطلب.' }}
                                </p>
                            </div>

                            <!-- البيانات الإدارية وتواريخ دورة الحياة -->
                            <div class="grid grid-cols-2 gap-3">
                                <div class="p-3.5 rounded-xl border border-[#D6E0E7] bg-white text-xs space-y-1">
                                    <span class="text-[#5B6770] block text-[11px]">مقدم البلاغ:</span>
                                    <span class="font-bold text-[#163E3E] flex items-center gap-1.5">
                                        <User class="w-3.5 h-3.5 text-[#5B6770]" />
                                        <span>{{ details.renter_name }}</span>
                                    </span>
                                </div>

                                <div class="p-3.5 rounded-xl border border-[#D6E0E7] bg-white text-xs space-y-1">
                                    <span class="text-[#5B6770] block text-[11px]">الموقع / النطاق:</span>
                                    <span class="font-bold text-[#163E3E] flex items-center gap-1.5">
                                        <component :is="details.is_common_area ? Building2 : Home" class="w-3.5 h-3.5 text-[#5B6770]" />
                                        <span>{{ details.unit_name }}</span>
                                    </span>
                                </div>

                                <div class="p-3.5 rounded-xl border border-[#D6E0E7] bg-white text-xs space-y-1">
                                    <span class="text-[#5B6770] block text-[11px]">تاريخ بدء الإصلاح:</span>
                                    <span class="font-mono font-bold" :class="details.started_at ? 'text-amber-800' : 'text-slate-400'">
                                        {{ details.started_at || 'لم يبدأ بعد' }}
                                    </span>
                                </div>

                                <div class="p-3.5 rounded-xl border border-[#D6E0E7] bg-white text-xs space-y-1">
                                    <span class="text-[#5B6770] block text-[11px]">تاريخ الإنجاز والإغلاق:</span>
                                    <span class="font-mono font-bold" :class="details.resolved_at ? 'text-emerald-800' : 'text-slate-400'">
                                        {{ details.resolved_at || 'قيد المعالجة' }}
                                    </span>
                                </div>
                            </div>

                            <!-- الصندوق المالي: المصروف المرتبط -->
                            <div
                                class="p-5 rounded-2xl border text-xs space-y-4"
                                :class="details.expense?.has_amount
                                    ? 'bg-emerald-50/60 border-emerald-200'
                                    : 'bg-amber-50/60 border-amber-200'"
                            >
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <Receipt class="w-5 h-5" :class="details.expense?.has_amount ? 'text-emerald-700' : 'text-amber-700'" />
                                        <div>
                                            <h4 class="font-bold text-sm text-[#163E3E]">التكلفة والمصروف المرتبط</h4>
                                            <p class="text-[11px] text-[#5B6770]">رقم قيد المصروف: #{{ details.expense?.id || '—' }}</p>
                                        </div>
                                    </div>

                                    <span
                                        class="font-bold text-xs px-3 py-1 rounded-full border"
                                        :class="details.expense?.has_amount
                                            ? (details.expense.is_paid ? 'bg-emerald-100 text-emerald-800 border-emerald-300' : 'bg-blue-50 text-blue-800 border-blue-200')
                                            : 'bg-amber-100 text-amber-900 border-amber-300'"
                                    >
                                        {{ details.expense?.status_label || 'بانتظار تسجيل التكلفة' }}
                                    </span>
                                </div>

                                <!-- المبلغ المسجل أو تنبيه التسجيل -->
                                <div v-if="details.expense?.has_amount" class="p-4 rounded-xl bg-white border border-emerald-200/80 space-y-3">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <span class="text-[#5B6770] text-[11px] block">تكلفة الصيانة المسجلة:</span>
                                            <span class="text-xl font-bold font-mono text-emerald-800">
                                                {{ details.expense?.amount?.toLocaleString('en-US') }} {{ currency }}
                                            </span>
                                        </div>

                                        <div class="text-left space-y-1">
                                            <span class="text-[11px] block text-[#5B6770]">حالة السداد:</span>
                                            <span
                                                class="text-xs font-bold px-2.5 py-0.5 rounded-md inline-block"
                                                :class="details.expense?.is_paid ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                                            >
                                                {{ details.expense?.is_paid ? 'مدفوع' : 'غير مدفوع بعد' }}
                                            </span>
                                        </div>
                                    </div>

                                    <!-- إجراء السداد المالي -->
                                    <div class="pt-3 border-t border-slate-100 flex items-center justify-between flex-wrap gap-2">
                                        <div v-if="details.expense?.is_paid" class="text-xs text-emerald-700 flex items-center gap-1.5 font-medium">
                                            <CheckCircle2 class="w-4 h-4 text-emerald-600 shrink-0" />
                                            <span>تم السداد بتاريخ {{ details.expense.paid_at || 'مسبقاً' }}</span>
                                        </div>
                                        <div v-else class="text-xs text-[#5B6770]">
                                            المصروف معتمد مالياً ولكن لم يُسجل صرفه للمنفذ/الفني بعد.
                                        </div>

                                        <div class="flex items-center gap-2">
                                            <button
                                                v-if="!details.expense?.is_paid"
                                                @click="handleRecordExpensePayment"
                                                :disabled="isRecordingPayment"
                                                type="button"
                                                class="h-8 px-3 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition flex items-center gap-1.5 shadow-2xs cursor-pointer disabled:opacity-50"
                                            >
                                                <Loader2 v-if="isRecordingPayment" class="w-3.5 h-3.5 animate-spin" />
                                                <CreditCard v-else class="w-3.5 h-3.5" />
                                                <span>تسجيل سداد المصروف</span>
                                            </button>
                                            <button
                                                v-else
                                                @click="handleReverseExpensePayment"
                                                :disabled="isRecordingPayment"
                                                type="button"
                                                class="text-xs text-rose-600 hover:text-rose-800 hover:underline cursor-pointer disabled:opacity-50 font-medium"
                                            >
                                                <span>إلغاء السداد</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div v-else class="p-4 rounded-xl bg-white border border-amber-200/80 space-y-2">
                                    <div class="flex items-center gap-2 text-amber-900 font-bold">
                                        <AlertCircle class="w-4 h-4 text-amber-700 shrink-0" />
                                        <span>لم تُسجل التكلفة الفعلية بعد</span>
                                    </div>
                                    <p class="text-[11px] text-[#5B6770] leading-relaxed">
                                        تم إنشاء بند المصروف تلقائياً بمبلغ فارغ لحفظ سلامة الحسابات. يرجى التوجه لصفحة المصروفات وإدخال قيمة فاتورة الصيانة حتى تتمكن من إنهاء الطلب.
                                    </p>
                                </div>

                                <div class="flex items-center justify-between pt-2">
                                    <span class="text-[11px] text-[#5B6770]">يتحمل المصروف مالك العقار افتراضياً.</span>
                                    <Link
                                        :href="route('expenses.index')"
                                        class="h-9 px-4 rounded-xl bg-[#163E3E] hover:bg-[#102F2F] text-white text-xs font-bold transition flex items-center gap-1.5 shadow-2xs"
                                    >
                                        <span>فتح صفحة المصروفات</span>
                                        <ExternalLink class="w-3.5 h-3.5" />
                                    </Link>
                                </div>
                            </div>

                            <!-- الإجراءات السريعة لحالة الطلب -->
                            <div class="p-4 rounded-2xl bg-[#F8FAFC] border border-[#D6E0E7] space-y-3">
                                <span class="text-xs font-bold text-[#163E3E] block">الإجراء التشغيلي:</span>
                                <div class="flex items-center gap-3">
                                    <!-- Start repairs -->
                                    <button
                                        v-if="details.is_pending"
                                        @click="handleStatusTransition('in_progress')"
                                        :disabled="isTransitioning"
                                        type="button"
                                        class="h-10 px-5 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold transition flex items-center gap-2 cursor-pointer shadow-xs disabled:opacity-50"
                                    >
                                        <Loader2 v-if="isTransitioning" class="w-4 h-4 animate-spin" />
                                        <Wrench v-else class="w-4 h-4" />
                                        <span>بدء أعمال الصيانة والإصلاح</span>
                                    </button>

                                    <!-- Complete repairs -->
                                    <button
                                        v-else-if="details.is_in_progress"
                                        @click="handleStatusTransition('completed')"
                                        :disabled="!details.can_be_completed || isTransitioning"
                                        type="button"
                                        class="h-10 px-5 rounded-xl text-xs font-bold transition flex items-center gap-2 shadow-xs"
                                        :class="details.can_be_completed
                                            ? 'bg-emerald-700 hover:bg-emerald-800 text-white cursor-pointer'
                                            : 'bg-slate-100 text-slate-400 border border-slate-200 cursor-not-allowed'"
                                    >
                                        <Loader2 v-if="isTransitioning" class="w-4 h-4 animate-spin" />
                                        <Check v-else class="w-4 h-4" />
                                        <span>إنهاء وإغلاق طلب الصيانة</span>
                                    </button>

                                    <!-- Completed state info -->
                                    <div v-else class="flex items-center gap-2 text-emerald-800 font-bold text-xs">
                                        <CheckCircle2 class="w-5 h-5 text-emerald-600" />
                                        <span>تم إنجاز الصيانة وحفظ بند المصروف بنجاح</span>
                                    </div>
                                </div>

                                <p v-if="details.is_in_progress && !details.can_be_completed" class="text-[11px] text-amber-800">
                                    * ملاحظة: زر الإنهاء معطل حتى يتم إدخال مبلغ الصيانة في بند المصروف المرتبط.
                                </p>
                            </div>
                        </div>

                        <!-- TAB 2: ATTACHMENTS & PHOTOS -->
                        <div v-else-if="activeTab === 'attachments'" class="space-y-6">
                            
                            <!-- Uploader Area -->
                            <div class="p-5 rounded-2xl border-2 border-dashed border-[#D6E0E7] bg-[#F8FAFC] text-center space-y-3">
                                <div class="w-12 h-12 rounded-2xl bg-white shadow-2xs border border-[#D6E0E7] flex items-center justify-center mx-auto text-[#163E3E]">
                                    <UploadCloud class="w-6 h-6 stroke-[1.75]" />
                                </div>

                                <div>
                                    <h4 class="text-xs font-bold text-[#163E3E]">إرفاق صور العطل أو فواتير الشراء</h4>
                                    <p class="text-[11px] text-[#5B6770] mt-1">
                                        الصيغ المدعومة: JPG, PNG, WEBP, PDF (الحد الأقصى: 10 ميجابايت للملف الواحد)
                                    </p>
                                </div>

                                <input
                                    ref="fileInput"
                                    type="file"
                                    multiple
                                    accept="image/jpeg,image/png,image/webp,application/pdf"
                                    @change="handleFileSelect"
                                    class="hidden"
                                />

                                <div class="pt-1">
                                    <button
                                        @click="fileInput?.click()"
                                        type="button"
                                        class="h-9 px-4 rounded-xl border border-[#D6E0E7] bg-white hover:bg-[#F8FAFC] text-xs font-bold text-[#163E3E] transition inline-flex items-center gap-1.5 shadow-2xs cursor-pointer"
                                    >
                                        <Plus class="w-4 h-4" />
                                        <span>اختيار ملفات من الجهاز</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Upload Error Alert -->
                            <div v-if="uploadError" class="p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-800 flex items-center gap-2">
                                <AlertCircle class="w-4 h-4 text-rose-600 shrink-0" />
                                <span>{{ uploadError }}</span>
                            </div>

                            <!-- Pre-upload Files List -->
                            <div v-if="pendingPreviews.length > 0" class="p-4 rounded-2xl bg-white border border-amber-200 space-y-3">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="font-bold text-amber-950">ملفات جاهزة للرفع ({{ pendingPreviews.length }}):</span>
                                    <button
                                        @click="uploadPendingAttachments"
                                        :disabled="isUploadingFiles"
                                        type="button"
                                        class="h-8 px-4 rounded-xl bg-[#163E3E] hover:bg-[#102F2F] text-white font-bold text-xs transition flex items-center gap-1.5 cursor-pointer shadow-xs disabled:opacity-60"
                                    >
                                        <Loader2 v-if="isUploadingFiles" class="w-3.5 h-3.5 animate-spin" />
                                        <UploadCloud v-else class="w-3.5 h-3.5" />
                                        <span>{{ isUploadingFiles ? 'جاري الرفع...' : 'تأكيد رفع الملفات' }}</span>
                                    </button>
                                </div>

                                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5">
                                    <div
                                        v-for="(item, idx) in pendingPreviews"
                                        :key="idx"
                                        class="relative rounded-xl border border-[#D6E0E7] p-2 bg-[#F8FAFC] flex flex-col items-center gap-2 group"
                                    >
                                        <button
                                            @click="removePendingFile(idx)"
                                            type="button"
                                            class="absolute top-1 left-1 p-1 bg-white/90 hover:bg-rose-50 text-rose-600 rounded-md shadow-xs transition cursor-pointer"
                                            title="إلغاء الملف"
                                        >
                                            <X class="w-3.5 h-3.5" />
                                        </button>

                                        <img
                                            v-if="item.isImage && item.url"
                                            :src="item.url"
                                            class="w-full h-20 object-cover rounded-lg"
                                        />
                                        <div
                                            v-else
                                            class="w-full h-20 rounded-lg bg-red-50 text-red-600 flex items-center justify-center font-bold text-xs"
                                        >
                                            PDF
                                        </div>

                                        <span class="text-[11px] text-[#163E3E] font-medium truncate w-full text-center">
                                            {{ item.file.name }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Existing Attachments Gallery -->
                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <h4 class="text-xs font-bold text-[#163E3E]">
                                        المرفقات المحفوظة ({{ details.attachments.length }})
                                    </h4>
                                </div>

                                <div v-if="details.attachments.length === 0" class="py-10 text-center text-xs text-[#5B6770] bg-[#F8FAFC] rounded-2xl border border-[#D6E0E7]">
                                    لا توجد صور أو مستندات مرفقة لهذا الطلب حتى الآن.
                                </div>

                                <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div
                                        v-for="att in details.attachments"
                                        :key="att.id"
                                        class="rounded-2xl border border-[#D6E0E7] bg-white p-3.5 shadow-2xs hover:shadow-xs transition flex flex-col justify-between gap-3"
                                    >
                                        <!-- Thumbnail or Document Icon -->
                                        <div class="flex items-start gap-3">
                                            <div
                                                v-if="att.is_image"
                                                @click="activePreviewImage = { url: att.preview_url, title: att.original_filename }"
                                                class="w-16 h-16 rounded-xl overflow-hidden bg-slate-100 shrink-0 border border-[#D6E0E7] relative cursor-pointer group"
                                            >
                                                <img :src="att.preview_url" class="w-full h-full object-cover group-hover:scale-105 transition duration-300" />
                                                <div class="absolute inset-0 bg-black/30 opacity-0 group-hover:opacity-100 transition flex items-center justify-center text-white">
                                                    <Eye class="w-4 h-4" />
                                                </div>
                                            </div>
                                            <div
                                                v-else
                                                class="w-16 h-16 rounded-xl bg-red-50 text-red-600 border border-red-200 flex flex-col items-center justify-center shrink-0"
                                            >
                                                <FileText class="w-6 h-6 stroke-[2]" />
                                                <span class="text-[10px] font-bold font-mono">PDF</span>
                                            </div>

                                            <div class="min-w-0 flex-1">
                                                <h5 class="text-xs font-bold text-[#163E3E] truncate" :title="att.original_filename">
                                                    {{ att.original_filename }}
                                                </h5>
                                                <p class="text-[11px] text-[#5B6770] font-mono mt-0.5">
                                                    {{ att.formatted_size }}
                                                </p>
                                                <p class="text-[10px] text-[#5B6770] mt-1">
                                                    بواسطة {{ att.uploaded_by_name }} • {{ att.created_at }}
                                                </p>
                                            </div>
                                        </div>

                                        <!-- Action Buttons -->
                                        <div class="pt-2 border-t border-[#E8EFF4] flex items-center justify-between gap-2">
                                            <div class="flex items-center gap-1.5">
                                                <a
                                                    v-if="att.is_image"
                                                    @click.prevent="activePreviewImage = { url: att.preview_url, title: att.original_filename }"
                                                    href="#"
                                                    class="h-7 px-2.5 rounded-lg border border-[#D6E0E7] text-[11px] font-bold text-[#163E3E] hover:bg-[#F8FAFC] transition flex items-center gap-1 cursor-pointer"
                                                >
                                                    <Eye class="w-3.5 h-3.5" />
                                                    <span>معاينة</span>
                                                </a>
                                                <a
                                                    v-else
                                                    :href="att.preview_url"
                                                    target="_blank"
                                                    class="h-7 px-2.5 rounded-lg border border-[#D6E0E7] text-[11px] font-bold text-[#163E3E] hover:bg-[#F8FAFC] transition flex items-center gap-1"
                                                >
                                                    <Eye class="w-3.5 h-3.5" />
                                                    <span>فتح</span>
                                                </a>

                                                <a
                                                    :href="att.download_url"
                                                    class="h-7 px-2.5 rounded-lg border border-[#D6E0E7] text-[11px] font-bold text-[#163E3E] hover:bg-[#F8FAFC] transition flex items-center gap-1"
                                                >
                                                    <Download class="w-3.5 h-3.5" />
                                                    <span>تحميل</span>
                                                </a>
                                            </div>

                                            <button
                                                @click="confirmDeleteAttachment(att)"
                                                type="button"
                                                class="h-7 w-7 rounded-lg text-rose-600 hover:bg-rose-50 transition flex items-center justify-center cursor-pointer"
                                                title="حذف المرفق"
                                            >
                                                <Trash2 class="w-3.5 h-3.5" />
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- TAB 3: PROGRESS NOTES -->
                        <div v-else-if="activeTab === 'notes'" class="space-y-5">
                            
                            <!-- Form to Add Note -->
                            <div class="p-4 rounded-2xl bg-[#F8FAFC] border border-[#D6E0E7] space-y-3">
                                <div>
                                    <label class="block text-xs font-bold text-[#163E3E] mb-1">إضافة ملاحظة جديدة لسير العمل</label>
                                    <p class="text-[11px] text-[#5B6770]">
                                        تسجل الملاحظات الإجرائية تقدم العمل والتواصل مع الفنيين دون المساس بالوصف الأصلي للبلاغ.
                                    </p>
                                </div>

                                <textarea
                                    v-model="newNote"
                                    rows="3"
                                    placeholder="اكتب التحديث الإجرائي هنا (مثال: تم الاتفاق مع الفني على المعاينة غداً في الساعة العاشرة صباحاً)..."
                                    class="w-full p-3 rounded-xl border border-[#D6E0E7] bg-white text-xs text-[#163E3E] focus:border-[#163E3E] focus:ring-1 focus:ring-[#163E3E] transition"
                                ></textarea>

                                <p v-if="noteError" class="text-xs text-rose-600">{{ noteError }}</p>

                                <div class="flex justify-end">
                                    <button
                                        @click="submitNote"
                                        :disabled="!newNote.trim() || isSubmittingNote"
                                        type="button"
                                        class="h-9 px-4 rounded-xl bg-[#163E3E] hover:bg-[#102F2F] text-white text-xs font-bold transition flex items-center gap-1.5 cursor-pointer shadow-xs disabled:opacity-50"
                                    >
                                        <Loader2 v-if="isSubmittingNote" class="w-3.5 h-3.5 animate-spin" />
                                        <Plus v-else class="w-3.5 h-3.5" />
                                        <span>{{ isSubmittingNote ? 'جاري الحفظ...' : 'إضافة الملاحظة' }}</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Notes List -->
                            <div class="space-y-3">
                                <h4 class="text-xs font-bold text-[#163E3E]">
                                    سجل الملاحظات ({{ details.notes.length }})
                                </h4>

                                <div v-if="details.notes.length === 0" class="py-10 text-center text-xs text-[#5B6770] bg-[#F8FAFC] rounded-2xl border border-[#D6E0E7]">
                                    لا توجد ملاحظات مسجلة بعد. استخدم النموذج أعلاه لتدوين أحدث المستجدات.
                                </div>

                                <div
                                    v-for="n in details.notes"
                                    :key="n.id"
                                    class="p-4 rounded-2xl border border-[#D6E0E7] bg-white shadow-2xs space-y-2"
                                >
                                    <div class="flex items-center justify-between text-xs text-[#5B6770] pb-2 border-b border-[#E8EFF4]">
                                        <span class="font-bold text-[#163E3E] flex items-center gap-1.5">
                                            <User class="w-3.5 h-3.5 text-[#5B6770]" />
                                            <span>{{ n.created_by_name }}</span>
                                        </span>
                                        <span class="font-mono text-[11px]">{{ n.created_at }}</span>
                                    </div>
                                    <p class="text-xs text-[#163E3E] leading-relaxed whitespace-pre-wrap">
                                        {{ n.note }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- TAB 4: ACTIVITY HISTORY TIMELINE -->
                        <div v-else-if="activeTab === 'history'" class="space-y-4">
                            <h4 class="text-xs font-bold text-[#163E3E]">
                                المخطط الزمني وسجل العمليات
                            </h4>

                            <div v-if="details.activities.length === 0" class="py-10 text-center text-xs text-[#5B6770] bg-[#F8FAFC] rounded-2xl border border-[#D6E0E7]">
                                لا توجد حركات مسجلة في الأرشيف حتى الآن.
                            </div>

                            <div v-else class="relative pr-6 space-y-6 before:absolute before:right-2.5 before:top-2 before:bottom-2 before:w-0.5 before:bg-[#D6E0E7]">
                                <div
                                    v-for="act in details.activities"
                                    :key="act.id"
                                    class="relative flex items-start gap-3"
                                >
                                    <!-- Node marker -->
                                    <span class="absolute -right-6 top-1 w-5 h-5 rounded-full bg-white border-2 border-[#163E3E] flex items-center justify-center shrink-0">
                                        <span class="w-1.5 h-1.5 rounded-full bg-[#163E3E]"></span>
                                    </span>

                                    <div class="flex-1 p-3.5 rounded-xl border border-[#D6E0E7] bg-white text-xs space-y-1 shadow-2xs">
                                        <div class="flex items-center justify-between text-[#5B6770] text-[11px]">
                                            <span class="font-bold text-[#163E3E]">{{ act.causer_name }}</span>
                                            <span class="font-mono">{{ act.created_at }}</span>
                                        </div>
                                        <p class="font-medium text-[#163E3E] leading-snug">
                                            {{ act.description }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- 3. Drawer Footer Actions -->
                <div class="p-4 sm:p-5 border-t border-[#E8EFF4] bg-[#F8FAFC] flex items-center justify-between gap-3">
                    <span class="text-xs text-[#5B6770]">
                        رقم الطلب: #{{ details?.id || '—' }}
                    </span>

                    <button
                        @click="close"
                        type="button"
                        class="h-9 px-5 rounded-xl border border-[#D6E0E7] text-xs font-bold text-[#5B6770] hover:bg-white transition cursor-pointer"
                    >
                        إغلاق
                    </button>
                </div>

            </div>
        </section>

        <!-- Lightbox Image Modal -->
        <div
            v-if="activePreviewImage"
            class="fixed inset-0 z-60 overflow-y-auto flex items-center justify-center p-4"
        >
            <div @click="activePreviewImage = null" class="fixed inset-0 bg-black/80 backdrop-blur-xs"></div>
            <div class="relative max-w-3xl max-h-[90vh] bg-white rounded-3xl overflow-hidden shadow-2xl z-10 flex flex-col">
                <div class="p-4 border-b border-[#D6E0E7] flex items-center justify-between">
                    <span class="text-xs font-bold text-[#163E3E] truncate">{{ activePreviewImage.title }}</span>
                    <button @click="activePreviewImage = null" class="p-1 text-[#5B6770] hover:text-[#163E3E]">
                        <X class="w-5 h-5" />
                    </button>
                </div>
                <div class="p-2 overflow-auto flex items-center justify-center bg-slate-950">
                    <img :src="activePreviewImage.url" class="max-h-[75vh] object-contain rounded-xl" />
                </div>
            </div>
        </div>

        <!-- Delete Attachment Confirm Modal -->
        <div
            v-if="attachmentToDelete"
            class="fixed inset-0 z-60 overflow-y-auto flex items-center justify-center p-4"
        >
            <div @click="attachmentToDelete = null" class="fixed inset-0 bg-[#163E3E]/60 backdrop-blur-xs"></div>
            <div class="relative w-full max-w-sm bg-white rounded-3xl p-6 shadow-2xl border border-[#D6E0E7] z-10 space-y-4">
                <div class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center mx-auto">
                    <Trash2 class="w-6 h-6 stroke-[2]" />
                </div>
                <div class="text-center space-y-1">
                    <h4 class="text-base font-bold text-[#163E3E]">تأكيد حذف المرفق</h4>
                    <p class="text-xs text-[#5B6770]">
                        هل أنت متأكد من حذف الملف "{{ attachmentToDelete.original_filename }}"؟ سيتم مسح الملف نهائياً من وحدة التخزين الآمنة.
                    </p>
                </div>
                <div class="flex items-center justify-center gap-3 pt-2">
                    <button
                        @click="attachmentToDelete = null"
                        type="button"
                        class="h-9 px-4 rounded-xl border border-[#D6E0E7] text-xs font-bold text-[#5B6770] hover:bg-[#F8FAFC]"
                    >
                        إلغاء
                    </button>
                    <button
                        @click="executeDeleteAttachment"
                        :disabled="isDeletingAttachment"
                        type="button"
                        class="h-9 px-4 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold transition flex items-center gap-1.5 cursor-pointer disabled:opacity-60"
                    >
                        <Loader2 v-if="isDeletingAttachment" class="w-3.5 h-3.5 animate-spin" />
                        <span>{{ isDeletingAttachment ? 'جاري الحذف...' : 'نعم، احذف المرفق' }}</span>
                    </button>
                </div>
            </div>
        </div>

    </div>
</template>

