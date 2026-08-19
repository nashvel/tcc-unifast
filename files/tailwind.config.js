// tailwind.config.js
// UniFAST — Flare Theme
// Drop this into your Vue project root

/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{vue,js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      // ── Fonts ──
      fontFamily: {
        display: ['"Space Grotesk"', 'sans-serif'],
        sans:    ['"Inter"',         'sans-serif'],
      },

      // ── Colors ──
      colors: {
        primary: {
          DEFAULT: '#FF6115',
          hover:   '#E2540F',
          light:   '#FFE4D6',
        },
        base:  '#FFFCF4',
        wash:  '#FFE4D6',
        ink:   '#1A1A18',
      },

      // ── Border radius ──
      borderRadius: {
        sm:   '0.5rem',
        md:   '0.625rem',
        lg:   '0.75rem',
        xl:   '1rem',
        '2xl':'1.25rem',
        '3xl':'1.5rem',
        '4xl':'1.75rem',
      },

      // ── Box shadows ──
      boxShadow: {
        'card':    '0 4px 24px 0 rgba(26,26,24,0.06)',
        'primary': '0 4px 16px 0 rgba(255,97,21,0.20)',
        'sm':      '0 1px 4px 0 rgba(26,26,24,0.06)',
      },

      // ── Letter spacing ──
      letterSpacing: {
        tight:   '-0.02em',
        tighter: '-0.03em',
      },

      // ── Animations ──
      keyframes: {
        'slide-up': {
          from: { opacity: '0', transform: 'translateY(20px)' },
          to:   { opacity: '1', transform: 'translateY(0)' },
        },
        'fade-in': {
          from: { opacity: '0' },
          to:   { opacity: '1' },
        },
      },
      animation: {
        'slide-up': 'slide-up 0.6s ease-out forwards',
        'fade-in':  'fade-in 0.4s ease-out forwards',
      },
    },
  },
  plugins: [],
}
