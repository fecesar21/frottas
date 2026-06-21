import defaultTheme from 'tailwindcss/defaultTheme';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.jsx',
        './resources/**/*.vue',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                brand: {
                    50:  '#eefcfb',
                    100: '#d5f5f3',
                    200: '#afecea',
                    300: '#77dee0',
                    400: '#3dc8cf',
                    500: '#1aabb3',
                    600: '#158890',
                    700: '#146f77',
                    800: '#155962',
                    900: '#164952',
                    950: '#072c33',
                },
                navy: {
                    700: '#1e2a3b',
                    800: '#162030',
                    900: '#0f1623',
                    950: '#090e18',
                },
            },
            keyframes: {
                'fade-in': {
                    '0%': { opacity: '0', transform: 'translateY(8px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
                'slide-in': {
                    '0%': { opacity: '0', transform: 'translateX(-12px)' },
                    '100%': { opacity: '1', transform: 'translateX(0)' },
                },
            },
            animation: {
                'fade-in': 'fade-in 0.3s ease-out forwards',
                'slide-in': 'slide-in 0.25s ease-out forwards',
            },
        },
    },
    plugins: [],
};
