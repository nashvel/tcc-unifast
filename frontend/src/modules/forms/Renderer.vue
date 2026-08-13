<script setup lang="ts">
/**
 * Dynamic Form Renderer
 * Renders a form schema into live inputs with full client-side validation,
 * honeypot field (off-screen via CSS), double-submit prevention,
 * and rate-limit / 409 / 410 error handling.
 * Never uses v-html for user content.
 */
import { ref, computed, reactive } from "vue";
import type { FormSchema } from "@/api/types";

const props = defineProps<{
  schema: FormSchema;
  previewMode?: boolean;
}>();

const emit = defineEmits<{
  submit: [data: Record<string, unknown>];
}>();

// ─── Honeypot field (off-screen, never labeled) ──────────────────────────────
// CSS hides it without display:none (bots detect that).
// Field name is generic and attractive to bots.
const honeypotValue = ref("");

// ─── Form values ─────────────────────────────────────────────────────────────
const values = reactive<Record<string, any>>({});
const errors = reactive<Record<string, string>>({});
const touched = reactive<Record<string, boolean>>({});

// Initialize values
props.schema.fields.forEach((f) => {
  if (f.field_type === "checkbox") {
    values[f.field_name] = [];
  } else {
    values[f.field_name] = "";
  }
});

const submitState = ref<"idle" | "loading" | "success" | "error">("idle");
const submitMessage = ref("");
const rateLimitCountdown = ref(0);

// ─── Validation ───────────────────────────────────────────────────────────────

function validateField(fieldName: string): string | null {
  const field = props.schema.fields.find((f) => f.field_name === fieldName);
  if (!field) return null;

  const value = values[fieldName];
  const blank = value === "" || value === null || value === undefined || (Array.isArray(value) && value.length === 0);

  if (field.is_required && blank) return `${field.label} is required.`;
  if (blank) return null;

  const str = String(value);

  switch (field.field_type) {
    case "email":
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(str)) return "Enter a valid email address.";
      break;
    case "number": {
      if (isNaN(Number(value))) return `${field.label} must be a number.`;
      const n = Number(value);
      if (field.min_value !== null && n < Number(field.min_value)) return `Minimum value is ${field.min_value}.`;
      if (field.max_value !== null && n > Number(field.max_value)) return `Maximum value is ${field.max_value}.`;
      break;
    }
    case "text":
    case "textarea": {
      if (field.min_length !== null && str.length < field.min_length) return `Minimum ${field.min_length} characters.`;
      if (field.max_length !== null && str.length > field.max_length) return `Maximum ${field.max_length} characters.`;
      break;
    }
    case "date": {
      if (!/^\d{4}-\d{2}-\d{2}$/.test(str)) return "Enter a valid date.";
      if (field.min_value && str < field.min_value) return `Date must be on or after ${field.min_value}.`;
      if (field.max_value && str > field.max_value) return `Date must be on or before ${field.max_value}.`;
      break;
    }
    case "checkbox": {
      const arr = value as string[];
      if (field.is_required && arr.length === 0) return `Select at least one option.`;
      break;
    }
  }
  return null;
}

function onBlur(fieldName: string) {
  touched[fieldName] = true;
  const err = validateField(fieldName);
  if (err) errors[fieldName] = err;
  else delete errors[fieldName];
}

const allValid = computed(() => {
  return props.schema.fields.every((f) => {
    if (!f.is_required) return true;
    const value = values[f.field_name];
    if (f.field_type === "checkbox") return (value as string[]).length > 0;
    return value !== "" && value !== null && value !== undefined;
  });
});

// Character counter helper
function charCount(fieldName: string, maxLen: number | null) {
  return maxLen ? `${String(values[fieldName] ?? "").length} / ${maxLen}` : null;
}

// Checkbox helpers
function isChecked(fieldName: string, option: string): boolean {
  return (values[fieldName] as string[]).includes(option);
}

function toggleCheckbox(fieldName: string, option: string) {
  const arr = values[fieldName] as string[];
  const idx = arr.indexOf(option);
  if (idx >= 0) arr.splice(idx, 1);
  else arr.push(option);
  onBlur(fieldName);
}

// ─── Submit ───────────────────────────────────────────────────────────────────

const isClosed = computed(() => {
  if (!props.schema.closes_at) return false;
  return new Date(props.schema.closes_at) < new Date();
});

async function handleSubmit() {
  if (props.previewMode) {
    alert("Preview mode — submission disabled.");
    return;
  }

  // Touch all fields
  props.schema.fields.forEach((f) => {
    touched[f.field_name] = true;
    const err = validateField(f.field_name);
    if (err) errors[f.field_name] = err;
    else delete errors[f.field_name];
  });

  if (Object.keys(errors).length > 0) return;
  if (!allValid.value) return;

  submitState.value = "loading";

  const payload: Record<string, unknown> = {
    ...values,
    website: honeypotValue.value, // honeypot field included
  };

  emit("submit", payload);
}

