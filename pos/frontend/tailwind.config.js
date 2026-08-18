/** @type {import('tailwindcss').Config} */
export const content = [
  "./index.html",
  "./src/**/*.{vue,js,ts,jsx,tsx}",
];
export const theme = {
  extend: {
    colors: {
      indigo: {
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
    },
  },
};
export const plugins = [];
