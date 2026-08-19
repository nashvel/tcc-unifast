<script setup lang="ts">
import { apiFetch } from "@/api/client";
import { onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import PageHeader from "@/components/ui/PageHeader.vue";
import CardSkeleton from "@/components/ui/CardSkeleton.vue";
import { authSession } from "@/auth/session";

const router = useRouter();
const error = ref("");

onMounted(async () => {
  try {
    const accountStatus = authSession.user?.account_status;
    if (accountStatus === "unverified" || accountStatus === "pending_kyc") {
      await router.replace("/student/kyc");
      return;
    }

    const payload = await apiFetch<{ data: { next_step: string } }>("/api/student/identity-onboarding");
    const next = payload.data.next_step as string;
    if (next === "kyc") await router.replace("/student/kyc");
    else if (next === "liveness") await router.replace("/student/onboarding/liveness");
    else if (next === "face_review") await router.replace("/student/onboarding/pending-review");
    else if (next === "done") await router.replace("/student");
    else await router.replace("/student/onboarding/id-scan");
  } catch (exception) {
    error.value = exception instanceof Error ? exception.message : "Unable to start identity onboarding.";
  }
});
</script>

<template>
  <div>
    <PageHeader title="Identity onboarding" description="Preparing your next verification step…" />
    <CardSkeleton v-if="!error" :lines="4" />
    <p v-else class="rounded-md border border-danger/30 bg-danger-soft p-3 text-xs text-danger">{{ error }}</p>
  </div>
</template>

