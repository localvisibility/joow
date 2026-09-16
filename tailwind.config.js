import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                display: ['"Space Grotesk"', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                ink: {
                    950: '#08080c',
                    900: '#0d0d14',
                    800: '#14141f',
                    700: '#1c1c2b',
                    600: '#262638',
                },
                brand: {
                    50: '#eef2ff',
                    400: '#818cf8',
                    500: '#6366f1',
                    600: '#4f46e5',
                    700: '#4338ca',
                },
            },
            boxShadow: {
                glow: '0 0 60px -15px rgba(124, 92, 246, 0.45)',
            },
            backgroundImage: {
                'brand-gradient': 'linear-gradient(135deg, #6366f1 0%, #a855f7 100%)',
                'mesh': 'radial-gradient(60% 60% at 15% 10%, rgba(99,102,241,0.18) 0%, transparent 60%), radial-gradient(50% 50% at 90% 20%, rgba(168,85,247,0.14) 0%, transparent 55%)',
            },
        },
    },

    plugins: [forms],
};
