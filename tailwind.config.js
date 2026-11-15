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
                    // Primary Brand Foundations
                    plum: '#5A0E57',
                    'plum-deep': '#310B35',
                    'plum-dark': '#230526',
                    'plum-2': '#5B1450',
                    'plum-classic': '#3B0A32',
                    magenta: '#8E1E78',
                    violet: '#7B2D8E',
                    'violet-light': '#F5EBF8',
                    
                    // Logo Accent Palette
                    teal: '#00A9A5',
                    'teal-classic': '#1E7FA8',
                    cyan: '#21A3C4',
                    green: '#7BBF3B',
                    'green-classic': '#4E9E4A',
                    emerald: '#2F8F6E',
                    orange: '#FF8C00',
                    amber: '#D9701F',

                    // Neutrals & Surfaces
                    'soft-bg': '#F7F5FA',
                    cream: '#F7F5FA',
                    'cream-2': '#E5E7EB',
                    'cream-dark': '#E2E8F0',
                    ink: '#111827',
                    muted: '#6B7280',
                },
            },
            boxShadow: {
                card: '0 8px 32px rgba(49, 11, 53, 0.04), inset 0 1px 0 rgba(255, 255, 255, 0.8)',
                'card-hover': '0 20px 42px -12px rgba(49, 11, 53, 0.12)',
                glass: '0 12px 36px -10px rgba(49, 11, 53, 0.06), inset 0 1px 0 rgba(255, 255, 255, 0.9)',
                'glass-elevated': '0 24px 48px -12px rgba(49, 11, 53, 0.16), 0 4px 12px rgba(0, 0, 0, 0.04), inset 0 1px 0 #FFFFFF',
                'glass-sidebar': '6px 0 30px rgba(25, 4, 28, 0.25)',
                'plum-btn': '0 12px 24px -8px rgba(90, 14, 87, 0.55)',
            },
            borderRadius: {
                card: '20px',
                modal: '24px',
            },
        },
    },
    plugins: [],
};
