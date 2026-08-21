<script setup lang="ts">
import { onMounted, ref } from "vue";
import {
  IconBuilding,
  IconCheck,
  IconDeviceLaptop,
  IconHistory,
  IconKey,
  IconLogout,
  IconMoon,
  IconPalette,
  IconPlus,
  IconShieldCheck,
  IconSun,
  IconUser,
} from "@tabler/icons-vue";
import DiceBearAvatar from "@/components/ui/DiceBearAvatar.vue";
import AppTour from "@/components/tour/AppTour.vue";
import { apiFetch, ApiError } from "@/api";
import { toast } from "vue-sonner";
import { useRouter, useRoute } from "vue-router";
import {
  disableTwoFactor,
  enableTwoFactor,
  fetchTwoFactorStatus,
  logout,
  setupTwoFactor,
  type TwoFactorSetup,
  type TwoFactorStatus,
} from "@/api/auth";
import { authSession } from "@/auth/session";
import { withLang } from "@/i18n/routeLang";

type Section = "general" | "organization" | "appearance" | "security" | "sessions";

type AcademicProgram = {
  id: number;
  code: string;
  name: string;
  pass_grade: number;
  pass_grade_display?: string;
  is_active: boolean;
};

type PolicySettings = {
  max_failed_subjects_per_semester: number;
  auto_approve_risk_threshold: number;
  default_pass_grade: number;
  default_pass_grade_display?: string;
  identity_face_pass_max: number;
  identity_face_review_max: number;
  organization_academic_year: string;
};

type ProgramFormRow = AcademicProgram & { pass_grade_input: string };

const section = ref<Section>("general");
const router = useRouter();
const route = useRoute();

async function signOut() {
  await logout();
  authSession.user = null;
  await router.push(withLang("/login", route.query.lang));
}
const dark = ref(
  typeof document !== "undefined" && document.documentElement.classList.contains("dark"),
);
const fullName = ref("System Administrator");
const twoFactor = ref<TwoFactorStatus>({
  enabled: false,
  confirmed_at: null,
  recovery_codes_remaining: 0,
});
const twoFactorSetup = ref<TwoFactorSetup | null>(null);
const twoFactorCode = ref("");
const twoFactorDisablePassword = ref("");
const recoveryCodes = ref<string[]>([]);
const loadingTwoFactor = ref(false);
const savingTwoFactor = ref(false);
const loadingOrg = ref(false);
const savingOrg = ref(false);
const programs = ref<ProgramFormRow[]>([]);
const policy = ref<PolicySettings>({
  max_failed_subjects_per_semester: 3,
  auto_approve_risk_threshold: 20,
  default_pass_grade: 3.0,
  default_pass_grade_display: "3.0",
  identity_face_pass_max: 0.45,
  identity_face_review_max: 0.6,
  organization_academic_year: "2026-2027",
});
const defaultPassGradeInput = ref("3.0");
const newProgram = ref({ code: "", name: "", pass_grade_input: "3.0" });
const editingId = ref<number | null>(null);
const showAddRow = ref(false);
const editDraft = ref({ name: "", pass_grade_input: "3.0" });

function toPassGradeDisplay(value: number | string | null | undefined): string {
  const n = Number(value);
  return Number.isFinite(n) ? n.toFixed(1) : "3.0";
}

function parsePassGrade(value: string): number | null {
  const n = Number.parseFloat(value);
  if (!Number.isFinite(n) || n < 1 || n > 5) return null;
  return Math.round(n * 10) / 10;
}

function mapProgram(program: AcademicProgram): ProgramFormRow {
  return {
    ...program,
    pass_grade: Number(toPassGradeDisplay(program.pass_grade_display ?? program.pass_grade)),
    pass_grade_input: toPassGradeDisplay(program.pass_grade_display ?? program.pass_grade),
  };
}

function startEdit(program: ProgramFormRow) {
  editingId.value = program.id;
  showAddRow.value = false;
  editDraft.value = {
    name: program.name,
    pass_grade_input: program.pass_grade_input,
  };
}

function cancelEdit() {
  editingId.value = null;
}

