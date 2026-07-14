<script setup lang="ts">
import { IconLock } from "@tabler/icons-vue";
import AuthLayout from "./AuthLayout.vue";
</script>

<template>
  <AuthLayout>
    <div class="text-center">
      <span
        class="mx-auto mb-3 grid h-12 w-12 place-items-center rounded-full bg-danger-soft text-danger"
        ><IconLock :size="22"
      /></span>
      <h1 class="text-xl font-semibold">Account locked or inactive</h1>
      <p class="mx-auto mt-1 max-w-xs text-sm text-text-muted">
        Your account has been temporarily disabled. Please contact the UniFAST Office or your
        school's TES coordinator for assistance.
      </p>
      <RouterLink
        to="/login"
        class="mt-5 inline-block text-sm font-medium text-primary hover:underline"
        >← Back to sign in</RouterLink
      >
    </div>
  </AuthLayout>
</template>
