<script setup lang="ts">
defineProps<{ headings: string[] }>();
</script>
<template>
  <div class="overflow-x-auto rounded-lg border bg-surface">
    <table class="w-full text-left text-xs">
      <thead class="bg-surface-muted text-2xs uppercase text-text-muted">
        <tr>
          <th v-for="heading in headings" :key="heading" class="px-3 py-2.5">{{ heading }}</th>
        </tr>
      </thead>
      <tbody class="divide-y">
        <slot />
      </tbody>
    </table>
    <slot name="footer" />
  </div>
</template>
