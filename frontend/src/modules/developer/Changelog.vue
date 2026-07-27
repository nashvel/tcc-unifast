<script setup lang="ts">
import { ref, computed } from "vue";
import { useI18n } from "vue-i18n";
import { useQuery } from "@tanstack/vue-query";
import { apiFetch } from "@/api/client";
import { GitCommit, GitBranch, Github, ExternalLink, Loader2, Activity, Calendar, RefreshCcw } from "lucide-vue-next";
import { isMockMode } from "@/api/client";
import PageHeader from "@/components/ui/PageHeader.vue";
import TablePagination from "@/components/tables/TablePagination.vue";
import type { PaginationMeta } from "@/api";

const isLocalDev = import.meta.env.DEV;
const { t } = useI18n();

const isRefreshing = ref(false);

type GithubCommitInfo = {
  sha: string;
  html_url: string;
  commit: {
    message: string;
    author: {
      name: string;
      date: string;
    };
  };
  author?: {
    avatar_url: string;
    login: string;
    html_url: string;
  };
};

type ChangelogResponse = {
  data: GithubCommitInfo[];
  repo: string;
  has_token: boolean;
};

const { data: changelog, isPending, error, refetch } = useQuery({
  queryKey: ["changelogs"],
  queryFn: async (): Promise<ChangelogResponse> => {
    const url = isRefreshing.value ? '/api/changelogs?refresh=1' : '/api/changelogs';
    const res = await apiFetch(url);
    if (!res.ok) {
      throw new Error("Failed to fetch changelog");
    }
    
    // Reset refreshing state after successful fetch
    isRefreshing.value = false;
    
    return res.json();
  },
});

function formatDate(dateString: string) {
  const date = new Date(dateString);
  return date.toLocaleString(undefined, {
    year: 'numeric', month: 'short', day: 'numeric',
    hour: '2-digit', minute: '2-digit'
  });
}

// Client-side pagination
const currentPage = ref(1);
const perPage = 10;

const handleRefresh = async () => {
  isRefreshing.value = true;
  await refetch();
};

const totalCommits = computed(() => changelog.value?.data?.length || 0);
const totalPages = computed(() => Math.ceil(totalCommits.value / perPage));

const paginatedCommits = computed(() => {
  if (!changelog.value?.data) return [];
  const start = (currentPage.value - 1) * perPage;
  const end = start + perPage;
  return changelog.value.data.slice(start, end);
});

const paginationMeta = computed<PaginationMeta>(() => {
  const total = totalCommits.value;
  const from = total === 0 ? 0 : (currentPage.value - 1) * perPage + 1;
  const to = Math.min(currentPage.value * perPage, total);
  
  return {
    current_page: currentPage.value,
    last_page: totalPages.value || 1,
    per_page: perPage,
    total: total,
    from,
    to,
    links: []
  };
});
</script>

