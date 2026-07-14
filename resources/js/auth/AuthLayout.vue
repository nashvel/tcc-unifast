<script setup lang="ts">
import { IconShieldCheck } from "@tabler/icons-vue";
</script>

<template>
  <div class="flex min-h-screen bg-bg">
    <aside class="hidden flex-1 flex-col justify-between border-r bg-surface p-10 lg:flex">
      <div class="flex items-center gap-2">
        <span class="grid h-8 w-8 place-items-center rounded-md bg-primary text-white">
          <IconShieldCheck :size="18" />
        </span>
        <div class="leading-tight">
          <p class="text-sm font-semibold">UniFAST TES</p>
          <p class="text-xs text-text-muted">Grantee Management</p>
        </div>
      </div>
      <div>
        <h2 class="max-w-md text-2xl font-semibold leading-tight tracking-tight">
          Tertiary Education Subsidy — Grantee Management &amp; Document Validation
        </h2>
        <p class="mt-3 max-w-md text-sm text-text-muted">
          Validate submissions, track academic records, evaluate eligibility, and communicate with
          TES grantees from a single workspace.
        </p>
        <ul class="mt-6 space-y-2 text-xs text-text-muted">
          <li>• Centralized masterlist &amp; batch management</li>
          <li>• OCR-assisted document validation</li>
          <li>• Rules-based eligibility evaluation</li>
          <li>• Full audit trail and role-based access</li>
        </ul>
      </div>
      <p class="text-micro text-text-soft">© 2026 Commission on Higher Education — UniFAST</p>
    </aside>
    <main class="flex flex-1 items-center justify-center p-6">
      <div class="w-full max-w-sm"><slot /></div>
    </main>
  </div>
</template>
