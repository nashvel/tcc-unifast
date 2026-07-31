<script setup lang="ts">
import { apiUrl } from "@/api/client";
import { onMounted, ref } from "vue";
import { useRoute } from "vue-router";
import { ChevronDown, HelpCircle, ArrowLeft, Mail, Phone, MessageCircle } from "lucide-vue-next";
import logo from "@/assets/system-logo.png";
import LanguageSwitcher from "@/components/LanguageSwitcher.vue";
import { withLang } from "@/i18n/routeLang";

const route = useRoute();

type Faq = { id: number; question: string; answer: string; category: string };
type Term = { id: number; title: string; content: string; version: string };

const faqs = ref<Faq[]>([]);
const terms = ref<Term | null>(null);
const expandedId = ref<number | null>(null);
const loading = ref(true);
const activeCategory = ref("all");

async function loadData() {
  loading.value = true;
  try {
    const [faqsRes, termsRes] = await Promise.all([
      fetch(apiUrl("/api/faqs")).then((r) => r.json()),
      fetch(apiUrl("/api/terms/active")).then((r) => r.json()),
    ]);
    faqs.value = faqsRes.data || [];
    terms.value = termsRes.data;
  } catch {}
  loading.value = false;
}

function toggleExpand(id: number) {
  expandedId.value = expandedId.value === id ? null : id;
}

const categories = computed(() => {
  const cats = [...new Set(faqs.value.map((f) => f.category))];
  return ["all", ...cats];
});

const filteredFaqs = computed(() => {
  if (activeCategory.value === "all") return faqs.value;
  return faqs.value.filter((f) => f.category === activeCategory.value);
});

const categoryLabels: Record<string, string> = {
  all: "All",
  general: "General",
  account: "Account",
  documents: "Documents",
  verification: "Verification",
};

import { computed } from "vue";
</script>

<template>
  <div class="min-h-screen bg-[var(--bg)]">
    <!-- Header -->
    <header class="sticky top-0 z-30 border-b border-[var(--border)] bg-[var(--surface)]">
      <div class="mx-auto flex h-14 max-w-4xl items-center justify-between px-4">
        <div class="flex items-center gap-3">
          <RouterLink :to="withLang('/login', route.query.lang)" class="flex items-center gap-2 text-text-muted hover:text-text">
            <ArrowLeft :size="16" />
            <img :src="logo" class="h-7 w-7 object-contain" alt="TCC" />
            <span class="text-sm font-semibold text-text">UniFAST TES</span>
          </RouterLink>
        </div>
        <LanguageSwitcher />
      </div>
    </header>

    <main class="mx-auto max-w-4xl px-4 py-8">
      <!-- Page Title -->
      <div class="mb-8">
        <h1 class="text-2xl font-bold text-text">Help & Support</h1>
        <p class="mt-1 text-sm text-text-muted">Find answers to frequently asked questions about the UniFAST TES Portal.</p>
      </div>

      <!-- Quick Contact -->
      <section class="mb-8 grid gap-3 sm:grid-cols-3">
        <div class="rounded-lg border bg-surface p-4">
          <Mail :size="18" class="text-primary" />
          <p class="mt-2 text-xs font-medium text-text">Email Support</p>
          <p class="text-2xs text-text-muted">support@tcc.edu.ph</p>
        </div>
        <div class="rounded-lg border bg-surface p-4">
          <Phone :size="18" class="text-primary" />
          <p class="mt-2 text-xs font-medium text-text">Phone</p>
          <p class="text-2xs text-text-muted">(088) 555-1234</p>
        </div>
        <div class="rounded-lg border bg-surface p-4">
          <MessageCircle :size="18" class="text-primary" />
          <p class="mt-2 text-xs font-medium text-text">Office Hours</p>
          <p class="text-2xs text-text-muted">Mon-Fri, 8AM-5PM</p>
        </div>
      </section>

      <!-- Category Filter -->
      <div class="mb-4 flex flex-wrap gap-2">
        <button
          v-for="cat in categories"
          :key="cat"
          :class="[
            'rounded-full px-3 py-1 text-xs font-medium transition-colors',
            activeCategory === cat
              ? 'bg-primary text-white'
              : 'border bg-surface text-text-muted hover:bg-surface-muted',
          ]"
          @click="activeCategory = cat"
        >
          {{ categoryLabels[cat] || cat }}
        </button>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="space-y-3">
        <div v-for="i in 4" :key="i" class="h-16 animate-pulse rounded-lg bg-surface-muted" />
      </div>

      <!-- FAQ List -->
      <div v-else class="space-y-2">
        <div v-for="faq in filteredFaqs" :key="faq.id" class="rounded-lg border bg-surface">
          <button
            class="flex w-full items-center justify-between p-4 text-left"
            @click="toggleExpand(faq.id)"
          >
            <div class="flex items-center gap-3">
              <HelpCircle :size="16" class="shrink-0 text-primary" />
              <span class="text-sm font-medium text-text">{{ faq.question }}</span>
            </div>
            <ChevronDown
              :size="16"
              :class="[
                'shrink-0 text-text-muted transition-transform',
                expandedId === faq.id ? 'rotate-180' : '',
              ]"
            />
          </button>
          <div
            v-if="expandedId === faq.id"
            class="border-t px-4 py-3 text-sm leading-relaxed text-text-muted"
          >
            {{ faq.answer }}
          </div>
        </div>
      </div>

      <!-- Terms Link -->
      <div v-if="terms" class="mt-8 rounded-lg border bg-surface p-4">
        <p class="text-xs text-text-muted">
          By using this portal, you agree to our
          <span class="font-medium text-text">Terms and Conditions (v{{ terms.version }})</span>.
        </p>
      </div>

      <!-- Footer -->
      <footer class="mt-12 border-t pt-6 text-center text-2xs text-text-soft">
        <p>Tagoloan Community College &middot; UniFAST TES Portal</p>
        <p class="mt-1">&copy; {{ new Date().getFullYear() }} All rights reserved.</p>
      </footer>
    </main>
  </div>
</template>
