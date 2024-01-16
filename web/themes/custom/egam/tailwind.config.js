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
      'display': ['ui-sans-serif'],
      'body': ['"Open Sans"', 'ui-sans-serif'],
    },
    extend: {},
  },
  plugins: [],
}

