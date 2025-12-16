/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './templates/**/*.twig',
    './components/**/*.twig',
    './assets/src/**/*.js',
    '../../../modules/custom/**/templates/**/*.twig',
  ],
  safelist: [
    'views-row',
  ],
  theme: {
    container: {
      center: true,
    },
    extend: {
      screens: {
        sm: '480px',
        md: '768px',
        lg: '976px',
        xl: '1440px',
      },
      colors: {
        'dark': 'rgb(3 7 18)',
        'light': 'rgb(250 250 249)',
        'body': 'rgb(231 229 228)',
        'green': 'rgb(16 185 129)',
        'coral': 'rgb(255 127 80)',
      },
      fontFamily: {
        'branding': ['Primetime', 'ui-sans-serif'],
        'title': ['Bebas Regular', 'ui-sans-serif'],
        'display': ['Futura Book', 'ui-sans-serif'],
        'body': ['Futura Book', 'ui-sans-serif'],
      },
    },
  },
  plugins: [],
}

