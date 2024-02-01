/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    'templates/**/*.twig',
  ],
  theme: {
    container: {
      center: true,
    },
    fontFamily: {
      'title': ['Primetime', 'ui-sans-serif'],
      'display': ['Futura Book', 'ui-sans-serif'],
      'body': ['Futura Book', 'ui-sans-serif'],
    },
    extend: {},
  },
  plugins: [],
}

