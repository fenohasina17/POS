/** @type {import('tailwindcss').Config} */
import colors from 'tailwindcss/colors';

// Thème unique de la caisse : rouge IGP en couleur primaire, ardoise (slate)
// pour les neutres. Les écrans n'utilisent que ces noms de couleurs ; les
// composants partagés (boutons, cartes, badges, modales…) sont dans
// src/assets/main.css.
export const content = [
  "./index.html",
  "./src/**/*.{vue,js,ts,jsx,tsx}",
];
export const theme = {
  extend: {
    colors: {
      primary: {
        50: '#fff5f5',
        100: '#ffe1e1',
        200: '#ffc4c5',
        300: '#ff9a9e',
        400: '#ff6b77',
        500: '#f6404f',
        600: '#d81f33',
        700: '#b31229',
        800: '#8f0f24',
        900: '#6f0d1f',
        950: '#470715',
      },
      danger: colors.rose,
      success: colors.emerald,
      warning: colors.amber,
    },
  },
};
export const plugins = [];
