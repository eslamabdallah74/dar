import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Cairo', '"IBM Plex Sans Arabic"', 'system-ui', ...defaultTheme.fontFamily.sans],
                cairo: ['Cairo', 'sans-serif'],
                mono: ['"JetBrains Mono"', ...defaultTheme.fontFamily.mono],
            },
            colors: {
                miftah: {
                    navy: '#0F2747',
                    primary: {
                        DEFAULT: '#0E6E7E',
                        hover: '#0B5A68',
                        tint: '#E3F1F4',
                    },
                    page: '#F3F7FA',
                    surface: '#FFFFFF',
                    muted: '#E8EFF4',
                    text: {
                        primary: '#0F2747',
                        body: '#334155',
                        muted: '#5B6770',
                    },
                    border: {
                        DEFAULT: '#D6E0E7',
                        strong: '#B6C5D0',
                    },
                },
            },
        },
    },

    plugins: [forms],
};
