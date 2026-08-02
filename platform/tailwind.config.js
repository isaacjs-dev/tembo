import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    // O produto ainda não possui uma paleta escura completa. Usar a estratégia
    // por classe evita que apenas alguns textos fiquem brancos quando o sistema
    // operacional está em modo escuro.
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['"Plus Jakarta Sans"', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Gray 400 is widely used for helper text and table headers.
                // This tone keeps those small texts above the WCAG AA contrast
                // threshold on white and background-light surfaces.
                "gray": {
                    "400": "#667085",
                },
                // Tembo: a calm, accessible sky/pastel-blue system. Darker
                // interaction tones retain AA contrast when paired with white.
                "primary": "#1d78a6",
                "primary-dark": "#155b80",
                "primary-light": "#dceff8",
                "secondary": "#4776bf",
                "secondary-dark": "#345891",
                "accent": "#6670b5",
                "accent-light": "#eeeffb",
                "background-light": "#f5fafd",
                "background-dark": "#142d3b",
                "surface": "#ffffff",
                "error-soft": "#fee2e2",
                "error-text": "#dc2626",
                "success": "#16a34a",
                "warning": "#f59e0b",
                "duo-border": "#d7e6ee",
                "control-border": "#7895a5",
                "duo-text": "#405b69",
                "duo-heading": "#173243",
            },
            borderRadius: {
                "DEFAULT": "0.5rem",
                "lg": "0.75rem",
                "xl": "1rem",
                "2xl": "1rem",
                "3xl": "1.5rem",
                "full": "9999px"
            },
            boxShadow: {
                'tactile': '0 4px 0 0 #155b80',
                'tactile-hover': '0 2px 0 0 #155b80',
                'tactile-secondary': '0 4px 0 0 #345891',
                'card': '0 1px 3px 0 rgba(21,91,128,0.06), 0 1px 2px -1px rgba(21,91,128,0.05)',
                'card-hover': '0 8px 22px -8px rgba(21,91,128,0.24)',
                'float': '0 12px 34px -12px rgba(21,91,128,0.28)',
            },
            borderWidth: {
                '3': '3px',
            },
            animation: {
                'fade-in': 'fadeIn 0.3s ease-out',
                'slide-up': 'slideUp 0.4s ease-out',
                'pulse-soft': 'pulseSoft 2s infinite',
            },
            keyframes: {
                fadeIn: {
                    '0%': { opacity: '0' },
                    '100%': { opacity: '1' },
                },
                slideUp: {
                    '0%': { opacity: '0', transform: 'translateY(12px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
                pulseSoft: {
                    '0%, 100%': { opacity: '1' },
                    '50%': { opacity: '0.7' },
                },
            },
        },
    },

    plugins: [forms],
};