async function commitEdit(program: ProgramFormRow) {
  const passGrade = parsePassGrade(editDraft.value.pass_grade_input);
  if (!editDraft.value.name.trim()) {
    toast.error("Full name is required.");
    return;
  }
  if (passGrade === null) {
    toast.error("Pass grade must be a decimal from 1.0 to 5.0.");
    return;
  }
  try {
    const res = await apiFetch<{ data: AcademicProgram }>(`/api/academic-programs/${program.id}`, {
      method: "PATCH",
      body: JSON.stringify({
        name: editDraft.value.name.trim(),
        pass_grade: passGrade,
        is_active: program.is_active,
      }),
    });
    programs.value = programs.value.map((row) =>
      row.id === res.data.id ? mapProgram(res.data) : row,
    );
    editingId.value = null;
    toast.success(`Updated ${res.data.code}`);
  } catch (error) {
    toast.error(error instanceof ApiError ? error.message : "Unable to update program.");
  }
}

const nav = [
  ["general", "General", "Profile & avatar", IconUser],
  ["organization", "Organization", "Programs & pass rules", IconBuilding],
  ["appearance", "Appearance", "Theme & font", IconPalette],
  ["security", "Security", "Password", IconKey],
  ["sessions", "Sessions", "Current device & history", IconHistory],
];

function setTheme(value: boolean) {
  dark.value = value;
  if (typeof document !== "undefined") document.documentElement.classList.toggle("dark", value);
  if (typeof localStorage !== "undefined") localStorage.setItem("theme", value ? "dark" : "light");
}

async function loadOrganization() {
  loadingOrg.value = true;
  try {
    const [programsRes, policyRes] = await Promise.all([
      apiFetch<{ data: AcademicProgram[] }>("/api/academic-programs"),
      apiFetch<{ data: PolicySettings }>("/api/policy-settings"),
    ]);
    programs.value = programsRes.data.map(mapProgram);
    policy.value = policyRes.data;
    defaultPassGradeInput.value = toPassGradeDisplay(
      policyRes.data.default_pass_grade_display ?? policyRes.data.default_pass_grade,
    );
  } catch (error) {
    toast.error(error instanceof ApiError ? error.message : "Unable to load organization settings.");
  } finally {
    loadingOrg.value = false;
  }
}

async function savePolicy() {
  savingOrg.value = true;
  const defaultPass = parsePassGrade(defaultPassGradeInput.value);
  if (defaultPass === null) {
    toast.error("Default pass grade must be a decimal from 1.0 to 5.0.");
    savingOrg.value = false;
    return;
  }
  try {
    const res = await apiFetch<{ data: PolicySettings }>("/api/policy-settings", {
      method: "PUT",
      body: JSON.stringify({
        max_failed_subjects_per_semester: Number(policy.value.max_failed_subjects_per_semester),
        auto_approve_risk_threshold: Number(policy.value.auto_approve_risk_threshold),
        default_pass_grade: defaultPass,
        identity_face_pass_max: Number(policy.value.identity_face_pass_max),
        identity_face_review_max: Number(policy.value.identity_face_review_max),
        organization_academic_year: String(policy.value.organization_academic_year || "").trim(),
      }),
    });
    policy.value = res.data;
    defaultPassGradeInput.value = toPassGradeDisplay(
      res.data.default_pass_grade_display ?? res.data.default_pass_grade,
    );
    toast.success("Validation rules saved");
  } catch (error) {
    toast.error(error instanceof ApiError ? error.message : "Unable to save validation rules.");
  } finally {
    savingOrg.value = false;
  }
}

async function addProgram() {
  const code = newProgram.value.code.trim();
  const name = newProgram.value.name.trim();
  const passGrade = parsePassGrade(newProgram.value.pass_grade_input);
  if (!code || !name) {
    toast.error("Program code and name are required.");
    return;
  }
  if (passGrade === null) {
    toast.error("Pass grade must be a decimal from 1.0 to 5.0.");
    return;
  }
  try {
    const res = await apiFetch<{ data: AcademicProgram }>("/api/academic-programs", {
      method: "POST",
      body: JSON.stringify({
        code,
        name,
        pass_grade: passGrade,
      }),
    });
    programs.value = [...programs.value, mapProgram(res.data)].sort((a, b) =>
      a.code.localeCompare(b.code),
    );
    newProgram.value = { code: "", name: "", pass_grade_input: "3.0" };
    showAddRow.value = false;
    toast.success(`Added ${res.data.code}`);
  } catch (error) {
    toast.error(error instanceof ApiError ? error.message : "Unable to add program.");
  }
}

