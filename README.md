# TCC UniFAST — Laravel + Vue

Laravel 13 serves a Vue 3 single-page application for the UniFAST TES administrator and student portals. The interface uses Tailwind CSS v4, the original design tokens, branding assets, and mock-only content.

## Run locally

```bash
composer install
npm install
php artisan key:generate
composer run dev
```

Open `http://localhost:8000`. Any login values work, or use one of the four demo role buttons.

## Production

```bash
npm run build
php artisan optimize
```

The Vue entry point is `resources/js/app.ts`; the Laravel SPA fallback is in `routes/web.php`. The former React implementation remains under `src/` as a visual and content reference and is not included in the production build.

The Absans `@font-face` URLs are preserved from the source project. The original repository only contained asset metadata rather than the font binaries, so deployments outside the original Lovable asset environment use the matching system sans-serif fallback until the licensed `.woff2` file is placed locally.
