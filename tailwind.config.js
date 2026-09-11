/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./resources/**/*.blade.php",
    "./resources/**/*.js",
  ],
  theme: {
    extend: {
      colors: {
        navy: {
          DEFAULT: '#0B1F3A',
          light: '#13315C',
          dark: '#071527',
        },
        gold: {
          DEFAULT: '#C9A84C',
          light: '#E4C97A',
          dark: '#A6853A',
        },
        cream: '#FAF7F0',
        ink: '#1B1F27',
      },
      fontFamily: {
        display: ['"Playfair Display"', 'serif'],
        sans: ['Inter', 'ui-sans-serif', 'system-ui'],
      },
      borderRadius: {
        '2xl': '1rem',
        '3xl': '1.5rem',
      },
      boxShadow: {
        soft: '0 10px 30px -12px rgba(11,31,58,0.18)',
        card: '0 4px 20px -6px rgba(11,31,58,0.12)',
        gold: '0 10px 25px -10px rgba(201,168,76,0.45)',
      },
    },
  },
  plugins: [],
}
