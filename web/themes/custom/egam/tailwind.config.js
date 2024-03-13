/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    'templates/**/*.twig',
  ],
  theme: {
    container: {
      center: true,
    },
    screens: {
      sm: '480px',
      md: '768px',
      lg: '976px',
      xl: '1440px',
    },
    colors: {
      'dark': 'rgb(3 7 18)',
      'light': 'rgb(250 250 249)',
    },
    backgroundColor: ({ theme }) => ({
      dark: theme('colors.dark'),
      light: theme('colors.light'),
      body: 'rgb(231 229 228)'
    }),
    fontFamily: {
      'branding': ['Primetime', 'ui-sans-serif'],
      'title': ['Bebas Regular', 'ui-sans-serif'],
      'display': ['Futura Book', 'ui-sans-serif'],
      'body': ['Futura Book', 'ui-sans-serif'],
    },
    extend: {},
  },
  plugins: [],
}

