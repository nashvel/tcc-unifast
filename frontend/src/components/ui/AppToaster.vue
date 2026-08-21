<script setup lang="ts">
import { Toaster } from "vue-sonner";
import "vue-sonner/style.css";
</script>

<template>
  <Toaster
    class="app-sonner"
    position="top-right"
    :duration="4000"
    :visible-toasts="4"
    close-button
    expand
    :toast-options="{
      unstyled: true,
      classes: {
        toast:
          'group pointer-events-auto relative flex w-full items-start gap-3 overflow-hidden rounded-md border border-border bg-surface p-4 pr-10 text-text shadow-lg transition-all',
        title: 'text-sm font-semibold leading-5',
        description: 'mt-1 text-sm leading-5 text-text-muted',
        content: 'grid flex-1 gap-0.5',
        icon: 'mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center',
        closeButton:
          'absolute right-2 top-2 rounded-md p-1 text-text-soft opacity-70 transition hover:bg-surface-muted hover:text-text focus:outline-none focus:ring-2 focus:ring-[var(--ring)] disabled:pointer-events-none',
        actionButton:
          'ml-auto inline-flex h-8 shrink-0 items-center justify-center rounded-md bg-primary px-3 text-xs font-semibold text-white transition hover:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-[var(--ring)]',
        cancelButton:
          'ml-auto inline-flex h-8 shrink-0 items-center justify-center rounded-md border border-border bg-surface px-3 text-xs font-semibold text-text transition hover:bg-surface-muted focus:outline-none focus:ring-2 focus:ring-[var(--ring)]',
        success: 'border-[var(--success)]/30 bg-[var(--success-soft)]',
        error: 'border-[var(--danger)]/30 bg-[var(--danger-soft)]',
        warning: 'border-[var(--warning)]/30 bg-[var(--warning-soft)]',
        info: 'border-[var(--info)]/30 bg-[var(--info-soft)]',
      },
    }"
  />
</template>
