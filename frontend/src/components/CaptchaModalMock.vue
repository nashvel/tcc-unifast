<script setup lang="ts">
import { ref, watch } from "vue";
import { X, RefreshCw } from "lucide-vue-next";

const props = defineProps<{
  show: boolean;
}>();

const emit = defineEmits<{
  close: [];
  verify: [code: string];
}>();

const captchaCode = ref("");

// Static SVG CAPTCHA showing "K7M3QX"
const captchaSvg = `
<svg width="200" height="60" xmlns="http://www.w3.org/2000/svg">
  <rect width="200" height="60" fill="#334155"/>
  <line x1="0" y1="10" x2="200" y2="10" stroke="#475569" stroke-width="1"/>
  <line x1="0" y1="30" x2="200" y2="30" stroke="#475569" stroke-width="1"/>
  <line x1="0" y1="50" x2="200" y2="50" stroke="#475569" stroke-width="1"/>
  <line x1="40" y1="0" x2="40" y2="60" stroke="#475569" stroke-width="1"/>
  <line x1="80" y1="0" x2="80" y2="60" stroke="#475569" stroke-width="1"/>
  <line x1="120" y1="0" x2="120" y2="60" stroke="#475569" stroke-width="1"/>
  <line x1="160" y1="0" x2="160" y2="60" stroke="#475569" stroke-width="1"/>
  <text x="15" y="40" font-family="monospace" font-size="32" font-weight="bold" fill="#e2e8f0" transform="rotate(-8 15 40)">K</text>
  <text x="45" y="42" font-family="monospace" font-size="32" font-weight="bold" fill="#e2e8f0" transform="rotate(6 45 42)">7</text>
  <text x="75" y="38" font-family="monospace" font-size="32" font-weight="bold" fill="#e2e8f0" transform="rotate(-4 75 38)">M</text>
  <text x="105" y="41" font-family="monospace" font-size="32" font-weight="bold" fill="#e2e8f0" transform="rotate(7 105 41)">3</text>
  <text x="135" y="39" font-family="monospace" font-size="32" font-weight="bold" fill="#e2e8f0" transform="rotate(-5 135 39)">Q</text>
  <text x="165" y="42" font-family="monospace" font-size="32" font-weight="bold" fill="#e2e8f0" transform="rotate(4 165 42)">X</text>
</svg>
`.trim();

const captchaImageUrl = ref(`data:image/svg+xml;base64,${btoa(captchaSvg)}`);

const handleClose = () => {
  captchaCode.value = "";
  emit("close");
};

const handleVerify = () => {
  if (captchaCode.value.trim()) {
    emit("verify", captchaCode.value.trim());
    captchaCode.value = "";
  }
};

const handleRefresh = () => {
  // Mock refresh - just reload the same image
  captchaImageUrl.value = `data:image/svg+xml;base64,${btoa(captchaSvg)}`;
};

// Reset input when modal closes
watch(() => props.show, (isShown) => {
  if (!isShown) {
    captchaCode.value = "";
  }
});
</script>

<template>
  <Teleport to="body">
    <Transition name="modal">
      <div
        v-if="show"
        class="fixed inset-0 z-[100] grid place-items-center overflow-y-auto bg-black/50 p-4 backdrop-blur-sm"
        role="presentation"
        @mousedown.self="handleClose"
      >
        <div
          class="w-full max-w-[400px] rounded-lg border border-slate-600 bg-slate-800 shadow-2xl"
          role="dialog"
          aria-modal="true"
          aria-label="CAPTCHA Verification"
        >
          <!-- Header -->
          <div class="flex items-center justify-between border-b border-slate-700 px-5 py-4">
            <h2 class="text-base font-semibold text-slate-100">Security Verification</h2>
            <button
              class="rounded-md p-1.5 text-slate-400 transition-colors hover:bg-slate-700 hover:text-slate-200"
              aria-label="Close"
              @click="handleClose"
            >
              <X :size="18" />
            </button>
          </div>

          <!-- Content -->
          <div class="p-5">
            <p class="mb-4 text-sm text-slate-300">
              Please enter the characters shown in the image below:
            </p>

            <!-- CAPTCHA Image Container -->
            <div class="mb-4 flex items-center justify-center gap-2 rounded-lg bg-slate-900 p-3">
              <div class="flex-1 overflow-hidden rounded border border-slate-700">
                <img
                  :src="captchaImageUrl"
                  alt="CAPTCHA"
                  class="h-[60px] w-full object-contain"
                />
              </div>
              <button
                class="rounded-md p-2 text-slate-400 transition-colors hover:bg-slate-700 hover:text-slate-200"
                aria-label="Refresh CAPTCHA"
                title="Refresh CAPTCHA"
                @click="handleRefresh"
              >
                <RefreshCw :size="20" />
              </button>
            </div>

            <!-- Input Field -->
            <div class="mb-4">
              <label for="captcha-input" class="mb-2 block text-sm font-medium text-slate-300">
                CAPTCHA Code
              </label>
              <input
                id="captcha-input"
                v-model="captchaCode"
                type="text"
                placeholder="Enter code"
                class="w-full rounded-md border border-slate-600 bg-slate-900 px-3 py-2 text-slate-100 placeholder-slate-500 transition-colors focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/50"
                @keyup.enter="handleVerify"
              />
            </div>

            <!-- Actions -->
            <div class="flex gap-2">
              <button
                type="button"
                class="flex-1 rounded-md border border-slate-600 bg-slate-700 px-4 py-2 text-sm font-medium text-slate-200 transition-colors hover:bg-slate-600"
                @click="handleClose"
              >
                Cancel
              </button>
              <button
                type="button"
                class="flex-1 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="!captchaCode.trim()"
                @click="handleVerify"
              >
                Verify
              </button>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.2s ease;
}

.modal-enter-active > div,
.modal-leave-active > div {
  transition: transform 0.2s ease, opacity 0.2s ease;
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}

.modal-enter-from > div,
.modal-leave-to > div {
  transform: scale(0.95);
  opacity: 0;
}
</style>
