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
                sans: ['Montserrat', '"IBM Plex Sans Arabic"', 'Poppins', 'Alexandria', 'Cairo', 'system-ui', ...defaultTheme.fontFamily.sans],
                montserrat: ['Montserrat', 'sans-serif'],
                poppins: ['Poppins', 'sans-serif'],
                ibm: ['"IBM Plex Sans Arabic"', 'sans-serif'],
                alexandria: ['Alexandria', 'sans-serif'],
                cairo: ['Cairo', 'sans-serif'],
                mono: ['"JetBrains Mono"', ...defaultTheme.fontFamily.mono],
            },
            colors: {
                brand: {
                    flat: '#163E3E',
                    room: '#F8F4EA',
                    bed: '#F7C33D',
                    tech: '#163E3E',
                },
                nook: {
                    flat: '#163E3E',
                    room: '#F8F4EA',
                    bed: '#F7C33D',
                    tech: '#163E3E',
                    primary: {
                        DEFAULT: '#163E3E',
                        hover: '#102F2F',
                        tint: '#E8F0F0',
                    },
                    accent: {
                        DEFAULT: '#F7C33D',
                        hover: '#E5B12B',
                        tint: '#FEF7E6',
                    },
                },
                miftah: {
                    navy: '#163E3E',
                    primary: {
                        DEFAULT: '#163E3E',
                        hover: '#102F2F',
                        tint: '#E8F0F0',
                    },
                    page: '#F8F4EA',
                    surface: '#FFFFFF',
                    muted: '#EFECE2',
                    text: {
                        primary: '#163E3E',
                        body: '#334155',
                        muted: '#5B6770',
                    },
                    border: {
                        DEFAULT: '#E2DDD2',
                        strong: '#C5BCAC',
                    },
                },
            },
        },
    },

    plugins: [forms],
};
