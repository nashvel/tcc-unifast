<script setup lang="ts">
import { computed, ref } from "vue";
import { IconFile, IconFileTypePdf, IconPhoto, IconSearch, IconUpload } from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
const query = ref("");
const category = ref("all");
const files = [
  ["Certificate of Enrollment.pdf", "document", "Maria Angela Santos", "1.8 MB", "May 12, 2026"],
  ["PSA Birth Certificate.jpg", "image", "Nicole Anne Flores", "2.4 MB", "May 11, 2026"],
  ["Grades Transcript.pdf", "document", "John Paul Ramirez", "980 KB", "May 10, 2026"],
  ["Profile Photo.png", "image", "Christian Dela Cruz", "420 KB", "May 9, 2026"],
];
const rows = computed(() =>
  files.filter(
    (f) =>
      (category.value === "all" || f[1] === category.value) &&
      `${f[0]} ${f[2]}`.toLowerCase().includes(query.value.toLowerCase()),
  ),
);
</script>
<template>
  <div>
    <PageHeader title="File Manager" description="Browse and organize uploaded scholarship files."
      ><template #actions
        ><button
          class="inline-flex h-9 items-center gap-1.5 rounded-md bg-primary px-3 text-xs text-white"
        >
          <IconUpload :size="14" />Upload file
        </button></template
      ></PageHeader
    >
    <section class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
      <article
        v-for="c in [
          ['All files', '2,486'],
          ['Documents', '1,842'],
          ['Images', '612'],
          ['Storage used', '4.8 GB'],
        ]"
        :key="c[0]"
        class="rounded-lg border bg-surface p-4"
      >
        <p class="text-xs text-text-muted">{{ c[0] }}</p>
        <p class="mt-1 text-xl font-semibold">{{ c[1] }}</p>
      </article>
    </section>
    <section class="mb-3 flex flex-wrap gap-2">
      <div class="relative min-w-64 flex-1">
        <IconSearch
          :size="14"
          class="absolute left-3 top-1/2 -translate-y-1/2 text-text-soft"
        /><input
          v-model="query"
          class="h-9 w-full rounded-md border pl-9 pr-3 text-xs"
          placeholder="Search files or owners"
        />
      </div>
      <select v-model="category" class="h-9 rounded-md border bg-surface px-3 text-xs">
        <option value="all">All categories</option>
        <option value="document">Documents</option>
        <option value="image">Images</option>
      </select>
    </section>
    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
      <article v-for="f in rows" :key="f[0]" class="rounded-lg border bg-surface p-4">
        <component
          :is="f[1] === 'image' ? IconPhoto : f[0].endsWith('.pdf') ? IconFileTypePdf : IconFile"
          :size="28"
          class="text-primary"
        />
        <h2 class="mt-4 truncate text-sm font-semibold">{{ f[0] }}</h2>
        <p class="mt-1 text-xs text-text-muted">{{ f[2] }}</p>
        <div class="mt-4 flex justify-between border-t pt-3 text-micro text-text-muted">
          <span>{{ f[3] }}</span
          ><span>{{ f[4] }}</span>
        </div>
        <div class="mt-3 flex gap-3 text-xs text-primary">
          <button>Preview</button><button>Download</button>
        </div>
      </article>
    </section>
    <p v-if="!rows.length" class="rounded-lg border p-8 text-center text-sm text-text-muted">
      No files found.
    </p>
  </div>
</template>
