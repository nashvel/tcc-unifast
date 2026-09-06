<script setup lang="ts">
import { computed, ref, watch } from "vue";
import {
  IconCheck,
  IconFileCheck,
  IconLoader2,
  IconLock,
  IconSchool,
  IconShieldCheck,
  IconSparkles,
  IconX,
} from "@tabler/icons-vue";
import AppDialog from "@/components/dialogs/AppDialog.vue";
import DiceBearAvatar from "@/components/ui/DiceBearAvatar.vue";
import { useUserModules } from "@/composables/useRbac";
import { useToast } from "@/composables/useToast";
import { ApiError } from "@/api/client";
import type { RbacOperationalModule, RbacUserModuleRow } from "@/api/rbac";

const props = defineProps<{
  user: RbacUserModuleRow | null;
}>();

const open = defineModel<boolean>({ required: true });

const toast = useToast();
const {
  modules: operationalModules,
  desks,
  sortedDeskNames,
  updateMutation,
  syncMutation,
} = useUserModules();

// Local copy of assigned modules for responsive local state while mutates execute
const localAssigned = ref<string[]>([]);
const pendingToggle = ref<string | null>(null);
const isBatchPending = ref(false);

watch(
  () => props.user,
  (u) => {
    if (u) {
      localAssigned.value = [...(u.assigned_modules || [])];
    } else {
      localAssigned.value = [];
    }
  },
  { immediate: true },
);

function isModuleAssigned(key: string): boolean {
  return localAssigned.value.includes(key);
}

const totalModuleCount = computed(() => operationalModules.value.length);
const assignedCount = computed(() => localAssigned.value.length);

async function toggleModule(mod: RbacOperationalModule) {
  if (!props.user) return;
  if (props.user.is_developer || !props.user.is_assignable) {
    toast.error("Developer accounts hold permanent root access and cannot be modified.");
    return;
  }

  const currentlyHas = localAssigned.value.includes(mod.key);
  const nextEnabled = !currentlyHas;
  pendingToggle.value = mod.key;

  // Optimistically toggle locally
  if (nextEnabled) {
    localAssigned.value.push(mod.key);
  } else {
    localAssigned.value = localAssigned.value.filter((k) => k !== mod.key);
  }

  try {
    await updateMutation.mutateAsync({
      userId: props.user.id,
      moduleKey: mod.key,
      enabled: nextEnabled,
    });
    toast.success(
      nextEnabled
        ? `Assigned "${mod.name}" with full CRUD to ${props.user.name}`
        : `Removed "${mod.name}" from ${props.user.name}`,
    );
  } catch (err) {
    // Revert optimistic change
    if (nextEnabled) {
      localAssigned.value = localAssigned.value.filter((k) => k !== mod.key);
    } else {
      localAssigned.value = localAssigned.value.push(mod.key);
    }
    const msg = err instanceof ApiError ? err.message : "Failed to update module assignment.";
    toast.error(msg);
  } finally {
    pendingToggle.value = null;
  }
}

async function setDeskModules(deskName: string, enableAll: boolean) {
  if (!props.user) return;
  if (props.user.is_developer || !props.user.is_assignable) {
    toast.error("Developer accounts cannot be modified.");
    return;
  }

  const deskMods = desks.value[deskName] || [];
  const deskKeys = deskMods.map((m) => m.key);

  let nextList: string[];
  if (enableAll) {
    nextList = Array.from(new Set([...localAssigned.value, ...deskKeys]));
  } else {
    nextList = localAssigned.value.filter((k) => !deskKeys.includes(k));
  }

  localAssigned.value = nextList;
  isBatchPending.value = true;

  try {
    await syncMutation.mutateAsync({
      userId: props.user.id,
      modules: nextList,
    });
    toast.success(
      enableAll
        ? `Assigned all ${deskName} modules to ${props.user.name}`
        : `Cleared ${deskName} modules for ${props.user.name}`,
    );
  } catch (err) {
    localAssigned.value = [...(props.user.assigned_modules || [])];
    const msg = err instanceof ApiError ? err.message : "Failed to update desk modules.";
    toast.error(msg);
  } finally {
    isBatchPending.value = false;
  }
}

