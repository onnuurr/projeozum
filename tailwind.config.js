import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
        './Modules/**/*.vue',
    ],

    theme: {
        extend: {
            // Renkler app.css :root değişkenlerinden beslenir (TEK KAYNAK).
            // alpha-value deseni sayesinde bg-primary/10 gibi opaklıklar çalışır.
            colors: {
                primary: {
                    DEFAULT: 'rgb(var(--color-primary) / <alpha-value>)',
                    hover: 'rgb(var(--color-primary-hover) / <alpha-value>)',
                    soft: 'rgb(var(--color-primary-soft) / <alpha-value>)',
                },
                ink: 'rgb(var(--color-ink) / <alpha-value>)',
                muted: 'rgb(var(--color-muted) / <alpha-value>)',
                surface: 'rgb(var(--color-surface) / <alpha-value>)',
                canvas: 'rgb(var(--color-bg) / <alpha-value>)',
                line: 'rgb(var(--color-border) / <alpha-value>)',
                success: 'rgb(var(--color-success) / <alpha-value>)',
                warning: 'rgb(var(--color-warning) / <alpha-value>)',
                danger: 'rgb(var(--color-danger) / <alpha-value>)',
                info: 'rgb(var(--color-info) / <alpha-value>)',
            },
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                mono: ['"JetBrains Mono"', ...defaultTheme.fontFamily.mono],
            },
            fontSize: {
                '2xs': ['0.65rem', { lineHeight: '0.9rem' }],
                'xs': ['0.75rem', { lineHeight: '1rem' }],
                'sm': ['0.8125rem', { lineHeight: '1.25rem' }], // 13px - ERP Standard
                'base': ['0.9375rem', { lineHeight: '1.5rem' }], // 15px
                'md': ['1rem', { lineHeight: '1.5rem' }],
                'lg': ['1.125rem', { lineHeight: '1.75rem' }],
                'xl': ['1.25rem', { lineHeight: '1.75rem' }],
            },
            borderRadius: {
                'none': '0',
                'sm': '0.125rem',
                DEFAULT: '0.5rem', // 8px
                'md': '0.5rem',
                'lg': '0.5rem',
                'xl': '0.5rem',
                '2xl': '0.5rem',
                '3xl': '0.5rem',
                'full': '9999px',
            }
        },
    },

    plugins: [forms],
};
