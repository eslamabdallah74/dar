import { router } from '@inertiajs/vue3';
import { useToast } from './composables/useToast';

const toast = useToast();

/**
 * 1. Global Validation Errors:
 * Fires when backend returns validation errors (HTTP 422 or redirect with errors).
 */
router.on('error', (event) => {
    const errors = event.detail.errors;
    if (errors && Object.keys(errors).length > 0) {
        toast.fromErrors(errors);
    }
});

/**
 * 2. Global HTTP Exceptions:
 * Fires when server returns HTTP status >= 400 or non-Inertia response.
 */
router.on('httpException', (event) => {
    // Prevent default raw modal popup
    event.preventDefault();

    const response = (event.detail as any)?.response;
    const status = response?.status;
    let message = 'حدث خطأ في الخادم أثناء معالجة الطلب، يرجى المحاولة لاحقاً.';

    if (status === 403) {
        message = 'غير مصرح لك بإجراء هذه العملية.';
    } else if (status === 404) {
        message = 'السجل المطلوب أو الصفحة غير موجودة.';
    } else if (status === 419) {
        message = 'انتهت صلاحية الجلسة، يرجى إعادة تحميل الصفحة والمحاولة مرة أخرى.';
    } else if (status === 429) {
        message = 'تم تجاوز الحد المسموح من الطلبات، يرجى الانتظار قليلاً.';
    } else if (status >= 500) {
        message = `حدث خطأ غير متوقع في الخادم (رمز ${status})، يرجى مراجعة الدعم الفني.`;
    }

    if (response?.data && typeof response.data === 'object' && response.data.message) {
        message = response.data.message;
    }

    toast.error(message, {
        title: `خطأ في الخادم (${status || 'غير معروف'})`,
        duration: 9000,
    });
});

/**
 * 4. Global Network Errors:
 * Fires on connection drop, offline status, or network timeout.
 */
router.on('networkError', (event) => {
    event.preventDefault();

    toast.error('تعذر الاتصال بالخادم، يرجى التحقق من اتصال الإنترنت والمحاولة مرة أخرى.', {
        title: 'انقطاع الاتصال بالشبكة',
        duration: 8000,
    });
});

/**
 * 5. Inertia Flash Events:
 * Fires whenever session flash data is received.
 */
router.on('flash', (event) => {
    const flash = (event.detail as any)?.flash;
    if (flash?.error) {
        toast.error(flash.error);
    }
    if (flash?.success) {
        toast.success(flash.success);
    }
    if (flash?.warning) {
        toast.warning(flash.warning);
    }
});

/**
 * 6. Navigation / Page Visit:
 * Checks incoming page props for flash and errors on initial load or navigation.
 */
router.on('navigate', (event) => {
    const page = event.detail.page as any;
    if (page?.props?.flash?.error) {
        toast.error(page.props.flash.error);
    }
    if (page?.props?.flash?.success) {
        toast.success(page.props.flash.success);
    }
    if (page?.props?.flash?.warning) {
        toast.warning(page.props.flash.warning);
    }
    if (page?.props?.errors && Object.keys(page.props.errors).length > 0) {
        toast.fromErrors(page.props.errors);
    }
});
