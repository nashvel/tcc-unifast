<script setup lang="ts">
import { onBeforeUnmount, onMounted } from "vue";
import { IconX } from "@tabler/icons-vue";

withDefaults(
  defineProps<{
    title: string;
    description?: string;
    size?: "sm" | "md" | "lg" | "xl";
    closeable?: boolean;
  }>(),
  { size: "md", closeable: true },
);
const open = defineModel<boolean>({ required: true });
const widths = { sm: "max-w-sm", md: "max-w-lg", lg: "max-w-2xl", xl: "max-w-4xl" };
function close() {
  open.value = false;
}
function onKeydown(event: KeyboardEvent) {
  if (event.key === "Escape") close();
}
onMounted(() => document.addEventListener("keydown", onKeydown));
onBeforeUnmount(() => document.removeEventListener("keydown", onKeydown));
</script>

<template>
  <Teleport to="body">
    <Transition name="dialog">
      <div
        v-if="open"
        class="fixed inset-0 z-[100] grid place-items-center overflow-y-auto bg-black/45 p-4"
        role="presentation"
        @mousedown.self="close"
      >
        <section
          :class="[
            'my-auto w-full overflow-hidden rounded-xl border bg-surface shadow-2xl',
            widths[size],
          ]"
          role="dialog"
          aria-modal="true"
          :aria-label="title"
        >
          <header class="flex items-start justify-between gap-4 border-b px-5 py-4">
            <div>
              <h2 class="text-base font-semibold">{{ title }}</h2>
              <p v-if="description" class="mt-1 text-xs leading-5 text-text-muted">
                {{ description }}
              </p>
            </div>
            <button
              v-if="closeable"
              class="rounded-md p-1.5 text-text-muted hover:bg-surface-muted"
              aria-label="Close dialog"
              @click="close"
            >
              <IconX :size="17" />
            </button>
          </header>
          <div class="max-h-[70vh] overflow-y-auto p-5"><slot /></div>
          <footer
            v-if="$slots.footer"
            class="flex flex-wrap items-center justify-end gap-2 border-t bg-surface-muted/40 px-5 py-3"
          >
            <slot name="footer" :close="close" />
          </footer>
        </section>
      </div>
    </Transition>
  </Teleport>
</template>
