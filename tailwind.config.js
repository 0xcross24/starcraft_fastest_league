import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/views/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', 'ui-sans-serif', 'system-ui', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'Roboto', 'Helvetica', 'Arial', 'sans-serif'],
            },
            colors: {
                neonGreen: '#39FF14',  // Neon Green
                neonBlue: '#1D8CE0',   // Neon Blue
                neonPink: '#FF1493',   // Neon Pink
                neonPurple: '#9B30FF', // Neon Purple
                neonOrange: '#FF5F1F', // Neon Orange
                neonYellow: '#FFFF00', // Neon Yellow
                neonRed: '#FF073A',    // Neon Red
                neonGold: '#C7A008',   // Neon Gold
            },
        },
    },

    plugins: [forms],
};