async function setAllModules(enableAll: boolean) {
  if (!props.user) return;
  if (props.user.is_developer || !props.user.is_assignable) {
    toast.error("Developer accounts cannot be modified.");
    return;
  }

  const nextList = enableAll ? operationalModules.value.map((m) => m.key) : [];
  localAssigned.value = nextList;
  isBatchPending.value = true;

  try {
    await syncMutation.mutateAsync({
      userId: props.user.id,
      modules: nextList,
    });
    toast.success(
      enableAll
        ? `Granted all ${totalModuleCount.value} operational modules to ${props.user.name}`
        : `Revoked all operational modules from ${props.user.name}`,
    );
  } catch (err) {
    localAssigned.value = [...(props.user.assigned_modules || [])];
    const msg = err instanceof ApiError ? err.message : "Failed to update modules.";
    toast.error(msg);
  } finally {
    isBatchPending.value = false;
  }
}

function deskAssignedCount(deskName: string): number {
  const deskMods = desks.value[deskName] || [];
  return deskMods.filter((m) => localAssigned.value.includes(m.key)).length;
}

const showAdvancedModules = ref(true);

async function applyRolePreset(preset: "evaluator" | "records_billing" | "full_operations") {
  if (!props.user) return;
  if (props.user.is_developer || !props.user.is_assignable) {
    toast.error("Developer accounts cannot be modified.");
    return;
  }

  let targetKeys: string[] = [];
  if (preset === "evaluator") {
    // Validation Desk + Grantees + File Manager
    const validationKeys = (desks.value["Validation Desk"] || []).map((m) => m.key);
    targetKeys = Array.from(new Set([...validationKeys, "grantees", "files"]));
  } else if (preset === "records_billing") {
    // Operations Desk + Academic Records + Eligibility + Reports
    const opsKeys = (desks.value["Operations Desk"] || []).map((m) => m.key);
    targetKeys = Array.from(new Set([...opsKeys, "academic", "eligibility", "reports"]));
  } else if (preset === "full_operations") {
    targetKeys = operationalModules.value.map((m) => m.key);
  }

  localAssigned.value = targetKeys;
  isBatchPending.value = true;

  try {
    await syncMutation.mutateAsync({
      userId: props.user.id,
      modules: targetKeys,
    });
    toast.success(`Applied role preset template to ${props.user.name}`);
  } catch (err) {
    localAssigned.value = [...(props.user.assigned_modules || [])];
    const msg = err instanceof ApiError ? err.message : "Failed to apply role preset.";
    toast.error(msg);
  } finally {
    isBatchPending.value = false;
  }
}
</script>

