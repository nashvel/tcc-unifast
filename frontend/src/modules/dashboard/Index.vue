<script setup lang="ts">
import { computed } from "vue";
import { authSession } from "@/auth/session";
import AdminDashboard from "./AdminDashboard.vue";
import DeveloperDashboard from "./DeveloperDashboard.vue";
import StudentDashboard from "./StudentDashboard.vue";

const role = computed(() => authSession.user?.role);
</script>

<template>
  <DeveloperDashboard v-if="role === 'developer'" />
  <AdminDashboard v-else-if="role === 'admin' || role === 'staff'" />
  <StudentDashboard v-else />
</template>