// Called by parent after submit resolves
function onSuccess() {
  submitState.value = "success";
  submitMessage.value = "Your response has been submitted successfully. Thank you!";
}

function onError(status: number, message: string) {
  if (status === 409) {
    submitState.value = "error";
    submitMessage.value = "You have already submitted this form.";
  } else if (status === 410) {
    submitState.value = "error";
    submitMessage.value = "This form is no longer accepting responses.";
  } else if (status === 429) {
    submitState.value = "idle";
    rateLimitCountdown.value = 60;
    const tick = setInterval(() => {
      rateLimitCountdown.value--;
      if (rateLimitCountdown.value <= 0) clearInterval(tick);
    }, 1000);
    submitMessage.value = "";
  } else {
    submitState.value = "error";
    submitMessage.value = message || "Something went wrong. Please try again.";
  }
}

defineExpose({ onSuccess, onError });
</script>

<template>
  <div class="space-y-5">
    <!-- Closed banner -->
    <div v-if="isClosed" class="rounded-lg border border-warning/30 bg-warning-soft p-4 text-sm text-warning font-medium">
      This form is closed and no longer accepting responses.
    </div>

    <!-- Success -->
    <div v-else-if="submitState === 'success'" class="rounded-lg border border-success/30 bg-success-soft p-6 text-center">
      <p class="text-lg font-semibold text-success mb-1">✓ Submitted!</p>
      <p class="text-sm text-text-muted">{{ submitMessage }}</p>
    </div>

    <!-- Form -->
    <template v-else-if="!isClosed">
      <!-- Already submitted banner -->
      <div v-if="schema.already_submitted" class="rounded-lg border border-warning/30 bg-warning-soft p-4 text-sm text-warning">
        You have already submitted this form.
      </div>

      <div class="space-y-4">
        <div v-for="field in schema.fields" :key="field.field_name" class="space-y-1">
          <!-- Label -->
          <label :for="`field-${field.field_name}`" class="block text-sm font-medium">
            {{ field.label }}
            <span v-if="field.is_required" class="ml-0.5 text-danger" aria-label="required">*</span>
          </label>
          <p v-if="field.placeholder && ['text','email','textarea','number'].includes(field.field_type)" class="sr-only">{{ field.placeholder }}</p>

          <!-- Text -->
          <input
            v-if="field.field_type === 'text'"
            :id="`field-${field.field_name}`"
            v-model="values[field.field_name]"
            type="text"
            class="h-10 w-full rounded-md border bg-surface px-3 text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary transition"
            :placeholder="field.placeholder ?? ''"
            :maxlength="field.max_length ?? undefined"
            :class="{ 'border-danger ring-1 ring-danger/30': errors[field.field_name] && touched[field.field_name] }"
            @blur="onBlur(field.field_name)"
          />

          <!-- Email -->
          <input
            v-else-if="field.field_type === 'email'"
            :id="`field-${field.field_name}`"
            v-model="values[field.field_name]"
            type="email"
            class="h-10 w-full rounded-md border bg-surface px-3 text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary transition"
            :placeholder="field.placeholder ?? ''"
            :class="{ 'border-danger ring-1 ring-danger/30': errors[field.field_name] && touched[field.field_name] }"
            @blur="onBlur(field.field_name)"
          />

          <!-- Number -->
          <input
            v-else-if="field.field_type === 'number'"
            :id="`field-${field.field_name}`"
            v-model="values[field.field_name]"
            type="number"
            class="h-10 w-full rounded-md border bg-surface px-3 text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary transition"
            :placeholder="field.placeholder ?? ''"
            :min="field.min_value ?? undefined"
            :max="field.max_value ?? undefined"
            :class="{ 'border-danger ring-1 ring-danger/30': errors[field.field_name] && touched[field.field_name] }"
            @blur="onBlur(field.field_name)"
          />

          <!-- Textarea -->
          <div v-else-if="field.field_type === 'textarea'" class="space-y-0.5">
            <textarea
              :id="`field-${field.field_name}`"
              v-model="values[field.field_name]"
              rows="4"
              class="w-full rounded-md border bg-surface px-3 py-2 text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary transition"
              :placeholder="field.placeholder ?? ''"
              :maxlength="field.max_length ?? undefined"
              :class="{ 'border-danger ring-1 ring-danger/30': errors[field.field_name] && touched[field.field_name] }"
              @blur="onBlur(field.field_name)"
            ></textarea>
            <p v-if="field.max_length" class="text-right text-micro text-text-muted">
              {{ charCount(field.field_name, field.max_length) }}
            </p>
          </div>

          <!-- Date -->
          <input
            v-else-if="field.field_type === 'date'"
            :id="`field-${field.field_name}`"
            v-model="values[field.field_name]"
            type="date"
            class="h-10 w-full rounded-md border bg-surface px-3 text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary transition"
            :min="field.min_value ?? undefined"
            :max="field.max_value ?? undefined"
            :class="{ 'border-danger ring-1 ring-danger/30': errors[field.field_name] && touched[field.field_name] }"
            @blur="onBlur(field.field_name)"
          />

          <!-- Select -->
          <select
            v-else-if="field.field_type === 'select'"
            :id="`field-${field.field_name}`"
            v-model="values[field.field_name]"
            class="h-10 w-full rounded-md border bg-surface px-3 text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary transition"
            :class="{ 'border-danger ring-1 ring-danger/30': errors[field.field_name] && touched[field.field_name] }"
            @blur="onBlur(field.field_name)"
          >
            <option value="">Select an option…</option>
            <option v-for="opt in field.options" :key="opt" :value="opt">{{ opt }}</option>
          </select>

          <!-- Radio -->
          <div v-else-if="field.field_type === 'radio'" class="space-y-2" role="radiogroup">
            <label
              v-for="opt in field.options"
              :key="opt"
              class="flex items-center gap-2 text-sm cursor-pointer"
            >
              <input
                type="radio"
                :name="`field-${field.field_name}`"
                :value="opt"
                v-model="values[field.field_name]"
                class="text-primary"
                @change="onBlur(field.field_name)"
              />
              {{ opt }}
            </label>
          </div>

          <!-- Checkbox -->
          <div v-else-if="field.field_type === 'checkbox'" class="space-y-2">
            <label
              v-for="opt in field.options"
              :key="opt"
              class="flex items-center gap-2 text-sm cursor-pointer"
            >
              <input
                type="checkbox"
                :value="opt"
                :checked="isChecked(field.field_name, opt)"
                class="rounded text-primary"
                @change="toggleCheckbox(field.field_name, opt)"
              />
              {{ opt }}
            </label>
          </div>

          <!-- File -->
          <div v-else-if="field.field_type === 'file'" class="space-y-1">
            <input
              :id="`field-${field.field_name}`"
              type="file"
              :accept="field.accepted_types ?? 'application/pdf,image/jpeg,image/png'"
              class="w-full rounded-md border bg-surface px-3 py-2 text-sm file:mr-3 file:rounded file:border-0 file:bg-primary-soft file:px-2 file:py-1 file:text-xs file:font-medium file:text-primary"
              :class="{ 'border-danger': errors[field.field_name] && touched[field.field_name] }"
              @change="(e) => { values[field.field_name] = (e.target as HTMLInputElement).files?.[0] ?? ''; onBlur(field.field_name); }"
            />
            <p class="text-micro text-text-muted">
              Accepted: {{ field.accepted_types ?? 'PDF, JPG, PNG' }}
              <template v-if="field.max_file_size"> · Max {{ Math.round(field.max_file_size / 1024) }} MB</template>
            </p>
          </div>

          <!-- Inline error -->
          <p v-if="errors[field.field_name] && touched[field.field_name]" class="text-micro text-danger" role="alert">
            {{ errors[field.field_name] }}
          </p>
        </div>
      </div>

      <!-- Honeypot field — visually hidden off-screen, not display:none -->
      <div style="position:absolute;left:-9999px;top:-9999px;width:1px;height:1px;overflow:hidden;" aria-hidden="true">
        <label for="website-field">Website</label>
        <input id="website-field" v-model="honeypotValue" type="text" name="website" autocomplete="off" tabindex="-1" />
      </div>

      <!-- Error banner -->
      <div v-if="submitState === 'error'" class="rounded-lg border border-danger/30 bg-danger-soft p-3 text-sm text-danger">
        {{ submitMessage }}
      </div>

      <!-- Rate limit countdown -->
      <div v-if="rateLimitCountdown > 0" class="rounded-lg border border-warning/30 bg-warning-soft p-3 text-sm text-warning">
        Too many requests. Please wait {{ rateLimitCountdown }} second{{ rateLimitCountdown !== 1 ? 's' : '' }} before trying again.
      </div>

      <!-- Submit button -->
      <button
        v-if="!schema.already_submitted"
        id="btn-submit-form"
        type="button"
        class="h-11 w-full rounded-md bg-primary text-sm font-semibold text-white transition hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-50"
        :disabled="submitState === 'loading' || rateLimitCountdown > 0 || isClosed"
        @click="handleSubmit"
      >
        <span v-if="submitState === 'loading'" class="inline-flex items-center gap-2">
          <svg class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83" />
          </svg>
          Submitting…
        </span>
        <span v-else>Submit</span>
      </button>
    </template>
  </div>
</template>
