/// <reference types="vite/client" />

import "vue-router";

declare module "vue-router" {
  interface RouteMeta {
    breadcrumbLabel?: string;
  }
}

interface ImportMetaEnv {
  readonly VITE_APP_NAME?: string;
  readonly VITE_TCC_REGISTRAR_DOMAINS?: string;
  readonly VITE_REVERB_APP_KEY?: string;
  readonly VITE_REVERB_HOST?: string;
  readonly VITE_REVERB_PORT?: string;
  readonly VITE_REVERB_SCHEME?: string;
  readonly VITE_PUSHER_APP_KEY?: string;
  readonly VITE_PUSHER_HOST?: string;
  readonly VITE_PUSHER_PORT?: string;
  readonly VITE_PUSHER_SCHEME?: string;
  readonly VITE_PUSHER_APP_CLUSTER?: string;
}

interface ImportMeta {
  readonly env: ImportMetaEnv;
}

// Suppress missing type declarations for libraries that don't ship proper exports-compatible types
declare module "vue3-recaptcha2" {
  import { DefineComponent } from "vue";
  const VueRecaptcha: DefineComponent<{ sitekey: string; theme?: string; size?: string }, object, unknown>;
  export default VueRecaptcha;
}

export {};
