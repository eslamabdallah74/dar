import { createI18n } from 'vue-i18n';
import ar from './ar';
import en from './en';

export type MessageSchema = typeof en;

const getInitialLocale = (): 'ar' | 'en' => {
    if (typeof document !== 'undefined') {
        const pageEl = document.getElementById('app');
        if (pageEl && pageEl.dataset.page) {
            try {
                const parsed = JSON.parse(pageEl.dataset.page);
                if (parsed.props?.locale && (parsed.props.locale === 'ar' || parsed.props.locale === 'en')) {
                    return parsed.props.locale;
                }
            } catch {
                // Ignore parse error and fall back
            }
        }
        const htmlLang = document.documentElement.lang;
        if (htmlLang === 'en' || htmlLang === 'ar') {
            return htmlLang;
        }
    }
    return 'ar';
};

export const i18n = createI18n({
    legacy: false,
    locale: getInitialLocale(),
    fallbackLocale: 'ar',
    messages: {
        ar,
        en,
    },
});

export default i18n;
