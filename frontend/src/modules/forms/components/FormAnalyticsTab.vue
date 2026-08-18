<script setup lang="ts">
import { computed } from 'vue';
import { useFormAnalytics } from '@/composables/useForms';
import type { FormDetail } from '@/api/types';
import {
  IconUsers,
  IconUserCheck,
  IconUserQuestion,
  IconChartLine,
  IconAsterisk,
  IconLayoutList
} from '@tabler/icons-vue';
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend,
  ArcElement,
  Filler
} from 'chart.js';
import { Line, Doughnut } from 'vue-chartjs';
import Skeleton from '@/components/ui/Skeleton.vue';
import EmptyState from '@/components/ui/EmptyState.vue';

// Register Chart.js components
ChartJS.register(
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend,
  ArcElement,
  Filler
);

const props = defineProps<{
  form: FormDetail;
}>();

const { data: analytics, isLoading, isError } = useFormAnalytics(computed(() => props.form.id));

// ─────────────────────────────────────────────────────────────────
// Chart Data & Configs
// ─────────────────────────────────────────────────────────────────

const lineChartData = computed(() => {
  if (!analytics.value) return { labels: [], datasets: [] };
  
  return {
    labels: analytics.value.by_day.map(d => new Date(d.date).toLocaleDateString(undefined, { month: 'short', day: 'numeric' })),
    datasets: [
      {
        label: 'Submissions',
        backgroundColor: 'rgba(128, 0, 0, 0.1)', // UniFAST maroon with opacity
        borderColor: '#800000',
        pointBackgroundColor: '#800000',
        borderWidth: 2,
        fill: true,
        data: analytics.value.by_day.map(d => d.count),
        tension: 0.3 // Smooth curves
      }
    ]
  };
});

const lineChartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false },
    tooltip: { mode: 'index', intersect: false }
  },
  scales: {
    y: { beginAtZero: true, ticks: { precision: 0 } },
    x: { grid: { display: false } }
  }
};

const doughnutChartData = computed(() => {
  if (!analytics.value) return { labels: [], datasets: [] };
  
  return {
    labels: ['Authenticated (Grantees)', 'Anonymous (Public)'],
    datasets: [
      {
        backgroundColor: ['#22c55e', '#94a3b8'], // success green, slate-400
        data: [analytics.value.authenticated, analytics.value.anonymous],
        borderWidth: 0,
        hoverOffset: 4
      }
    ]
  };
});

const doughnutChartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  cutout: '75%',
  plugins: {
    legend: { position: 'bottom' }
  }
};

</script>

<template>
  <div class="h-full overflow-y-auto p-6 bg-surface-muted">
    <div class="max-w-6xl mx-auto space-y-6">
      
      <!-- Header -->
      <div>
        <h2 class="text-xl font-bold">Analytics & Insights</h2>
        <p class="text-text-muted text-sm">Submission statistics for {{ form.title }}</p>
      </div>

      <!-- Loading State -->
      <div v-if="isLoading" class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <Skeleton v-for="i in 3" :key="i" class="h-32 rounded-xl" />
        <Skeleton class="h-80 md:col-span-2 rounded-xl" />
        <Skeleton class="h-80 rounded-xl" />
      </div>

      <!-- Error State -->
      <EmptyState 
        v-else-if="isError || !analytics"
        :icon="IconChartLine"
        title="Failed to load analytics"
        description="There was an error retrieving statistics from the server."
      />

      <!-- Empty Data State -->
      <EmptyState 
        v-else-if="analytics.total === 0"
        :icon="IconChartLine"
        title="No data yet"
        description="There are no submissions to analyze for this form."
      />

      <!-- Dashboard -->
      <template v-else>
        
        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <!-- Total Submissions -->
          <div class="bg-surface p-5 rounded-xl border shadow-sm flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-text-muted">Total Submissions</p>
              <p class="text-3xl font-bold mt-1">{{ analytics.total }}</p>
            </div>
            <div class="h-12 w-12 rounded-full bg-primary/10 flex items-center justify-center text-primary">
              <IconUsers :size="24" />
            </div>
          </div>

          <!-- Authenticated -->
          <div class="bg-surface p-5 rounded-xl border shadow-sm flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-text-muted">Authenticated</p>
              <p class="text-3xl font-bold mt-1">{{ analytics.authenticated }}</p>
              <p class="text-xs text-text-muted mt-1">{{ Math.round((analytics.authenticated / analytics.total) * 100) }}% of total</p>
            </div>
            <div class="h-12 w-12 rounded-full bg-success/10 flex items-center justify-center text-success">
              <IconUserCheck :size="24" />
            </div>
          </div>

          <!-- Anonymous -->
          <div class="bg-surface p-5 rounded-xl border shadow-sm flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-text-muted">Anonymous</p>
              <p class="text-3xl font-bold mt-1">{{ analytics.anonymous }}</p>
              <p class="text-xs text-text-muted mt-1">{{ Math.round((analytics.anonymous / analytics.total) * 100) }}% of total</p>
            </div>
            <div class="h-12 w-12 rounded-full bg-slate-100 flex items-center justify-center text-slate-500">
              <IconUserQuestion :size="24" />
            </div>
          </div>
        </div>

        <!-- Charts Row -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
          
          <!-- Line Chart (Submissions over time) -->
          <div class="bg-surface p-5 rounded-xl border shadow-sm lg:col-span-2 flex flex-col">
            <h3 class="font-semibold mb-4">Submissions (Last 30 Days)</h3>
            <div class="flex-1 min-h-[300px]">
              <Line 
                v-if="lineChartData.datasets.length"
                :data="lineChartData" 
                :options="lineChartOptions as any" 
              />
            </div>
          </div>

          <!-- Doughnut Chart (Auth breakdown) -->
          <div class="bg-surface p-5 rounded-xl border shadow-sm flex flex-col">
            <h3 class="font-semibold mb-4">Submission Type</h3>
            <div class="flex-1 min-h-[250px] flex items-center justify-center relative">
              <Doughnut 
                v-if="doughnutChartData.datasets.length"
                :data="doughnutChartData" 
                :options="doughnutChartOptions as any" 
              />
              <!-- Center Text Overlay -->
              <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none pb-6">
                <span class="text-3xl font-bold">{{ analytics.total }}</span>
                <span class="text-xs text-text-muted">Total</span>
              </div>
            </div>
          </div>

        </div>

        <!-- Form Composition Stats -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
           <div class="bg-surface p-5 rounded-xl border shadow-sm flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-text-muted">Total Fields</p>
              <p class="text-2xl font-bold mt-1">{{ analytics.total_fields }}</p>
            </div>
            <div class="h-10 w-10 rounded-full bg-surface-muted flex items-center justify-center text-text-muted">
              <IconLayoutList :size="20" />
            </div>
          </div>
          <div class="bg-surface p-5 rounded-xl border shadow-sm flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-text-muted">Required Fields</p>
              <p class="text-2xl font-bold mt-1">{{ analytics.required_fields }}</p>
            </div>
            <div class="h-10 w-10 rounded-full bg-surface-muted flex items-center justify-center text-primary">
              <IconAsterisk :size="20" />
            </div>
          </div>
        </div>

      </template>

    </div>
  </div>
</template>