<template>
  <AppDialog
    v-model="open"
    :title="`Configure Module Permissions`"
    size="xl"
  >
    <div v-if="user" class="space-y-5">
      <!-- User profile & status banner -->
      <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border bg-surface-muted/40 p-4">
        <div class="flex items-center gap-3">
          <DiceBearAvatar :seed="user.email" :size="42" class="rounded-full shadow-xs" />
          <div>
            <div class="flex items-center gap-2">
              <h3 class="text-sm font-semibold text-text">{{ user.name }}</h3>
              <span
                class="rounded-full px-2 py-0.5 text-3xs font-medium uppercase tracking-wide"
                :class="
                  user.role === 'admin'
                    ? 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20'
                    : 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20'
                "
              >
                {{ user.role }}
              </span>
            </div>
            <p class="text-xs text-text-muted font-mono mt-0.5">{{ user.email }}</p>
          </div>
        </div>

        <!-- Quick actions -->
        <div class="flex items-center gap-2">
          <button
            type="button"
            :disabled="isBatchPending || assignedCount === totalModuleCount"
            class="inline-flex items-center gap-1 rounded-md border border-primary/20 bg-primary/5 px-2.5 py-1.5 text-xs font-medium text-primary hover:bg-primary/10 disabled:opacity-40 transition-colors"
            @click="setAllModules(true)"
          >
            <IconSparkles :size="13" />
            Grant All ({{ totalModuleCount }})
          </button>
          <button
            type="button"
            :disabled="isBatchPending || assignedCount === 0"
            class="inline-flex items-center gap-1 rounded-md border border-border bg-surface px-2.5 py-1.5 text-xs text-text-muted hover:bg-surface-muted hover:text-danger disabled:opacity-40 transition-colors"
            @click="setAllModules(false)"
          >
            <IconX :size="13" />
            Revoke All
          </button>
        </div>
      </div>

      <!-- Hick's Law Role Preset Templates -->
      <div class="rounded-xl border border-primary/25 bg-primary/5 p-4 shadow-2xs">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <div>
            <h4 class="text-xs font-semibold text-text uppercase tracking-wider flex items-center gap-1.5">
              <IconSparkles :size="14" class="text-primary" />
              1-Click Role Presets (Hick's Law Fast-Setup)
            </h4>
            <p class="text-2xs text-text-muted mt-0.5">
              Quickly provision standard desk duties in 1 click instead of evaluating 20+ individual checkboxes.
            </p>
          </div>
          <button
            type="button"
            class="text-xs font-medium text-primary hover:underline"
            @click="showAdvancedModules = !showAdvancedModules"
          >
            {{ showAdvancedModules ? "Hide module details" : "Customize individual modules" }}
          </button>
        </div>

        <div class="mt-3 grid gap-3 sm:grid-cols-3 text-xs">
          <button
            type="button"
            :disabled="isBatchPending"
            class="group flex flex-col items-start rounded-lg border border-border bg-surface p-3 text-left transition hover:border-primary/60 hover:shadow-xs disabled:opacity-50"
            @click="applyRolePreset('evaluator')"
          >
            <div class="flex items-center gap-1.5 font-semibold text-text group-hover:text-primary transition">
              <IconFileCheck :size="16" class="text-primary" />
              Document Evaluator
            </div>
            <p class="text-2xs text-text-muted mt-1 leading-snug">
              Validation Desk + Grantees &amp; File Manager. Ideal for document and biometric reviewers.
            </p>
          </button>

          <button
            type="button"
            :disabled="isBatchPending"
            class="group flex flex-col items-start rounded-lg border border-border bg-surface p-3 text-left transition hover:border-emerald-500/60 hover:shadow-xs disabled:opacity-50"
            @click="applyRolePreset('records_billing')"
          >
            <div class="flex items-center gap-1.5 font-semibold text-text group-hover:text-emerald-600 transition">
              <IconSchool :size="16" class="text-emerald-600 dark:text-emerald-400" />
              Records &amp; Billing Desk
            </div>
            <p class="text-2xs text-text-muted mt-1 leading-snug">
              Operations Desk + Academic Records &amp; Reports. For registrars and billing coordinators.
            </p>
          </button>

          <button
            type="button"
            :disabled="isBatchPending"
            class="group flex flex-col items-start rounded-lg border border-border bg-surface p-3 text-left transition hover:border-amber-500/60 hover:shadow-xs disabled:opacity-50"
            @click="applyRolePreset('full_operations')"
          >
            <div class="flex items-center gap-1.5 font-semibold text-text group-hover:text-amber-600 transition">
              <IconSparkles :size="16" class="text-amber-500" />
              Full Operations Desk
            </div>
            <p class="text-2xs text-text-muted mt-1 leading-snug">
              All operational modules enabled. For senior administrative officers.
            </p>
          </button>
        </div>
      </div>

      <!-- Security & CRUD guarantee note -->
      <div class="flex items-start gap-2.5 rounded-lg border border-border/80 bg-surface-muted/30 p-3 text-xs text-text-muted">
        <IconShieldCheck :size="16" class="text-primary flex-shrink-0 mt-0.5" />
        <div class="leading-relaxed">
          <strong class="text-text font-medium">Automatic Full-CRUD Provisioning:</strong>
          Enabling any module automatically grants full access to create, read, update, delete, review, and execute all actions and sub-capabilities under that desk module.
        </div>
      </div>

      <!-- Desk sections (with progressive disclosure) -->
      <div v-if="showAdvancedModules" class="space-y-5 max-h-[60vh] overflow-y-auto pr-1">
        <div
          v-for="deskName in sortedDeskNames"
          :key="deskName"
          class="rounded-xl border bg-surface shadow-2xs overflow-hidden"
        >
          <!-- Desk Header -->
          <div class="flex items-center justify-between border-b bg-surface-muted/60 px-4 py-2.5">
            <div class="flex items-center gap-2">
              <span class="text-xs font-semibold uppercase tracking-wider text-text">
                {{ deskName }}
              </span>
              <span class="rounded bg-surface px-2 py-0.5 text-3xs font-medium text-text-muted border">
                {{ deskAssignedCount(deskName) }} / {{ desks[deskName]?.length }} active
              </span>
            </div>

            <div class="flex items-center gap-1.5 text-xs">
              <button
                type="button"
                :disabled="isBatchPending || deskAssignedCount(deskName) === desks[deskName]?.length"
                class="text-3xs font-medium text-primary hover:underline disabled:opacity-40 px-1"
                @click="setDeskModules(deskName, true)"
              >
                Select All
              </button>
              <span class="text-text-soft text-3xs">·</span>
              <button
                type="button"
                :disabled="isBatchPending || deskAssignedCount(deskName) === 0"
                class="text-3xs font-medium text-text-muted hover:text-danger disabled:opacity-40 px-1"
                @click="setDeskModules(deskName, false)"
              >
                Clear
              </button>
            </div>
          </div>

          <!-- Modules grid for this desk -->
          <div class="grid gap-3 p-3.5 sm:grid-cols-1 md:grid-cols-2">
            <div
              v-for="mod in desks[deskName]"
              :key="mod.key"
              :class="[
                'group relative flex flex-col justify-between rounded-lg border p-3.5 transition-all cursor-pointer select-none',
                isModuleAssigned(mod.key)
                  ? 'border-emerald-500/40 bg-emerald-500/5 shadow-xs'
                  : 'border-border bg-surface hover:border-text-muted/30 hover:bg-surface-muted/30'
              ]"
              @click="toggleModule(mod)"
            >
              <div>
                <div class="flex items-start justify-between gap-2">
                  <span class="font-semibold text-xs text-text group-hover:text-primary transition-colors">
                    {{ mod.name }}
                  </span>

                  <!-- Toggle switch indicator -->
                  <div
                    :class="[
                      'relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out',
                      isModuleAssigned(mod.key) ? 'bg-emerald-500' : 'bg-surface-muted'
                    ]"
                  >
                    <span
                      :class="[
                        'pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow-md ring-0 transition duration-200 ease-in-out',
                        isModuleAssigned(mod.key) ? 'translate-x-4' : 'translate-x-0'
                      ]"
                    >
                      <IconLoader2
                        v-if="pendingToggle === mod.key"
                        :size="12"
                        class="animate-spin text-text-muted m-0.5"
                      />
                    </span>
                  </div>
                </div>

                <p class="mt-1 text-2xs text-text-muted leading-relaxed">
                  {{ mod.description }}
                </p>
              </div>

              <div class="mt-3 flex items-center justify-between pt-2 border-t border-border/40 text-3xs">
                <span
                  class="inline-flex items-center gap-1 font-medium"
                  :class="isModuleAssigned(mod.key) ? 'text-emerald-600 dark:text-emerald-400' : 'text-text-soft'"
                >
                  <IconCheck v-if="isModuleAssigned(mod.key)" :size="11" class="stroke-[2.5]" />
                  {{ isModuleAssigned(mod.key) ? "Active Access" : "Disabled" }}
                </span>
                <span class="rounded bg-surface px-1.5 py-0.5 text-text-muted border text-3xs">
                  Full CRUD & Actions
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <template #footer="{ close }">
      <div class="flex items-center justify-between w-full">
        <span class="text-xs text-text-muted">
          <strong>{{ assignedCount }}</strong> of <strong>{{ totalModuleCount }}</strong> operational modules enabled
        </span>
        <button
          type="button"
          class="rounded-md bg-white text-black font-medium px-4 py-2 text-xs hover:bg-neutral-200 transition-colors shadow-sm"
          @click="close"
        >
          Done
        </button>
      </div>
    </template>
  </AppDialog>
</template>
