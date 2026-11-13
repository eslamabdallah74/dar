import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { router, usePage } from '@inertiajs/vue3';

export function useLocale() {
    const { locale, t } = useI18n();
    const page = usePage();

    const currentLocale = computed<'ar' | 'en'>({
        get() {
            const pageLocale = (page.props as any)?.locale;
            if (pageLocale === 'ar' || pageLocale === 'en') {
                if (locale.value !== pageLocale) {
                    locale.value = pageLocale;
                }
                return pageLocale;
            }
            return (locale.value as 'ar' | 'en') || 'ar';
        },
        set(val: 'ar' | 'en') {
            locale.value = val;
        },
    });

    const isRtl = computed(() => currentLocale.value === 'ar');
    const dir = computed(() => (isRtl.value ? 'rtl' : 'ltr'));

    function switchLocale(newLocale: 'ar' | 'en') {
        locale.value = newLocale;
        if (typeof document !== 'undefined') {
            document.documentElement.lang = newLocale;
            document.documentElement.dir = newLocale === 'ar' ? 'rtl' : 'ltr';
        }
        router.post(
            route('user.locale'),
            { locale: newLocale },
            {
                preserveScroll: true,
                preserveState: true,
            }
        );
    }

    return {
        currentLocale,
        isRtl,
        dir,
        t,
        switchLocale,
    };
}
