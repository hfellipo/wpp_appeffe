import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Verdes
                'brand': {
                    DEFAULT: '#2ECC71',
                    50: '#E8F8F0',
                    100: '#D1F1E1',
                    200: '#A3E3C3',
                    300: '#7DCEA0',  // Verde Claro - Leve, natural, amigável
                    400: '#52BE80',
                    500: '#2ECC71',  // Verde Médio - Fresco, moderno, positivo
                    600: '#27AE60',
                    700: '#1F7A4D',  // Verde Escuro - Elegante, confiança, base forte
                    800: '#196F3D',
                    900: '#145A32',
                    950: '#0E3D22',
                },
                // Transição
                'lime': {
                    DEFAULT: '#B7E04B',
                    50: '#F7FCE8',
                    100: '#EFF9D1',
                    200: '#DFF3A3',
                    300: '#CFED75',
                    400: '#B7E04B',  // Verde-Amarelado (Lima) - Energia, inovação, atenção
                    500: '#A3D433',
                    600: '#8AB82A',
                    700: '#6E9322',
                    800: '#526E19',
                    900: '#364911',
                },
                // Amarelos
                'golden': {
                    DEFAULT: '#F4D03F',
                    50: '#FEFCF0',
                    100: '#FDF9E1',
                    200: '#FBF3C3',
                    300: '#F9EDA5',
                    400: '#F4D03F',  // Amarelo Suave - Otimismo, destaque sem agressividade
                    500: '#F1C40F',  // Amarelo Vivo - Energia, ação, chamadas importantes
                    600: '#D4AC0D',
                    700: '#B7950B',
                    800: '#9A7D0A',
                    900: '#7D6608',
                },
            },
        },
    },

    plugins: [forms],
};
