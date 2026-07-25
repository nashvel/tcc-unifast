<script setup lang="ts">
import { computed, ref } from "vue";
import { useRoute } from "vue-router";
import { useQueryClient } from "@tanstack/vue-query";
import {
  IconArrowLeft,
  IconCalendarDue,
  IconLock,
  IconMail,
  IconPower,
  IconRefresh,
  IconUserPlus,
} from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import DataTable from "@/components/tables/DataTable.vue";
import AppDialog from "@/components/dialogs/AppDialog.vue";
import CardSkeleton from "@/components/ui/CardSkeleton.vue";
import EmptyState from "@/components/ui/EmptyState.vue";
import { useBatchDetail, useBatchAction } from "@/composables/useBatches";
import { toast } from "@/composables/useToast";
import { scheduleUndo } from "@/composables/useUndo";

const route = useRoute();
const extendDialog = ref(false);
const newDeadline = ref("");
const mailResult = ref("");
const pendingAction = ref("");
const resending = ref(false);

const { batch, query: batchQuery } = useBatchDetail(String(route.params.id));
const actionMutation = useBatchAction(String(route.params.id));
const queryClient = useQueryClient();

async function runAction(path: string, label: string, body?: Record<string, string>) {
  if (!batch.value || pendingAction.value) return;

  const destructive = path === "deactivate";
  if (destructive) {
    const previous = { ...batch.value };
    pendingAction.value = label;
    await scheduleUndo(`batch-${path}-${batch.value.id}`, {
      message: "Closing submission window\u2026",
      description: "Undo within 5 seconds to keep the window open.",
      optimistic: () => {
        queryClient.setQueryData(
          ["batches", String(route.params.id)],
          {
            ...previous,
            is_active: false,
            window_status: "closed" as const,
          },
        );
        return () => {
          queryClient.setQueryData(
            ["batches", String(route.params.id)],
            previous,
          );
        };
      },
      commit: async () => {
        const payload = await actionMutation.mutateAsync({ path, body });
        toast.success("Submission window closed");
        return payload;
      },
      onUndo: () => toast.info("Window close cancelled"),
    });
    pendingAction.value = "";
    return;
  }

  pendingAction.value = label;
  try {
    await actionMutation.mutateAsync({ path, body });
    toast.success(
      path === "activate"
        ? "Submission window activated"
        : path === "extend-deadline"
          ? "Deadline extended"
          : "Batch updated",
    );
  } catch (error) {
    toast.error(error instanceof Error ? error.message : "Batch action failed.");
  } finally {
    pendingAction.value = "";
  }
}

async function extend(close: () => void) {
  await runAction("extend-deadline", "extend", { submission_deadline: newDeadline.value });
  if (!actionMutation.isError.value) close();
}

function statusClass(status: string) {
  if (status === "active") return "bg-success-soft text-success";
  if (status === "expired") return "bg-danger-soft text-danger";
  if (status === "closed") return "bg-warning-soft text-warning";
  return "bg-surface-muted text-text-muted";
}

async function resendInvites() {
  if (!batch.value || resending.value) return;
  resending.value = true;
  try {
    const payload = await apiFetch<{ sent: number; failed: unknown[] }>(
      `/api/batches/${route.params.id}/activation-notifications`,
      { method: "POST", body: JSON.stringify({}) },
    );
    mailResult.value = `${payload.sent} activation invite(s) resent, ${payload.failed.length} failed.`;
    toast.success("Activation invites resent");
  } catch (error) {
    toast.error(error instanceof Error ? error.message : "Unable to resend invites.");
  } finally {
    resending.value = false;
  }
}
</script>

