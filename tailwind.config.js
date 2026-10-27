import defaultTheme from 'tailwindcss/defaultTheme';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.ts',
        './resources/**/*.vue',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['"Source Sans 3"', ...defaultTheme.fontFamily.sans],
                display: ['"Sora"', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                vb: {
                    plum: '#3B0A32',
                    'plum-dark': '#290623',
                    'plum-2': '#5B1450',
                    violet: '#7B2D8E',
                    'violet-light': '#F5EBF8',
                    teal: '#1E7FA8',
                    cyan: '#21A3C4',
                    green: '#4E9E4A',
                    emerald: '#2F8F6E',
                    amber: '#D9701F',
                    cream: '#F5F7FA',
                    'cream-2': '#E5E7EB',
                    'cream-dark': '#E2E8F0',
                    ink: '#111827',
                    muted: '#6B7280',
                },
            },
            boxShadow: {
                card: '0 8px 32px rgba(0,0,0,0.04), inset 0 1px 0 rgba(255,255,255,0.7)',
                'card-hover': '0 22px 48px -24px rgba(59,10,50,.45)',
                'plum-btn': '0 14px 30px -16px rgba(59,10,50,.9)',
            },
            borderRadius: {
                'card': '20px',
            },
        },
    },
    plugins: [],
};
