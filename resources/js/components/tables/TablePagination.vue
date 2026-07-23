<script setup lang="ts">
import type { PaginationMeta } from "@/lib/api";

const props = defineProps<{
  meta: PaginationMeta;
  busy?: boolean;
}>();

const emit = defineEmits<{
  "update:page": [page: number];
}>();

function go(page: number) {
  if (props.busy) return;
  if (page < 1 || page > props.meta.last_page || page === props.meta.current_page) return;
  emit("update:page", page);
}
</script>

<template>
  <footer
    class="flex flex-wrap items-center justify-between gap-2 border-t px-3 py-2.5 text-xs text-text-muted"
    aria-label="Table pagination"
  >
    <span>
      Showing
      {{ meta.from ?? 0 }}–{{ meta.to ?? 0 }}
      of {{ meta.total.toLocaleString() }}
    </span>
    <div class="flex items-center gap-2">
      <button
        class="rounded-md border px-2 py-1 disabled:opacity-40"
        :disabled="busy || meta.current_page <= 1"
        @click="go(meta.current_page - 1)"
      >
        Prev
      </button>
      <span>
        Page {{ meta.current_page }} of {{ Math.max(meta.last_page, 1) }}
      </span>
      <button
        class="rounded-md border px-2 py-1 disabled:opacity-40"
        :disabled="busy || meta.current_page >= meta.last_page"
        @click="go(meta.current_page + 1)"
      >
        Next
      </button>
    </div>
  </footer>
</template>