<template>
  <div>
    <RouterLink
      to="/app/batches"
      class="mb-3 inline-flex items-center gap-1 text-xs text-text-muted hover:text-primary"
    >
      <IconArrowLeft :size="14" />Back to batches
    </RouterLink>

    <PageHeader
      :title="batch?.name || 'Batch'"
      :description="
        batch ? `${batch.academic_year} - ${batch.semester}` : 'Loading batch details'
      "
    >
      <template v-if="batch" #actions>
        <button
          v-if="batch.window_status !== 'active'"
          class="inline-flex h-9 items-center gap-1.5 rounded-md bg-primary px-3 text-xs text-white disabled:opacity-60"
          :disabled="Boolean(pendingAction)"
          @click="runAction('activate', 'activate')"
        >
          <IconPower :size="14" />Activate window
        </button>
        <button
          v-else
          class="inline-flex h-9 items-center gap-1.5 rounded-md border px-3 text-xs disabled:opacity-60"
          :disabled="Boolean(pendingAction)"
          @click="runAction('deactivate', 'deactivate')"
        >
          <IconLock :size="14" />Close window
        </button>
        <button
          class="inline-flex h-9 items-center gap-1.5 rounded-md border px-3 text-xs"
          @click="
            newDeadline = batch.submission_deadline ? batch.submission_deadline.slice(0, 16) : '';
            extendDialog = true;
          "
        >
          <IconRefresh :size="14" />Extend deadline
        </button>
        <button
          class="inline-flex h-9 items-center gap-1.5 rounded-md border px-3 text-xs disabled:opacity-60"
          :disabled="resending"
          @click="resendInvites"
        >
          <IconMail :size="14" />{{ resending ? "Sending..." : "Resend invites" }}
        </button>
      </template>
    </PageHeader>

    <div v-if="batchQuery.isLoading.value" class="space-y-4">
      <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <CardSkeleton v-for="i in 4" :key="i" :lines="2" />
      </div>
      <CardSkeleton :lines="6" />
    </div>
    <EmptyState
      v-else-if="batchQuery.isError.value"
      variant="error"
      title="Couldn't load batch"
      :hint="
        batchQuery.error.value instanceof Error
          ? batchQuery.error.value.message
          : 'Unable to load batch.'
      "
      @retry="batchQuery.refetch()"
    />

    <p v-if="mailResult" class="mb-4 rounded-md border bg-surface-muted p-3 text-xs text-text-muted">
      <IconMail :size="14" class="mr-1 inline text-primary" />{{ mailResult }}
    </p>

    <template v-if="batch">
      <section class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <article class="rounded-lg border bg-surface p-4">
          <p class="text-xs text-text-muted">Window status</p>
          <p
            :class="[
              'mt-1 w-fit rounded-full px-2 py-1 text-xs font-semibold',
              statusClass(batch.window_status),
            ]"
          >
            {{ batch.window_status }}
          </p>
        </article>
        <article class="rounded-lg border bg-surface p-4">
          <p class="text-xs text-text-muted">Grantees</p>
          <p class="mt-1 text-xl font-semibold">{{ batch.grantees_count }}</p>
        </article>
        <article class="rounded-lg border bg-surface p-4 lg:col-span-2">
          <p class="flex items-center gap-1 text-xs text-text-muted">
            <IconCalendarDue :size="14" />Submission deadline
          </p>
          <p class="mt-1 text-sm font-semibold">
            {{
              batch.submission_deadline
                ? new Date(batch.submission_deadline).toLocaleString()
                : "No deadline set"
            }}
          </p>
        </article>
      </section>

      <DataTable :headings="['Student ID', 'Student name', 'Email', 'Program', 'Account', 'Grantee']">
        <tr v-for="member in batch.grantees" :key="member.id">
          <td class="px-3 py-3 font-mono">{{ member.student_id }}</td>
          <td class="px-3 py-3 font-medium">{{ member.full_name }}</td>
          <td class="px-3 py-3 text-text-muted">{{ member.email }}</td>
          <td class="px-3 py-3 text-text-muted">{{ member.program }}</td>
          <td class="px-3 py-3 capitalize">{{ member.account_status || "not linked" }}</td>
          <td class="px-3 py-3 capitalize">{{ member.status }}</td>
        </tr>
        <tr v-if="!batch.grantees.length">
          <td colspan="6" class="px-3 py-8 text-center text-text-muted">
            No grantees assigned to this batch yet.
          </td>
        </tr>
      </DataTable>

      <section class="mt-4 rounded-lg border bg-surface p-4">
        <h2 class="flex items-center gap-2 text-sm font-semibold">
          <IconUserPlus :size="16" /> Add grantees
        </h2>
        <p class="mt-1 text-xs text-text-muted">
          First-time import and activation:
          <RouterLink class="text-primary hover:underline" to="/app/onboarding">
            Onboarding Center
          </RouterLink>
          . To review stored records or upload an updated masterlist, use
          <RouterLink class="text-primary hover:underline" to="/app/masterlist">
            Masterlist
          </RouterLink>
          .
        </p>
      </section>
    </template>

    <AppDialog
      v-model="extendDialog"
      title="Extend submission deadline"
      description="Set a future deadline. If this batch is active, students regain upload access until the new deadline."
      size="sm"
    >
      <label class="block text-xs font-medium">
        New deadline
        <input
          v-model="newDeadline"
          type="datetime-local"
          class="mt-1.5 h-10 w-full rounded-md border px-3 text-sm"
        />
      </label>
      <template #footer="{ close }">
        <button class="rounded-md border px-4 py-2 text-xs" @click="close">Cancel</button>
        <button
          class="rounded-md bg-primary px-4 py-2 text-xs text-white disabled:opacity-60"
          :disabled="pendingAction === 'extend'"
          @click="extend(close)"
        >
          {{ pendingAction === "extend" ? "Saving..." : "Extend deadline" }}
        </button>
      </template>
    </AppDialog>
  </div>
</template>
