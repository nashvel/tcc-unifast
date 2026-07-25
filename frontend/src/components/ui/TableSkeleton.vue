<script setup lang="ts">
import Skeleton from "@/components/ui/Skeleton.vue";

withDefaults(
  defineProps<{
    rows?: number;
    cols?: number;
  }>(),
  { rows: 5, cols: 6 },
);

function width(row: number, col: number) {
  return `${40 + ((row * 13 + col * 17) % 55)}%`;
}
</script>

<template>
  <tbody aria-busy="true">
    <tr v-for="row in rows" :key="row" class="pointer-events-none">
      <td v-for="col in cols" :key="col" class="px-3 py-3">
        <Skeleton class-name="h-3" :style="{ width: width(row, col) }" />
      </td>
    </tr>
  </tbody>
</template>