async function removeProgram(program: ProgramFormRow) {
  try {
    await apiFetch<{ ok: boolean }>(`/api/academic-programs/${program.id}`, { method: "DELETE" });
    programs.value = programs.value.filter((row) => row.id !== program.id);
    if (editingId.value === program.id) editingId.value = null;
    toast.success(`Removed ${program.code}`);
  } catch (error) {
    toast.error(error instanceof ApiError ? error.message : "Unable to remove program.");
  }
}

async function loadTwoFactor() {
  loadingTwoFactor.value = true;
  try {
    twoFactor.value = await fetchTwoFactorStatus();
  } catch (error) {
    toast.error(error instanceof ApiError ? error.message : "Unable to load security settings.");
  } finally {
    loadingTwoFactor.value = false;
  }
}

async function beginTwoFactorSetup() {
  savingTwoFactor.value = true;
  recoveryCodes.value = [];
  twoFactorCode.value = "";
  try {
    twoFactorSetup.value = await setupTwoFactor();
  } catch (error) {
    toast.error(error instanceof ApiError ? error.message : "Unable to start two-factor setup.");
  } finally {
    savingTwoFactor.value = false;
  }
}

async function confirmTwoFactorSetup() {
  if (!twoFactorSetup.value || !twoFactorCode.value.trim()) {
    toast.error("Enter the current authenticator code.");
    return;
  }

  savingTwoFactor.value = true;
  try {
    const res = await enableTwoFactor(twoFactorSetup.value.secret, twoFactorCode.value);
    recoveryCodes.value = res.recovery_codes;
    twoFactorSetup.value = null;
    twoFactorCode.value = "";
    await loadTwoFactor();
    toast.success("Two-factor authentication enabled");
  } catch (error) {
    toast.error(error instanceof ApiError ? error.message : "Unable to enable two-factor authentication.");
  } finally {
    savingTwoFactor.value = false;
  }
}

async function turnOffTwoFactor() {
  if (!twoFactorDisablePassword.value) {
    toast.error("Enter your current password.");
    return;
  }

  savingTwoFactor.value = true;
  try {
    await disableTwoFactor(twoFactorDisablePassword.value);
    twoFactorDisablePassword.value = "";
    recoveryCodes.value = [];
    await loadTwoFactor();
    toast.success("Two-factor authentication disabled. Sign in again on other devices.");
  } catch (error) {
    toast.error(error instanceof ApiError ? error.message : "Unable to disable two-factor authentication.");
  } finally {
    savingTwoFactor.value = false;
  }
}

onMounted(() => {
  void loadOrganization();
  void loadTwoFactor();
});
</script>

