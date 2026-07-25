# TCC UniFAST React Mockup

This is the isolated React/TanStack mockup app preserved for Lovable and Vercel previews.

The production Laravel + Vue app lives at the repository root. This mockup is intentionally isolated so its dependencies, routes, and experiments do not affect the Laravel/Vue application.

## Local run

```bash
cd apps/react-mock
npm install
npm run dev
```

## Vercel

Set the Vercel project root directory to:

```text
apps/react-mock
```

Build command:

```bash
npm run build
```

## Backend

External backend wiring has been removed from this mockup. Data is mocked locally.
