<script setup lang="ts">
import { ref } from "vue";
import { useRoute } from "vue-router";
import {
  IconArrowLeft,
  IconCheck,
  IconFileText,
  IconHistory,
  IconNote,
  IconSchool,
  IconUser,
} from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";
import DiceBearAvatar from "@/components/ui/DiceBearAvatar.vue";
import CardSkeleton from "@/components/ui/CardSkeleton.vue";
import EmptyState from "@/components/ui/EmptyState.vue";
import { useGranteeDetail } from "@/composables/useGrantees";
import { toast } from "@/composables/useToast";

const route = useRoute();
const tab = ref("overview");
const notes = ref("");

const { grantee, query: detailQuery } = useGranteeDetail(String(route.params.id));

function saveNotes() {
  toast.success("Notes saved locally for this session");
}
</script>

<template>
  <div>
    <RouterLink
      to="/app/grantees"
      class="mb-3 inline-flex items-center gap-1 text-xs text-text-muted"
    >
      <IconArrowLeft :size="13" />Back to grantees
    </RouterLink>

    <template v-if="grantee">
      <PageHeader
        :title="grantee.name"
        :description="`${grantee.student_number || grantee.student_id} · ${grantee.program}`"
      />
      <section class="mb-4 flex flex-wrap items-center gap-4 rounded-lg border bg-surface p-4">
        <DiceBearAvatar :seed="grantee.email" :alt="grantee.name" :size="56" />
        <div class="min-w-0 flex-1">
          <p class="text-sm font-semibold">{{ grantee.email }}</p>
          <p class="text-xs text-text-muted">
            {{ grantee.contact || "No contact on file" }}
            · {{ grantee.year_level || "Year level not set" }}
          </p>
        </div>
        <div class="grid grid-cols-3 gap-5 text-center">
          <div>
            <p class="text-lg font-semibold">{{ grantee.gwa || "\u2014" }}</p>
            <p class="text-micro text-text-muted">GWA</p>
          </div>
          <div>
            <p class="text-lg font-semibold capitalize">{{ grantee.account.replaceAll("_", " ") }}</p>
            <p class="text-micro text-text-muted">Account</p>
          </div>
          <div>
            <p class="text-lg font-semibold capitalize">{{ grantee.risk }}</p>
            <p class="text-micro text-text-muted">Risk</p>
          </div>
        </div>
      </section>

      <div class="mb-4 flex gap-1 border-b">
        <button
          v-for="item in [
            ['overview', 'Overview', IconUser],
            ['academic', 'Academic', IconSchool],
            ['documents', 'Documents', IconFileText],
            ['notes', 'Notes', IconNote],
          ] as const"
          :key="item[0]"
          :class="[
            'inline-flex items-center gap-1.5 border-b-2 px-3 py-2 text-xs',
            tab === item[0]
              ? 'border-primary text-primary'
              : 'border-transparent text-text-muted',
          ]"
          @click="tab = item[0]"
        >
          <component :is="item[2]" :size="14" />{{ item[1] }}
        </button>
      </div>

      <section v-if="tab === 'overview'" class="grid gap-4 md:grid-cols-2">
        <article class="rounded-lg border bg-surface p-4 text-xs">
          <h2 class="mb-3 text-sm font-semibold">Profile</h2>
          <dl class="space-y-2 text-text-muted">
            <div class="flex justify-between gap-2">
              <dt>University</dt>
              <dd class="font-medium text-text">{{ grantee.university }}</dd>
            </div>
            <div class="flex justify-between gap-2">
              <dt>Batch</dt>
              <dd class="font-medium text-text">{{ grantee.batch || "\u2014" }}</dd>
            </div>
            <div class="flex justify-between gap-2">
              <dt>Submission</dt>
              <dd class="font-medium capitalize text-text">
                {{ grantee.submission.replaceAll("_", " ") }}
              </dd>
            </div>
            <div class="flex justify-between gap-2">
              <dt>Eligibility</dt>
              <dd class="font-medium capitalize text-text">{{ grantee.eligibility }}</dd>
            </div>
          </dl>
        </article>
        <article class="rounded-lg border bg-surface p-4 text-xs">
          <h2 class="mb-3 flex items-center gap-1.5 text-sm font-semibold">
            <IconHistory :size="15" />Status
          </h2>
          <ul class="space-y-2 text-text-muted">
            <li class="flex items-center gap-2">
              <IconCheck :size="14" class="text-success" />Account {{ grantee.account }}
            </li>
            <li class="flex items-center gap-2">
              <IconCheck :size="14" class="text-success" />Submission {{ grantee.submission }}
            </li>
          </ul>
        </article>
      </section>

      <section v-else-if="tab === 'academic'" class="rounded-lg border bg-surface p-4 text-sm">
        <p class="text-text-muted">
          Latest GWA: <span class="font-semibold text-text">{{ grantee.gwa || "\u2014" }}</span>
        </p>
        <RouterLink
          v-if="grantee.id"
          :to="`/app/academic`"
          class="mt-3 inline-block text-xs text-primary"
        >
          Open academic records
        </RouterLink>
      </section>

      <section v-else-if="tab === 'documents'" class="rounded-lg border bg-surface p-4 text-sm">
        <p class="text-text-muted">
          Review submissions from the document validation queue filtered by this student.
        </p>
        <RouterLink to="/app/documents" class="mt-3 inline-block text-xs text-primary">
          Open validation queue
        </RouterLink>
      </section>

      <section v-else class="rounded-lg border bg-surface p-4">
        <textarea
          v-model="notes"
          class="min-h-28 w-full rounded-md border p-3 text-xs"
          placeholder="Staff notes"
        />
        <button
          class="mt-3 rounded-md bg-primary px-3 py-2 text-xs text-white"
          @click="saveNotes"
        >
          Save notes
        </button>
      </section>
    </template>

    <div v-else-if="detailQuery.isLoading.value" class="space-y-4">
      <CardSkeleton :lines="2" />
      <CardSkeleton :lines="5" />
    </div>
    <EmptyState
      v-else-if="detailQuery.isError.value"
      variant="error"
      title="Couldn't load grantee"
      :hint="
        detailQuery.error.value instanceof Error
          ? detailQuery.error.value.message
          : 'Unable to load grantee.'
      "
      @retry="detailQuery.refetch()"
    />
  </div>
</template>