<template>
  <div>
    <header class="mb-4 flex items-start justify-between" data-tour="page-header">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight">Settings</h1>
        <p class="mt-1 text-sm text-text-muted">
          Manage your profile, organization, security, and sign-in activity.
        </p>
      </div>
      <div class="flex gap-2">
        <AppTour />
        <button
          class="inline-flex items-center gap-1.5 rounded-md px-3 py-2 text-xs hover:bg-surface-muted"
          @click="signOut"
        >
          <IconLogout :size="14" />Sign out
        </button>
      </div>
    </header>
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-[220px_minmax(0,1fr)]">
      <nav class="h-fit rounded-lg border bg-surface p-2 lg:sticky lg:top-20">
        <button
          v-for="item in nav"
          :key="item[0] as string"
          :class="[
            'mb-0.5 flex w-full items-start gap-2 rounded-md px-2.5 py-2 text-left',
            section === item[0] ? 'bg-sidebar-active text-[#f5e6c4]' : 'hover:bg-surface-muted',
          ]"
          @click="section = item[0] as Section"
        >
          <component :is="item[3]" :size="16" class="mt-0.5" /><span
            ><span class="block text-sm font-medium leading-tight">{{ item[1] }}</span
            ><span
              :class="[
                'block text-micro',
                section === item[0] ? 'text-[#f5e6c4]/80' : 'text-text-muted',
              ]"
              >{{ item[2] }}</span
            ></span
          >
        </button>
      </nav>
      <main class="min-w-0 space-y-3" data-tour="page-content">
        <section v-if="section === 'general'" class="rounded-lg border bg-surface">
          <h2 class="border-b px-4 py-3 text-sm font-semibold">Edit Profile</h2>
          <div class="grid items-start gap-5 p-5 md:grid-cols-[auto_minmax(0,1fr)]">
            <div class="text-center">
              <DiceBearAvatar seed="admin@unifast.gov.ph" alt="System Administrator" :size="80" />
              <button class="mt-2 text-xs text-primary">Change avatar</button>
            </div>
            <form class="max-w-xl space-y-4" @submit.prevent>
              <label class="block"
                ><span class="mb-1.5 block text-xs font-medium">Full name *</span
                ><input
                  v-model="fullName"
                  class="h-9 w-full rounded-md border bg-surface px-3 text-sm"
              /></label>
              <label class="block"
                ><span class="mb-1.5 block text-xs font-medium">Email</span
                ><input
                  value="admin@unifast.gov.ph"
                  disabled
                  class="h-9 w-full rounded-md border bg-surface-muted px-3 text-sm text-text-muted"
                /><span class="mt-1 block text-micro text-text-muted"
                  >Contact support to change your email.</span
                ></label
              ><button class="rounded-md bg-primary px-3 py-2 text-xs font-medium text-white">
                Save profile
              </button>
            </form>
          </div>
        </section>
        <template v-else-if="section === 'organization'">
          <section class="rounded-lg border bg-surface">
            <h2 class="border-b px-4 py-3 text-sm font-semibold">Validation rules</h2>
            <div class="grid gap-4 p-4 md:grid-cols-3">
              <label class="block"
                ><span class="mb-1.5 block text-xs font-medium">Auto-approve risk threshold</span
                ><input
                  v-model.number="policy.auto_approve_risk_threshold"
                  type="number"
                  min="0"
                  max="100"
                  class="h-9 w-full rounded-md border bg-surface px-3 text-sm"
              /></label>
              <label class="block"
                ><span class="mb-1.5 block text-xs font-medium"
                  >Max failed/dropped subjects (overall)</span
                ><input
                  v-model.number="policy.max_failed_subjects_per_semester"
                  type="number"
                  min="0"
                  max="20"
                  class="h-9 w-full rounded-md border px-3 text-sm"
              /></label>
              <label class="block"
                ><span class="mb-1.5 block text-xs font-medium"
                  >Default pass grade (unknown programs)</span
                ><input
                  v-model="defaultPassGradeInput"
                  type="text"
                  inputmode="decimal"
                  placeholder="3.0"
                  class="h-9 w-full rounded-md border px-3 text-sm"
                  @blur="defaultPassGradeInput = toPassGradeDisplay(defaultPassGradeInput)"
              /></label>
              <label class="block"
                ><span class="mb-1.5 block text-xs font-medium"
                  >Identity face pass max (activate below)</span
                ><input
                  v-model.number="policy.identity_face_pass_max"
                  type="number"
                  min="0"
                  max="2"
                  step="0.01"
                  class="h-9 w-full rounded-md border px-3 text-sm"
              /></label>
              <label class="block"
                ><span class="mb-1.5 block text-xs font-medium"
                  >Identity face review max (block at/above)</span
                ><input
                  v-model.number="policy.identity_face_review_max"
                  type="number"
                  min="0"
                  max="2"
                  step="0.01"
                  class="h-9 w-full rounded-md border px-3 text-sm"
              /></label>
              <label class="block"
                ><span class="mb-1.5 block text-xs font-medium">Organization academic year</span
                ><input
                  v-model="policy.organization_academic_year"
                  type="text"
                  placeholder="2026-2027"
                  class="h-9 w-full rounded-md border px-3 text-sm"
              /></label>
            </div>
            <div class="flex items-center justify-between gap-3 border-t px-4 py-3">
              <p class="text-xs text-text-muted">
                Pass if grade ≤ pass grade; fail if higher. Retention is overall across full
                Course History (not per semester): each Failed and each Dropped counts 1 toward
                the max (default 3). Pending blanks do not count. Course History blanks on the
                Grade Slip term and any newer enrollment term are Pending; older-term blanks
                count as Dropped. Upload the last graded Grade Slip (not an empty
                current-enrollment slip). Grade Slip blanks are review-only. Not eligible when
                failed+dropped ≥ max (API key still max_failed_subjects_per_semester). Onboarding
                face: distance &lt; pass max activates; between pass and review max goes to staff
                Face Match Reviews; ≥ review max blocks. Vault still uses
                IDENTITY_FACE_MATCH_THRESHOLD. Organization academic year is compared to School ID
                back OCR (soft flag for staff Return — students are not blocked).
              </p>
              <button
                class="shrink-0 rounded-md bg-primary px-3 py-2 text-xs text-white disabled:opacity-60"
                :disabled="savingOrg || loadingOrg"
                @click="savePolicy"
              >
                {{ savingOrg ? "Saving…" : "Save rules" }}
              </button>
            </div>
          </section>

          <section class="rounded-lg border bg-surface">
            <div class="flex items-center justify-between gap-3 border-b px-4 py-3">
              <div>
                <h2 class="text-sm font-semibold">Program list</h2>
                <p class="mt-0.5 text-xs text-text-muted">
                  OCR matches Course History term headers (e.g. BSED Filipino — Year 1st, BSIT —
                  Year 2nd) to these codes for per-term pass grades.
                </p>
              </div>
              <button
                class="inline-flex items-center gap-1 rounded-md border px-3 py-1.5 text-xs font-medium hover:bg-surface-muted"
                @click="
                  showAddRow = !showAddRow;
                  editingId = null;
                "
              >
                <IconPlus :size="14" />Add program
              </button>
            </div>

            <div v-if="loadingOrg" class="px-4 py-6 text-xs text-text-muted">Loading programs…</div>
            <div v-else class="overflow-x-auto">
              <table class="w-full min-w-[640px] text-left text-sm">
                <thead class="border-b bg-surface-muted/60 text-xs uppercase tracking-wide text-text-muted">
                  <tr>
                    <th class="px-4 py-2.5 font-medium">Code</th>
                    <th class="px-4 py-2.5 font-medium">Full Name</th>
                    <th class="px-4 py-2.5 font-medium">Pass Grade</th>
                    <th class="px-4 py-2.5 font-medium">Action</th>
                  </tr>
                </thead>
                <tbody class="divide-y">
                  <tr v-if="showAddRow" class="bg-primary-soft/40">
                    <td class="px-4 py-2">
                      <input
                        v-model="newProgram.code"
                        placeholder="Code"
                        class="h-8 w-full rounded border bg-surface px-2 text-xs uppercase"
                      />
                    </td>
                    <td class="px-4 py-2">
                      <input
                        v-model="newProgram.name"
                        placeholder="Full name"
                        class="h-8 w-full rounded border bg-surface px-2 text-xs"
                      />
                    </td>
                    <td class="px-4 py-2">
                      <input
                        v-model="newProgram.pass_grade_input"
                        type="text"
                        inputmode="decimal"
                        placeholder="3.0"
                        class="h-8 w-20 rounded border bg-surface px-2 text-xs"
                        @blur="
                          newProgram.pass_grade_input = toPassGradeDisplay(
                            newProgram.pass_grade_input,
                          )
                        "
                      />
                    </td>
                    <td class="px-4 py-2">
                      <div class="flex items-center gap-2">
                        <button class="text-xs font-medium text-primary" @click="addProgram">
                          Save
                        </button>
                        <button
                          class="text-xs text-text-muted"
                          @click="
                            showAddRow = false;
                            newProgram = { code: '', name: '', pass_grade_input: '3.0' };
                          "
                        >
                          Cancel
                        </button>
                      </div>
                    </td>
                  </tr>
                  <tr v-for="program in programs" :key="program.id">
                    <template v-if="editingId === program.id">
                      <td class="px-4 py-2 font-medium">{{ program.code }}</td>
                      <td class="px-4 py-2">
                        <input
                          v-model="editDraft.name"
                          class="h-8 w-full rounded border px-2 text-xs"
                        />
                      </td>
                      <td class="px-4 py-2">
                        <input
                          v-model="editDraft.pass_grade_input"
                          type="text"
                          inputmode="decimal"
                          class="h-8 w-20 rounded border px-2 text-xs"
                          @blur="
                            editDraft.pass_grade_input = toPassGradeDisplay(
                              editDraft.pass_grade_input,
                            )
                          "
                        />
                      </td>
                      <td class="px-4 py-2">
                        <div class="flex items-center gap-2">
                          <button
                            class="text-xs font-medium text-primary"
                            @click="commitEdit(program)"
                          >
                            Save
                          </button>
                          <button class="text-xs text-text-muted" @click="cancelEdit">Cancel</button>
                          <button
                            class="text-xs text-danger"
                            @click="removeProgram(program)"
                          >
                            Delete
                          </button>
                        </div>
                      </td>
                    </template>
                    <template v-else>
                      <td class="px-4 py-2.5 font-medium">{{ program.code }}</td>
                      <td class="px-4 py-2.5 text-text">{{ program.name }}</td>
                      <td class="px-4 py-2.5 tabular-nums">{{ program.pass_grade_input }}</td>
                      <td class="px-4 py-2.5">
                        <button
                          class="text-xs font-medium text-primary hover:underline"
                          @click="startEdit(program)"
                        >
                          Edit
                        </button>
                      </td>
                    </template>
                  </tr>
                  <tr v-if="programs.length === 0 && !showAddRow">
                    <td colspan="4" class="px-4 py-6 text-center text-xs text-text-muted">
                      No programs yet. Add one to set pass grades for OCR.
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>
        </template>
        <template v-else-if="section === 'appearance'"
          ><section class="rounded-lg border bg-surface">
            <h2 class="border-b px-4 py-3 text-sm font-semibold">Theme</h2>
            <div class="p-4">
              <p class="mb-3 text-xs text-text-muted">
                Heritage Maroon — deep maroon, ivory, and muted gold.
              </p>
              <div class="grid gap-3 sm:grid-cols-2">
                <button
                  v-for="theme in [
                    [false, 'Light', IconSun, ['#f6f7f9', '#fff', '#4a141d', '#b8894a']],
                    [true, 'Dark', IconMoon, ['#17110f', '#1f1815', '#2a0e14', '#d1a15c']],
                  ]"
                  :key="theme[1] as string"
                  :class="[
                    'rounded-md border p-3 text-left',
                    dark === theme[0] ? 'border-primary bg-primary-soft' : 'hover:bg-surface-muted',
                  ]"
                  @click="setTheme(theme[0] as boolean)"
                >
                  <div class="flex justify-between">
                    <span class="inline-flex items-center gap-2 text-sm font-medium"
                      ><component :is="theme[2]" :size="16" />{{ theme[1] }}</span
                    ><IconCheck v-if="dark === theme[0]" :size="16" class="text-primary" />
                  </div>
                  <div class="mt-3 flex h-8 overflow-hidden rounded border">
                    <i
                      v-for="color in theme[3]"
                      :key="color as string"
                      class="flex-1"
                      :style="{ backgroundColor: color as string }"
                    />
                  </div>
                </button>
              </div>
            </div>
          </section>
          <section class="rounded-lg border bg-surface">
            <h2 class="border-b px-4 py-3 text-sm font-semibold">Font family</h2>
            <div class="p-4">
              <label class="block"
                ><span class="mb-1.5 block text-xs font-medium">Font</span
                ><select class="h-9 w-full max-w-sm rounded-md border bg-surface px-3 text-sm">
                  <option>Absans</option>
                  <option>Inter</option>
                  <option>IBM Plex Sans</option>
                </select></label
              >
              <p class="mt-2 text-xs text-text-muted">
                The original institutional typeface used across the workspace.
              </p>
            </div>
          </section>
          <section class="rounded-lg border bg-surface p-4">
            <h2 class="text-sm font-semibold">Typography preview</h2>
            <p class="mt-4 text-2xs uppercase text-text-soft">Page title · 20/28 semibold</p>
            <p class="mt-1 text-xl font-semibold">Government scholarship applications</p>
            <p class="mt-4 text-2xs uppercase text-text-soft">Body · 14/20 regular</p>
            <p class="mt-1 text-sm">
              The Tertiary Education Subsidy covers tuition and other school fees for qualified
              Filipino students.
            </p>
          </section></template
        >
        <template v-else-if="section === 'security'">
        <section class="rounded-lg border bg-surface">
          <div class="flex items-center justify-between border-b px-4 py-3">
            <div>
              <h2 class="text-sm font-semibold">Two-Factor Authentication</h2>
              <p class="mt-0.5 text-xs text-text-muted">
                Require an authenticator code after password or Google sign-in.
              </p>
            </div>
            <span
              :class="[
                'rounded-full px-2.5 py-1 text-micro font-semibold uppercase',
                twoFactor.enabled ? 'bg-success/10 text-success' : 'bg-surface-muted text-text-muted',
              ]"
            >
              {{ twoFactor.enabled ? "Enabled" : "Off" }}
            </span>
          </div>
          <div v-if="loadingTwoFactor" class="p-4 text-xs text-text-muted">Loading security settings...</div>
          <div v-else class="space-y-4 p-4">
            <div v-if="!twoFactor.enabled && !twoFactorSetup" class="flex flex-wrap items-center justify-between gap-3">
              <div>
                <p class="text-sm font-medium">Authenticator app</p>
                <p class="mt-1 text-xs text-text-muted">
                  Use Google Authenticator, Microsoft Authenticator, 1Password, or any TOTP app.
                </p>
              </div>
              <button
                class="rounded-md bg-primary px-3 py-2 text-xs font-medium text-white disabled:opacity-60"
                :disabled="savingTwoFactor"
                @click="beginTwoFactorSetup"
              >
                Set up 2FA
              </button>
            </div>

            <div v-if="twoFactorSetup" class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_260px]">
              <div class="space-y-3">
                <label class="block">
                  <span class="mb-1.5 block text-xs font-medium">Manual setup key</span>
                  <input
                    :value="twoFactorSetup.secret"
                    readonly
                    class="h-9 w-full rounded-md border bg-surface-muted px-3 font-mono text-xs"
                  />
                </label>
                <label class="block">
                  <span class="mb-1.5 block text-xs font-medium">Current six-digit code</span>
                  <input
                    v-model="twoFactorCode"
                    type="text"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    placeholder="123456"
                    class="h-9 w-full rounded-md border px-3 text-sm"
                  />
                </label>
                <div class="flex gap-2">
                  <button
                    class="rounded-md bg-primary px-3 py-2 text-xs font-medium text-white disabled:opacity-60"
                    :disabled="savingTwoFactor"
                    @click="confirmTwoFactorSetup"
                  >
                    Verify and enable
                  </button>
                  <button
                    class="rounded-md border px-3 py-2 text-xs font-medium hover:bg-surface-muted"
                    @click="twoFactorSetup = null"
                  >
                    Cancel
                  </button>
                </div>
              </div>
              <div class="rounded-md border bg-surface-muted p-3">
                <p class="text-xs font-semibold">Authenticator URI</p>
                <p class="mt-2 break-all font-mono text-micro text-text-muted">
                  {{ twoFactorSetup.otpauth_uri }}
                </p>
              </div>
            </div>

            <div v-if="recoveryCodes.length" class="rounded-md border border-warning/40 bg-warning/10 p-3">
              <p class="text-xs font-semibold text-text">Save these recovery codes now.</p>
              <div class="mt-2 grid gap-2 sm:grid-cols-2">
                <code
                  v-for="code in recoveryCodes"
                  :key="code"
                  class="rounded border bg-white px-2 py-1 text-xs"
                >
                  {{ code }}
                </code>
              </div>
            </div>

            <div v-if="twoFactor.enabled" class="space-y-3">
              <p class="text-sm">
                Your account requires a second factor. Recovery codes remaining:
                <b>{{ twoFactor.recovery_codes_remaining }}</b>
              </p>
              <label class="block max-w-sm">
                <span class="mb-1.5 block text-xs font-medium">Current password to disable</span>
                <input
                  v-model="twoFactorDisablePassword"
                  type="password"
                  placeholder="Password"
                  class="h-9 w-full rounded-md border px-3"
                />
              </label>
              <button
                class="rounded-md border border-danger/40 px-3 py-2 text-xs font-medium text-danger hover:bg-danger/10 disabled:opacity-60"
                :disabled="savingTwoFactor"
                @click="turnOffTwoFactor"
              >
                Disable 2FA
              </button>
            </div>
          </div>
        </section>

        <section class="rounded-lg border bg-surface">
          <div class="flex items-center justify-between border-b px-4 py-3">
            <h2 class="text-sm font-semibold">Change Password</h2>
            <button class="inline-flex items-center gap-1 text-xs" @click="signOut">
              <IconLogout :size="13" />Sign out
            </button>
          </div>
          <form class="max-w-xl space-y-4 p-4" @submit.prevent>
            <label
              v-for="label in ['Current password', 'New password', 'Confirm new password']"
              :key="label"
              class="block"
              ><span class="mb-1.5 block text-xs font-medium">{{ label }} *</span
              ><input
                type="password"
                placeholder="••••••••"
                class="h-9 w-full rounded-md border px-3"
            /></label>
            <div>
              <div class="flex justify-between text-micro">
                <span class="text-text-muted">Strength</span><span>Strong</span>
              </div>
              <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-surface-muted">
                <div class="h-full w-4/5 bg-success" />
              </div>
            </div>
            <button class="rounded-md bg-primary px-3 py-2 text-xs text-white">
              Update password
            </button>
          </form>
        </section>
        </template>
        <template v-else
          ><section class="rounded-lg border bg-surface">
            <div class="flex justify-between border-b px-4 py-3">
              <h2 class="text-sm font-semibold">Current Session</h2>
              <button class="inline-flex items-center gap-1 text-xs" @click="signOut">
                <IconLogout :size="13" />Sign out
              </button>
            </div>
            <div class="flex gap-3 p-4">
              <span
                class="grid h-10 w-10 place-items-center rounded-md bg-primary-soft text-primary"
                ><IconShieldCheck :size="18"
              /></span>
              <div>
                <p class="text-sm font-medium">System Administrator</p>
                <p class="text-micro text-text-muted">admin@unifast.gov.ph</p>
                <p class="mt-1 text-micro text-text-muted">
                  This device: <b class="text-text">Windows PC</b>
                </p>
              </div>
            </div>
          </section>
          <section class="rounded-lg border bg-surface">
            <div class="flex justify-between border-b px-4 py-3">
              <h2 class="text-sm font-semibold">Login Activity</h2>
              <button class="text-xs text-primary">Export CSV</button>
            </div>
            <div class="divide-y px-4">
              <div
                v-for="event in [
                  ['Windows PC', 'Jul 11, 2026, 7:41 PM', '192.168.1.14'],
                  ['Windows PC', 'Jul 10, 2026, 8:16 AM', '192.168.1.14'],
                  ['Android device', 'Jul 8, 2026, 6:35 PM', '192.168.1.22'],
                ]"
                :key="event[1]"
                class="flex items-center gap-3 py-3"
              >
                <span
                  class="grid h-8 w-8 place-items-center rounded-md bg-surface-muted text-text-muted"
                  ><IconDeviceLaptop :size="14"
                /></span>
                <div>
                  <p class="text-sm font-medium">{{ event[0] }}</p>
                  <p class="text-micro text-text-muted">{{ event[1] }} · {{ event[2] }}</p>
                </div>
              </div>
            </div>
          </section></template
        >
      </main>
    </div>
  </div>
</template>
