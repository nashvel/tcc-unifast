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
    port: 5173,
    proxy: {
      "/api": {
        target: "http://localhost:8000",
        changeOrigin: true,
      },
      "/broadcasting": {
        target: "http://localhost:8000",
        changeOrigin: true,
      },
    },
  },
});
