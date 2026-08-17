import { defineConfig } from "vite";
import vue from "@vitejs/plugin-vue";
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
  plugins: [vue(), tailwindcss()],
  resolve: { alias: { "@": "/src" } },
  build: {
    rollupOptions: {
      output: {
        manualChunks: {
          "vue-vendor": ["vue", "vue-router", "vue-i18n"],
          "query-vendor": ["@tanstack/vue-query"],
          "ui-vendor": ["@tabler/icons-vue", "lucide-vue-next", "vue-sonner"],
        },
      },
    },
  },
  server: {
    allowedHosts: true,
    host: "0.0.0.0",
    port: 5173,
    proxy: {
      "/sanctum": {
        target: "http://127.0.0.1:8088",
        changeOrigin: true,
      },
      "/api": {
        target: "http://127.0.0.1:8088",
        changeOrigin: true,
      },
      "/broadcasting": {
        target: "http://127.0.0.1:8088",
        changeOrigin: true,
      },
      // Do NOT proxy /models — face-api weights live in frontend/public/models
      // and must be served as Vite static files (tunnel HTTPS + localhost/LAN).
    },
  },
});
