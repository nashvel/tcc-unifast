<script setup lang="ts">
import { ref, watch } from "vue";
import { X, RefreshCw, Loader2 } from "lucide-vue-next";
import { fetchLoginCaptcha } from "@/api/auth";

const props = defineProps<{
  show: boolean;
  /** Error message from the login API (wrong captcha, wrong password, etc.) */
  submitError?: string;
  /** True while the login API call is in-flight */
  loading?: boolean;
}>();

const emit = defineEmits<{
  close: [];
  verify: [code: string];
}>();

const captchaCode = ref("");
const captchaImageUrl = ref("");
const isLoading = ref(false);
const error = ref("");

const loadCaptcha = async () => {
  isLoading.value = true;
  error.value = "";
  try {
    const response = await fetchLoginCaptcha();
    captchaImageUrl.value = response.image;
  } catch (err) {
    error.value = "Failed to load CAPTCHA. Please try again.";
    console.error("Failed to load CAPTCHA:", err);
  } finally {
    isLoading.value = false;
  }
};

const handleClose = () => {
  captchaCode.value = "";
  error.value = "";
  emit("close");
};

const handleVerify = () => {
  if (captchaCode.value.trim() && !props.loading) {
    emit("verify", captchaCode.value.trim());
    captchaCode.value = "";
  }
};

const handleRefresh = async () => {
  captchaCode.value = "";
  await loadCaptcha();
};

// Load CAPTCHA when modal opens
watch(() => props.show, async (isShown) => {
  if (isShown) {
    captchaCode.value = "";
    error.value = "";
    await loadCaptcha();
  } else {
    captchaCode.value = "";
    error.value = "";
  }
}, { immediate: false });

// When the parent signals a submit error (wrong captcha / wrong credentials),
// auto-reload the captcha image so the user can try a fresh code.
watch(() => props.submitError, async (msg) => {
  if (msg) {
    captchaCode.value = "";
    await loadCaptcha();
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
              class="rounded-md p-1.5 text-slate-400 transition-colors hover:bg-slate-700 hover:text-slate-200 disabled:opacity-40"
              aria-label="Close"
              :disabled="loading"
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

            <!-- Submit error banner (wrong captcha / wrong password / blocked) -->
            <div
              v-if="submitError"
              class="mb-4 flex items-start gap-2 rounded-md border border-red-500/40 bg-red-500/10 px-3 py-2.5"
            >
              <span class="mt-0.5 shrink-0 text-red-400">&#9888;</span>
              <p class="text-xs font-medium text-red-300">{{ submitError }}</p>
            </div>

            <!-- CAPTCHA Image Container -->
            <div class="mb-4 flex items-center justify-center gap-2 rounded-lg bg-slate-900 p-3">
              <div class="flex-1 overflow-hidden rounded border border-slate-700">
                <div v-if="isLoading || loading" class="flex h-[60px] items-center justify-center">
                  <div class="h-6 w-6 animate-spin rounded-full border-2 border-slate-600 border-t-blue-500"></div>
                </div>
                <div v-else-if="error" class="flex h-[60px] items-center justify-center px-4">
                  <p class="text-xs text-red-400">{{ error }}</p>
                </div>
                <img
                  v-else-if="captchaImageUrl"
                  :src="captchaImageUrl"
                  alt="CAPTCHA"
                  class="h-[60px] w-full object-contain"
                />
              </div>
              <button
                class="rounded-md p-2 text-slate-400 transition-colors hover:bg-slate-700 hover:text-slate-200 disabled:cursor-not-allowed disabled:opacity-50"
                aria-label="Refresh CAPTCHA"
                title="Refresh CAPTCHA"
                :disabled="isLoading || loading"
                @click="handleRefresh"
              >
                <RefreshCw :size="20" :class="{ 'animate-spin': isLoading }" />
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
                :disabled="isLoading || loading || !!error"
                class="w-full rounded-md border border-slate-600 bg-slate-900 px-3 py-2 text-slate-100 placeholder-slate-500 transition-colors focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/50 disabled:cursor-not-allowed disabled:opacity-50"
                @keyup.enter="handleVerify"
              />
            </div>

            <!-- Actions -->
            <div class="flex gap-2">
              <button
                type="button"
                class="flex-1 rounded-md border border-slate-600 bg-slate-700 px-4 py-2 text-sm font-medium text-slate-200 transition-colors hover:bg-slate-600 disabled:opacity-50"
                :disabled="loading"
                @click="handleClose"
              >
                Cancel
              </button>
              <button
                type="button"
                class="flex h-[38px] flex-1 items-center justify-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="!captchaCode.trim() || isLoading || loading || !!error"
                @click="handleVerify"
              >
                <Loader2 v-if="loading" :size="15" class="animate-spin" />
                <span>{{ loading ? 'Verifying...' : 'Verify' }}</span>
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