<template>
  <div class="space-y-4">
    <PageHeader title="System Change Logs" description="Live commit history from GitHub">
      <template #actions>
        <button
          @click="handleRefresh"
          :disabled="isPending"
          class="inline-flex h-9 items-center gap-1.5 rounded-md border border-border bg-surface px-3 text-xs font-medium text-text hover:bg-surface-muted transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <RefreshCcw :size="14" :class="{ 'animate-spin': isPending }" />
          Refresh
        </button>
      </template>
    </PageHeader>

      <div v-if="!changelog?.has_token" class="mb-6 flex items-start gap-4 rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-900/50 dark:bg-amber-900/20">
        <div class="mt-0.5 rounded-full bg-amber-100 p-1.5 dark:bg-amber-900/40">
          <Activity class="h-4 w-4 text-amber-600 dark:text-amber-500" />
        </div>
        <div>
          <h3 class="text-sm font-semibold text-amber-800 dark:text-amber-500">GitHub API Token Missing</h3>
          <p class="mt-1 text-sm text-amber-700 dark:text-amber-400">
            <template v-if="isMockMode || isLocalDev">
              To fetch real commits locally without hitting GitHub's 60-request hourly rate limit, add <code>VITE_GITHUB_TOKEN</code> to your local <code>.env</code> file.
            </template>
            <template v-else>
              To avoid strict rate limiting or to fetch from a private repository, please add <code>GITHUB_TOKEN</code> to your Vercel Environment Variables.
            </template>
          </p>
        </div>
      </div>

    <!-- KPIs -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
      <div class="flex items-center gap-4 rounded-xl border border-border bg-surface p-4 shadow-sm">
        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-surface-muted text-primary">
          <Activity :size="20" />
        </div>
        <div>
          <p class="text-xs font-medium text-text-muted">Total Fetched</p>
          <p class="text-xl font-bold text-text">{{ totalCommits }} Commits</p>
        </div>
      </div>
      
      <div class="flex items-center gap-4 rounded-xl border border-border bg-surface p-4 shadow-sm">
        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-surface-muted text-primary">
          <Calendar :size="20" />
        </div>
        <div class="min-w-0 flex-1">
          <p class="text-xs font-medium text-text-muted">Latest Commit</p>
          <p class="truncate text-sm font-bold text-text">
            {{ changelog?.data?.[0] ? formatDate(changelog.data[0].commit.author.date) : 'N/A' }}
          </p>
        </div>
      </div>

      <div class="flex items-center gap-4 rounded-xl border border-border bg-surface p-4 shadow-sm">
        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-surface-muted text-primary">
          <GitBranch :size="20" />
        </div>
        <div class="min-w-0 flex-1">
          <p class="text-xs font-medium text-text-muted">Repository</p>
          <p class="truncate text-sm font-bold text-text">
            <template v-if="changelog?.repo">
              <a v-if="!isMockMode" :href="'https://github.com/' + changelog.repo" target="_blank" class="hover:text-primary hover:underline">
                {{ changelog.repo }}
              </a>
              <span v-else class="cursor-not-allowed opacity-80">{{ changelog.repo }}</span>
            </template>
            <span v-else>Loading...</span>
          </p>
        </div>
      </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-border bg-surface shadow-sm">
      <div v-if="isPending" class="flex min-h-[200px] items-center justify-center">
        <Loader2 :size="24" class="animate-spin text-text-muted" />
      </div>

      <div v-else-if="error" class="flex min-h-[200px] flex-col items-center justify-center text-center text-text-muted">
        <p class="text-sm">Failed to load changelog. Check API connection.</p>
      </div>

      <div v-else-if="paginatedCommits.length" class="divide-y divide-border">
        <div v-for="commitInfo in paginatedCommits" :key="commitInfo.sha" class="flex gap-3 px-5 py-3 hover:bg-surface-muted transition-colors">
          <!-- Avatar -->
          <div class="flex-shrink-0 pt-0.5">
            <template v-if="commitInfo.author">
              <a v-if="!isMockMode" :href="commitInfo.author.html_url" target="_blank">
                <img :src="commitInfo.author.avatar_url" class="h-8 w-8 rounded-full border border-border shadow-xs" alt="Author" />
              </a>
              <span v-else class="cursor-not-allowed">
                <img :src="commitInfo.author.avatar_url" class="h-8 w-8 rounded-full border border-border shadow-xs opacity-80" alt="Author" />
              </span>
            </template>
            <div v-else class="flex h-8 w-8 items-center justify-center rounded-full bg-surface-muted text-text-soft border border-border shadow-xs">
              <Github :size="16" />
            </div>
          </div>

          <!-- Content -->
          <div class="flex-1 min-w-0">
            <div class="flex items-start justify-between gap-4">
              <h4 class="truncate text-sm font-medium text-text">
                <a v-if="!isMockMode" :href="commitInfo.html_url" target="_blank" class="hover:text-primary hover:underline">
                  {{ commitInfo.commit.message.split('\n')[0] }}
                </a>
                <span v-else class="cursor-not-allowed opacity-90">
                  {{ commitInfo.commit.message.split('\n')[0] }}
                </span>
              </h4>
              <a v-if="!isMockMode" :href="commitInfo.html_url" target="_blank" class="flex-shrink-0 text-xs font-mono text-text-muted hover:text-primary">
                {{ commitInfo.sha.substring(0, 7) }}
                <ExternalLink :size="12" class="inline-block ml-0.5 -mt-0.5" />
              </a>
              <span v-else class="flex-shrink-0 text-xs font-mono text-text-muted cursor-not-allowed opacity-80">
                {{ commitInfo.sha.substring(0, 7) }}
              </span>
            </div>
            
            <div class="mt-0.5 flex items-center gap-1.5 text-xs text-text-muted">
              <span class="font-medium text-text">
                {{ commitInfo.commit.author.name }}
              </span>
              <span>committed on</span>
              <span>{{ formatDate(commitInfo.commit.author.date) }}</span>
            </div>
            
            <div v-if="commitInfo.commit.message.split('\n').length > 1" class="mt-2 rounded-md bg-surface-muted p-2 text-xs text-text-soft whitespace-pre-wrap border border-border">
              {{ commitInfo.commit.message.split('\n').slice(1).join('\n').trim() }}
            </div>
          </div>
        </div>
        
        <TablePagination
          v-model:page="currentPage"
          :meta="paginationMeta"
          :busy="isPending"
        />
      </div>

      <div v-else class="flex min-h-[200px] flex-col items-center justify-center text-center text-text-muted">
        <GitCommit :size="32" class="mb-3 text-slate-300" />
        <p class="text-sm">No commits found or API rate limited.</p>
      </div>
    </div>
  </div>
</template>
