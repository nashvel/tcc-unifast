<script setup lang="ts">
import {
  ArrowRight,
  Award,
  BookOpen,
  Briefcase,
  Building2,
  CalendarDays,
  ChevronDown,
  Download,
  Factory,
  Facebook,
  FileText,
  Globe2,
  GraduationCap,
  Headphones,
  HeartPulse,
  HeartHandshake,
  Home,
  Landmark,
  Leaf,
  Mail,
  MapPin,
  Menu,
  Newspaper,
  Package,
  Phone,
  Play,
  Recycle,
  Scale,
  ShieldCheck,
  Sprout,
  User,
  Users,
  Waves,
} from "lucide-vue-next";
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import type { Component } from "vue";
import { RouterLink } from "vue-router";
import { apiFetch } from "@/api";
import campusStrip01 from "@/assets/campus-strip-01.png";
import campusStrip05 from "@/assets/campus-strip-05.png";
import campusStrip06 from "@/assets/campus-strip-06.png";
import campusStrip08 from "@/assets/campus-strip-08.png";
import campusStrip09 from "@/assets/campus-strip-09.png";
import campusImage from "@/assets/landing-hero-4k.png";
import diskursoImage from "@/assets/tcc-diskurso.jpg";
import headerLogo from "@/assets/tcc-header-logo.png";
import newsUpdateVideo from "@/assets/tcc-news-update-video.webm";
import studentsImage from "@/assets/auth/TCC_UNIFAST Front Page.webp";
import systemLogo from "@/assets/system-logo.png";
import unifastHeroImage from "@/assets/unifast-hero.png";
import unifastLogo from "@/assets/unifast-logo.png";

type LandingNewsItem = {
  date: string;
  title: string;
  copy: string;
  image: string;
  url: string;
  author: string;
  sdgGoals: number[];
};

type TccHomeArticle = {
  id: number | null;
  slug: string | null;
  title: string | null;
  excerpt: string | null;
  image_url: string | null;
  published_at: string | null;
  author_name: string | null;
  sdg_goals: number[] | null;
};

const sdgMeta: Record<number, { label: string; color: string; icon: Component }> = {
  1: { label: "No Poverty", color: "#e5243b", icon: Home },
  2: { label: "Zero Hunger", color: "#dda63a", icon: Sprout },
  3: { label: "Good Health", color: "#4c9f38", icon: HeartPulse },
  4: { label: "Quality Education", color: "#c5192d", icon: BookOpen },
  5: { label: "Gender Equality", color: "#ff3a21", icon: Users },
  6: { label: "Clean Water", color: "#26bde2", icon: Waves },
  7: { label: "Clean Energy", color: "#fcc30b", icon: Play },
  8: { label: "Decent Work", color: "#a21942", icon: Briefcase },
  9: { label: "Innovation", color: "#fd6925", icon: Package },
  10: { label: "Reduced Inequalities", color: "#dd1367", icon: Scale },
  11: { label: "Sustainable Cities", color: "#fd9d24", icon: Building2 },
  12: { label: "Responsible Consumption", color: "#bf8b2e", icon: Recycle },
  13: { label: "Climate Action", color: "#3f7e44", icon: Leaf },
  14: { label: "Life Below Water", color: "#0a97d9", icon: Waves },
  15: { label: "Life on Land", color: "#56c02b", icon: Sprout },
  16: { label: "Peace and Justice", color: "#00689d", icon: Landmark },
  17: { label: "Partnerships", color: "#19486a", icon: Globe2 },
};

const navItems = [
  { label: "Home", href: "#home" },
  { label: "About", href: "#about-unifast", hasMenu: true },
  { label: "Admissions", href: "#submission-workflow", hasMenu: true },
  { label: "Academics", href: "#news", hasMenu: true },
  { label: "News & Events", href: "#news" },
  { label: "Research", href: "#news" },
  { label: "Downloadables", href: "#submission-workflow" },
];

const fallbackNewsItems: LandingNewsItem[] = [
  {
    date: "Jun 18, 2026",
    title: "Bagong Pilipinas Merit Scholarship Program call for applications",
    copy: "Incoming first-year students can review eligibility requirements and submission reminders.",
    image: studentsImage,
    url: "#news",
    author: "TCC Desk",
    sdgGoals: [4, 10, 17],
  },
  {
    date: "May 20, 2026",
    title: "Enrollment for SY 2025-2026 now open",
    copy: "Start your journey with Tagoloan Community College through the official admission process.",
    image: campusImage,
    url: "#news",
    author: "TCC Desk",
    sdgGoals: [4, 11, 17],
  },
  {
    date: "May 12, 2026",
    title: "College foundation day",
    copy: "Celebrating TCC's commitment to accessible, quality, and community-based education.",
    image: campusImage,
    url: "#news",
    author: "TCC Desk",
    sdgGoals: [4, 17],
  },
];

const programs = [
  { code: "BSA", name: "Accountancy", copy: "Professional accounting foundations for public and private practice." },
  { code: "BSBA", name: "Business Administration", copy: "Management, enterprise, finance, and operations for local industries." },
  { code: "BSED", name: "Secondary Education", copy: "Teacher preparation anchored on values, pedagogy, and community need." },
  { code: "BEED", name: "Elementary Education", copy: "Learner-centered instruction for early and foundational education." },
  { code: "BSIT", name: "Information Technology", copy: "Software, systems, networks, and applied digital problem solving." },
  { code: "BSTM", name: "Tourism Management", copy: "Hospitality, travel operations, and service leadership competencies." },
];

const globalCards = [
  {
    icon: Globe2,
    title: "Partnerships & linkages",
    copy: "Academic cooperation with institutions and partners that open wider pathways for TCC.",
  },
  {
    icon: Users,
    title: "Student & faculty mobility",
    copy: "Exchanges, internships, and visiting lectures that extend learning beyond campus.",
  },
  {
    icon: Building2,
    title: "Internationalization at home",
    copy: "Curriculum and SDG-aligned projects that build globally competent graduates.",
  },
];

const recognition = [
  {
    icon: Award,
    title: "ALCU-COA membership",
    copy: "Part of the local colleges and universities accreditation network.",
  },
  {
    icon: ShieldCheck,
    title: "UniFAST partnership",
    copy: "Financial pathways expanded for qualified students under national support programs.",
  },
  {
    icon: GraduationCap,
    title: "Free higher education",
    copy: "Eligible students benefit when policies and academic progress requirements are met.",
  },
];

const services = [
  {
    icon: User,
    title: "Student portal",
    copy: "Access student information, enrollment tools, and academic records.",
    hint: "SIS login",
    to: "/login",
    accent: true,
  },
  {
    icon: FileText,
    title: "Admissions overview",
    copy: "Review policies, timelines, program choices, and required forms.",
    hint: "Admissions",
    to: "#services",
  },
  {
    icon: BookOpen,
    title: "Online admission portal",
    copy: "Proceed to the official undergraduate application gateway.",
    hint: "Apply online",
    to: "#services",
  },
  {
    icon: Download,
    title: "Downloadable forms",
    copy: "Find handbook excerpts, petitions, clearances, and printable requests.",
    hint: "Forms",
    to: "#services",
  },
];

const workflowSteps = [
  { number: "01", title: "Create account", copy: "Set up your portal account." },
  { number: "02", title: "Submit requirements online", copy: "Upload and submit required documents." },
  { number: "03", title: "OCR & metadata validation", copy: "System validates your submissions." },
  { number: "04", title: "Identity verification", copy: "Verify your identity for assurance." },
  { number: "05", title: "Track status & notifications", copy: "Monitor updates and system messages." },
];

const portalUpdates = [
  {
    date: "Oct 30, 2025",
    title: "Requirements filing for Batch 2025-B closes Oct 30",
    copy: "Submit your complete requirements on or before the deadline.",
  },
  {
    date: "Dec 15, 2025",
    title: "Liveness check available via the Grantee mobile app",
    copy: "Please complete your liveness verification on the app.",
  },
  {
    date: "Dec 15, 2025",
    title: "Liquidation report deadline: December 15",
    copy: "Submit liquidation reports through the portal on or before Dec 15.",
  },
];

const campusStripImages = [
  { src: campusStrip01, alt: "Tagoloan Community College campus building" },
  { src: campusStrip05, alt: "Tagoloan Community College students in campus uniforms" },
  { src: campusStrip06, alt: "Student speaking during a campus program" },
  { src: campusStrip08, alt: "Tagoloan Community College campus ceremony" },
  { src: campusStrip09, alt: "Students and staff at a Tagoloan Community College office" },
];

const liveNewsItems = ref<LandingNewsItem[]>([]);
const isLoadingPublicContent = ref(true);
const publicContentError = ref<string | null>(null);
const heroRevealCard = ref<HTMLElement | null>(null);
const heroRevealColorImage = ref<HTMLElement | null>(null);

let heroRevealFrame = 0;
let removeHeroRevealPointerMove: (() => void) | null = null;

const newsItems = computed(() => liveNewsItems.value.length > 0 ? liveNewsItems.value : fallbackNewsItems);

function formatPublishedDate(value: string | null): string {
  if (!value) return "Latest update";

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "Latest update";

  return new Intl.DateTimeFormat("en-PH", {
    year: "numeric",
    month: "short",
    day: "numeric",
  }).format(date);
}

async function loadTccPublicContent(): Promise<void> {
  try {
    const payload = await apiFetch<{ news?: TccHomeArticle[] }>("/api/public/tcc-home");

    liveNewsItems.value = (payload.news ?? [])
      .filter((article) => article.title)
      .slice(0, 8)
      .map((article) => ({
        date: formatPublishedDate(article.published_at),
        title: article.title ?? "Latest TCC update",
        copy: article.excerpt ?? "Read the latest public update from Tagoloan Community College.",
        image: article.image_url ?? campusImage,
        url: article.slug ? `https://tcc.edu.ph/news/${article.slug}` : "#news",
        author: article.author_name ?? "Tagoloan Community College",
        sdgGoals: Array.isArray(article.sdg_goals) ? article.sdg_goals.slice(0, 5) : [],
      }));
  } catch (error) {
    publicContentError.value = error instanceof Error ? error.message : "Unable to load live TCC content.";
  } finally {
    isLoadingPublicContent.value = false;
  }
}

function setupHeroLiquidReveal(): void {
  const container = heroRevealCard.value;
  const colorImage = heroRevealColorImage.value;
  const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  if (!container || !colorImage || reducedMotion) return;

  type TrailPoint = { x: number; y: number; opacity: number };
  let lastPoint: { x: number; y: number } | null = null;
  let points: TrailPoint[] = [];

  function onPointerMove(event: PointerEvent): void {
    const rect = container.getBoundingClientRect();
    const x = event.clientX - rect.left;
    const y = event.clientY - rect.top;
    const radius = 143;

    if (x < -radius || y < -radius || x > rect.width + radius || y > rect.height + radius) {
      lastPoint = null;
      return;
    }

    if (!lastPoint) {
      points.push({ x, y, opacity: 1 });
      lastPoint = { x, y };
      ensureTicking();
      return;
    }

    const dx = x - lastPoint.x;
    const dy = y - lastPoint.y;
    const distance = Math.hypot(dx, dy);
    const step = Math.max(radius * 0.3, 1);
    const count = Math.min(Math.ceil(distance / step), 60);

    for (let index = 1; index <= count; index += 1) {
      const progress = index / count;
      points.push({
        x: lastPoint.x + dx * progress,
        y: lastPoint.y + dy * progress,
        opacity: 1,
      });
    }

    lastPoint = { x, y };
    ensureTicking();
  }

  function applyMask(): void {
    if (points.length === 0) {
      colorImage.style.opacity = "0";
      colorImage.style.maskImage = "none";
      colorImage.style.webkitMaskImage = "none";
      heroRevealFrame = 0;
      return;
    }

    colorImage.style.opacity = "1";
    const gradients = points
      .map((point) => {
        const alpha = Math.max(0, Math.min(point.opacity, 1));
        return `radial-gradient(circle 143px at ${point.x}px ${point.y}px, rgba(0,0,0,${alpha}) 0%, rgba(0,0,0,${alpha * 0.82}) 55%, rgba(0,0,0,0) 100%)`;
      })
      .join(", ");

    colorImage.style.maskImage = gradients;
    colorImage.style.webkitMaskImage = gradients;
  }

  function tick(): void {
    points = points
      .map((point) => ({ ...point, opacity: point.opacity - 0.032 }))
      .filter((point) => point.opacity > 0);
    applyMask();
    if (points.length > 0) heroRevealFrame = requestAnimationFrame(tick);
  }

  function ensureTicking(): void {
    applyMask();
    if (!heroRevealFrame) heroRevealFrame = requestAnimationFrame(tick);
  }

  window.addEventListener("pointermove", onPointerMove);
  removeHeroRevealPointerMove = () => window.removeEventListener("pointermove", onPointerMove);
}

onMounted(() => {
  void loadTccPublicContent();
  setupHeroLiquidReveal();
});

onBeforeUnmount(() => {
  if (heroRevealFrame) cancelAnimationFrame(heroRevealFrame);
  heroRevealFrame = 0;
  removeHeroRevealPointerMove?.();
  removeHeroRevealPointerMove = null;
});
</script>

<template>
  <main id="home" class="landing-page">
    <header class="site-header" aria-label="Site header">
      <div class="header-main">
        <RouterLink class="brand" to="/" aria-label="Tagoloan Community College home">
          <img :src="headerLogo" alt="Tagoloan Community College" />
        </RouterLink>

        <nav class="primary-nav" aria-label="Primary navigation">
          <a v-for="item in navItems" :key="item.label" :href="item.href" :class="{ active: item.label === 'Home' }">
            <span>{{ item.label }}</span>
            <ChevronDown v-if="item.hasMenu" :size="13" aria-hidden="true" />
          </a>
        </nav>

        <div class="header-actions">
          <a class="assistant-button" href="#services" aria-label="Open TCC assistant">
            <Headphones :size="18" aria-hidden="true" />
          </a>
          <button class="menu-button" type="button" aria-label="Menu">
            <Menu :size="22" aria-hidden="true" />
          </button>
          <RouterLink class="portal-link" to="/login">
            <User :size="16" aria-hidden="true" />
            Sign In
          </RouterLink>
        </div>
      </div>
    </header>

    <section class="hero-section" aria-label="Welcome">
      <div class="landing-container hero-grid">
        <div class="hero-copy">
          <p class="hero-kicker">
            <img :src="unifastLogo" alt="" aria-hidden="true" />
            <span></span>
            UniFAST TES Scholarship Portal
          </p>
          <h1>
            <span>Student financial assistance</span>
            <strong>for tertiary education</strong>
          </h1>
          <p class="hero-lead">
            A Tagoloan Community College portal for TES scholarship requirements, submissions, and updates
            under CHED-UniFAST, the unified system for government-funded tertiary education assistance.
          </p>
          <div class="hero-actions">
            <button class="hymn-button" type="button">
              <span><Play :size="17" fill="currentColor" aria-hidden="true" /></span>
              View UniFAST guide
            </button>
            <RouterLink class="hero-portal-button" to="/login">
              Sign In to Portal
              <ArrowRight :size="18" aria-hidden="true" />
            </RouterLink>
          </div>
          <p class="hero-location"><MapPin :size="17" aria-hidden="true" /> Tagoloan, Misamis Oriental, Philippines</p>
        </div>

        <aside class="hero-video-wall" aria-label="Campus video previews">
          <figure ref="heroRevealCard" class="hero-video-card">
            <img class="hero-video-card__image hero-video-card__image--base" :src="unifastHeroImage" alt="UniFAST TES scholarship illustration" />
            <img ref="heroRevealColorImage" class="hero-video-card__image hero-video-card__reveal" :src="unifastHeroImage" alt="" aria-hidden="true" />
          </figure>
        </aside>
      </div>
    </section>

    <section id="submission-workflow" class="workflow-section">
      <div class="workflow-cta">
        <div class="workflow-cta__photos" aria-hidden="true">
          <figure v-for="(photo, index) in campusStripImages" :key="`cta-${index}`" class="workflow-cta__photo">
            <img :src="photo.src" alt="" />
          </figure>
        </div>
        <div class="landing-container workflow-cta__inner">
          <p>Registered grantees: log in to submit and track your requirements.</p>
        </div>
      </div>

      <div class="landing-container workflow-layout">
        <div class="workflow-panel">
          <p class="workflow-kicker">How it works</p>
          <h2>Grantee submission workflow</h2>
          <ol class="workflow-list">
            <li v-for="step in workflowSteps" :key="step.number">
              <span>{{ step.number }}</span>
              <div>
                <strong>{{ step.title }}</strong>
                <p>{{ step.copy }}</p>
              </div>
            </li>
          </ol>
        </div>

        <aside class="updates-panel" aria-label="Latest portal updates">
          <p class="workflow-kicker">Announcements</p>
          <h2>Latest updates</h2>
          <div class="updates-list">
            <article v-for="update in portalUpdates" :key="`${update.date}-${update.title}`">
              <time>{{ update.date }}</time>
              <div>
                <h3>{{ update.title }}</h3>
                <p>{{ update.copy }}</p>
              </div>
            </article>
          </div>
        </aside>
      </div>
    </section>

    <section id="about-unifast" class="section unifast-section">
      <div class="landing-container unifast-editorial">
        <div class="unifast-copy">
          <div class="unifast-heading-row">
            <img :src="unifastLogo" alt="UniFAST logo" />
            <div>
              <p class="unifast-kicker">What is UniFAST?</p>
              <h2>A unified financial assistance system for tertiary education</h2>
            </div>
          </div>
          <p>
            The Unified Financial Assistance System for Tertiary Education Act, or UniFAST, is Republic Act
            No. 10687. It brings government-funded student financial assistance programs under one coordinated
            system for students in public and private institutions.
          </p>
          <p>
            UniFAST was established to make tertiary-level assistance more effective, efficient, and politically
            neutral, with support delivered through scholarships, grants-in-aid, student loans, and other
            specialized forms approved by the UniFAST Board.
          </p>
        </div>

        <div class="unifast-feature-grid">
          <article class="unifast-feature">
            <h3>RA 10687</h3>
            <strong>One coordinated system</strong>
            <p>All government-funded financial assistance programs in one unified system.</p>
          </article>
          <article class="unifast-feature">
            <h3>Scholarships · Grants · Loans</h3>
            <strong>Multiple forms of support</strong>
            <p>Scholarships, grants-in-aid, student loans, and other specialized assistance.</p>
          </article>
          <article class="unifast-feature">
            <h3>CHED-led Board</h3>
            <strong>Governed with oversight</strong>
            <p>The UniFAST Board ensures transparent and accountable implementation.</p>
          </article>
          <article class="unifast-feature">
            <h3>One place for TCC students</h3>
            <strong>Requirements, submissions, and updates in one portal</strong>
            <p>Streamlined for a better student experience.</p>
          </article>
        </div>

        <RouterLink class="unifast-link" to="/unifast">
          Know more about UniFAST <ArrowRight :size="17" aria-hidden="true" />
        </RouterLink>
      </div>
    </section>

    <section id="news" class="section news-section">
      <div class="landing-container">
        <header class="section-head section-head--split news-head">
          <figure class="news-video-feature" aria-label="News and updates highlight">
            <video :src="newsUpdateVideo" autoplay muted loop playsinline preload="metadata"></video>
          </figure>

          <div>
            <p class="section-kicker">Campus updates</p>
            <h2>Latest news & updates</h2>
            <p>
              Stories from instruction, research, extension, and student life.
              <span v-if="isLoadingPublicContent" class="news-status">Loading live TCC articles...</span>
              <span v-else-if="publicContentError" class="news-status">Showing fallback articles.</span>
            </p>
          </div>
        </header>

        <div class="news-grid">
          <article v-for="item in newsItems" :key="item.title" class="news-card">
            <a class="news-card-media" :href="item.url" :aria-label="`Read article: ${item.title}`">
              <img :src="item.image" alt="" />
              <span><Newspaper :size="17" aria-hidden="true" /> Full publication</span>
            </a>
            <div class="news-card-body">
              <p class="news-byline">By <strong>{{ item.author }}</strong> · <time>{{ item.date }}</time></p>
              <h3>{{ item.title }}</h3>
              <p>{{ item.copy }}</p>
              <ul v-if="item.sdgGoals.length" class="sdg-list" aria-label="Sustainable Development Goal tags">
                <li
                  v-for="goal in item.sdgGoals"
                  :key="goal"
                  :style="{ backgroundColor: sdgMeta[goal]?.color ?? '#6b1020' }"
                  :title="`SDG ${goal}: ${sdgMeta[goal]?.label ?? 'Goal'}`"
                >
                  <strong>{{ goal }}</strong>
                  <component :is="sdgMeta[goal]?.icon ?? ShieldCheck" :size="20" aria-hidden="true" />
                  <span>{{ sdgMeta[goal]?.label ?? "Goal" }}</span>
                </li>
              </ul>
              <a class="read-article-link" :href="item.url">Read article</a>
            </div>
          </article>
        </div>
      </div>
    </section>

    <footer id="contact" class="site-footer">
      <div class="landing-container footer-grid">
        <div>
          <div class="footer-brand" aria-label="Tagoloan Community College">
            <img :src="systemLogo" alt="" aria-hidden="true" />
            <span>
              <strong>Tagoloan</strong>
              <em>Community College</em>
            </span>
          </div>
          <p>Public higher education in Misamis Oriental, founded in 2003 to expand access for Tagoloan residents.</p>
        </div>
        <address>
          <span><MapPin :size="16" aria-hidden="true" /> Tagoloan, Misamis Oriental, Philippines</span>
          <span><Phone :size="16" aria-hidden="true" /> +63 (000) 000-0000</span>
          <span><Mail :size="16" aria-hidden="true" /> info@tcc.edu.ph</span>
        </address>
        <div class="footer-social">
          <a href="#contact"><Facebook :size="18" aria-hidden="true" /> Facebook</a>
          <RouterLink to="/login"><User :size="18" aria-hidden="true" /> Sign In</RouterLink>
          <RouterLink to="/login"><Briefcase :size="18" aria-hidden="true" /> About developers</RouterLink>
        </div>
      </div>
    </footer>
  </main>
</template>

<style scoped>
@reference "../assets/app.css";

.landing-page {
  --brand: #6b1020;
  --brand-dark: #30070e;
  --brand-mid: #8f2d3d;
  --accent: #c6a14a;
  --paper: #fff9f6;
  --ink: #1a1214;
  --muted: #6b5e61;
  --border: #e7dde0;
  @apply min-h-screen bg-white;
  color: var(--ink);
  font-family: "Figtree", "Poppins", var(--font-sans);
}

.landing-container {
  @apply mx-auto w-[min(80rem,calc(100%_-_2rem))];
}

a {
  @apply text-inherit no-underline;
}

.site-header {
  @apply sticky top-0 z-30 border-b backdrop-blur-[18px];
  background: rgba(255, 255, 255, 0.94);
  border-bottom: 1px solid rgba(107, 16, 32, 0.12);
}

.hero-location,
.footer-grid address span,
.footer-social a {
  @apply inline-flex items-center gap-[0.45rem];
}

.header-main {
  @apply grid min-h-[4.4rem] w-full items-center gap-6 px-6;
  grid-template-columns: auto 1fr auto;
}

.brand img {
  @apply block h-auto w-[190px];
}

.primary-nav {
  @apply flex justify-center;
  gap: clamp(0.35rem, 1.2vw, 1rem);
}

.primary-nav a {
  @apply inline-flex min-h-[2.55rem] items-center gap-[0.2rem] rounded-[0.8rem] px-[0.65rem] text-[0.78rem] font-extrabold uppercase tracking-[0.02em];
  color: #3f3034;
}

.primary-nav a.active,
.primary-nav a:hover {
  color: var(--brand);
  background: rgba(107, 16, 32, 0.07);
}

.header-actions {
  @apply flex items-center justify-end gap-[0.55rem];
}

.assistant-button,
.menu-button {
  @apply inline-grid size-[2.55rem] place-items-center rounded-full border bg-white;
  border-color: var(--border);
  color: var(--brand);
}

.menu-button {
  @apply hidden;
}

.portal-link {
  @apply inline-flex min-h-[2.55rem] items-center gap-[0.45rem] rounded-full px-4 text-[0.82rem] font-extrabold text-white;
  color: #fff;
  background: var(--brand);
}

.hero-section {
  @apply relative isolate overflow-hidden bg-white;
  min-height: clamp(34rem, 68svh, 45rem);
  color: var(--text);
}

.hero-grid {
  @apply grid min-h-[inherit] items-start;
  grid-template-columns: minmax(23rem, 0.76fr) minmax(42rem, 1.24fr);
  gap: clamp(2rem, 4vw, 3.25rem);
  padding: clamp(2rem, 3.2vw, 3rem) 0 clamp(1.5rem, 3vw, 2.75rem);
}

.hero-copy {
  @apply max-w-[39rem];
}

.hero-kicker,
.section-kicker {
  @apply mb-[0.8rem] mt-0 text-[0.76rem] font-black uppercase tracking-[0.14em];
  color: var(--brand);
}

.hero-kicker {
  @apply inline-flex items-center gap-3;
  color: var(--brand);
}

.hero-kicker img {
  @apply size-9 shrink-0 object-contain;
}

.hero-kicker span {
  @apply h-0.5 w-[1.9rem];
  background: var(--brand);
}

.hero-copy h1 {
  @apply m-0 max-w-[11ch] uppercase tracking-[0.01em];
  font-family: "Young Serif", Georgia, serif;
  font-size: clamp(1.9rem, 3.65vw, 3.25rem);
  line-height: 1.02;
  font-weight: 400;
}

.hero-copy h1 span,
.hero-copy h1 strong {
  @apply block;
}

.hero-copy h1 strong {
  color: var(--brand);
  font-weight: 400;
}

.hero-lead {
  @apply mb-0 mt-4 max-w-[32rem] text-[1rem] leading-[1.65];
  color: var(--muted);
}

.hero-actions {
  @apply mt-5 flex flex-wrap gap-[0.8rem];
}

.gold-button,
.dark-button,
.hymn-button,
.hero-portal-button,
.outline-pill,
.light-button {
  @apply inline-flex min-h-12 items-center justify-center gap-[0.55rem] rounded-[0.95rem] border border-transparent px-[1.2rem] py-3 font-extrabold;
}

.gold-button,
.hymn-button {
  color: #2a1008;
  background: linear-gradient(135deg, #f4d9a0, #d4a04a);
}

.gold-button--inline {
  @apply min-h-11;
}

.dark-button {
  color: var(--brand);
  border-color: rgba(107, 16, 32, 0.2);
  background: #fff;
}

.hymn-button {
  @apply cursor-pointer;
}

.hymn-button span {
  @apply inline-grid size-[1.9rem] place-items-center rounded-full;
  background: rgba(42, 16, 8, 0.14);
}

.hero-portal-button {
  color: #fff;
  background: var(--brand);
  box-shadow: 0 12px 30px rgba(107, 16, 32, 0.16);
}

.hero-location {
  @apply mb-0 mt-[1.45rem] font-bold;
  color: var(--muted);
}

.hero-video-wall {
  @apply grid w-full max-w-[50rem] place-items-center justify-self-center;
  gap: clamp(1rem, 2.2vw, 1.5rem);
  transform: translateY(clamp(-1.75rem, -2vw, -0.75rem));
}

.hero-video-card {
  @apply relative m-0 min-w-0 overflow-visible rounded-[1.15rem] bg-white shadow-none;
}

.hero-video-card {
  aspect-ratio: 4 / 3;
}

.hero-video-card__image {
  @apply block h-full w-full object-contain;
  border-radius: inherit;
}

.hero-video-card__image--base {
  filter: grayscale(1) saturate(0) contrast(1.03);
}

.hero-video-card__reveal {
  @apply pointer-events-none absolute inset-0 block h-full w-full opacity-0;
  border-radius: inherit;
  mask-repeat: no-repeat;
  -webkit-mask-repeat: no-repeat;
}

.section {
  @apply relative;
  padding-block: clamp(3rem, 7vw, 5.5rem);
}

.section-head,
.section-head--split {
  @apply mb-[1.7rem] grid gap-3;
}

.section-head--split {
  @apply items-end;
  grid-template-columns: 1fr auto;
}

.section h2,
.section-head h2 {
  @apply m-0 tracking-[-0.02em];
  color: var(--ink);
  font-size: clamp(2rem, 4vw, 3rem);
  line-height: 1.05;
}

.section-head p,
.section-lead {
  @apply m-0 max-w-[42rem] leading-[1.65];
  color: var(--muted);
}

.news-status {
  @apply mt-[0.3rem] block text-[0.82rem] font-extrabold;
  color: var(--brand);
}

.unifast-section {
  @apply bg-white;
}

.unifast-editorial {
  @apply grid gap-7;
}

.unifast-copy {
  @apply max-w-[52rem];
}

.unifast-heading-row {
  @apply mb-5 flex items-start gap-4;
}

.unifast-heading-row img {
  @apply mt-1 size-16 shrink-0 object-contain;
}

.unifast-kicker {
  @apply mb-3 mt-0 text-[0.78rem] font-black uppercase tracking-[0.15em];
  color: #c6963e;
}

.unifast-copy h2 {
  @apply m-0;
  font-family: "Young Serif", Georgia, serif;
  color: var(--text);
  font-size: clamp(1.8rem, 3.2vw, 2.8rem);
  line-height: 1.12;
  font-weight: 400;
}

.unifast-copy p {
  @apply mb-0 mt-4 max-w-[44rem] leading-[1.7];
  color: var(--muted);
}

.unifast-feature-grid {
  @apply grid border-y py-6;
  border-color: var(--border);
  grid-template-columns: repeat(4, minmax(0, 1fr));
}

.unifast-feature {
  @apply min-w-0 px-6;
  border-left: 1px solid rgba(107, 16, 32, 0.16);
}

.unifast-feature:first-child {
  @apply pl-0;
  border-left: 0;
}

.unifast-feature h3 {
  @apply mb-3 mt-0 text-[0.92rem] font-black;
  color: var(--brand);
}

.unifast-feature strong {
  @apply block text-[0.95rem] leading-[1.45];
  color: var(--text);
}

.unifast-feature p {
  @apply mb-0 mt-3 text-[0.92rem] leading-[1.65];
  color: var(--muted);
}

.unifast-link {
  @apply inline-flex w-fit items-center gap-2 text-[0.95rem] font-black;
  color: var(--brand);
}

.vmg-section,
.programs-section,
.recognition-section {
  @apply bg-white;
}

.vmg-layout,
.research-layout,
.community-layout {
  @apply grid items-stretch;
  grid-template-columns: minmax(0, 0.9fr) minmax(0, 1.1fr);
  gap: clamp(1.5rem, 4vw, 3rem);
}

.vmg-photo {
  @apply relative m-0 overflow-hidden rounded-[1.25rem];
  background: var(--brand-dark);
}

.vmg-photo img {
  @apply block h-auto w-full object-contain;
}

.vmg-photo figcaption {
  @apply absolute bottom-4 left-4 rounded-full px-3 py-[0.55rem] text-[0.78rem] font-black;
  color: #2a1008;
  background: #f4d9a0;
}

.news-card,
.program-card,
.feature-card,
.service-tile,
.diskurso-card {
  @apply relative overflow-hidden rounded-[1.05rem] border bg-white;
  border-color: var(--border);
  box-shadow: 0 12px 34px rgba(36, 2, 8, 0.08);
}

.vmg-content {
  @apply self-center;
}

.vmg-kicker {
  @apply mb-4 mt-0 inline-flex items-center gap-4 text-[0.78rem] font-black uppercase tracking-[0.15em];
  color: #c6963e;
}

.vmg-kicker::after {
  content: "";
  @apply h-0.5 w-8;
  background: #d8ab58;
}

.vmg-content h2 {
  @apply m-0;
  font-family: "Young Serif", Georgia, serif;
  font-size: clamp(2rem, 4vw, 3.35rem);
  line-height: 1.08;
  font-weight: 400;
  color: var(--text);
}

.vmg-statement {
  @apply mt-8 max-w-[36rem];
}

.vmg-statement span {
  @apply mb-3 block text-[0.86rem] font-black uppercase tracking-[0.1em];
  color: var(--brand);
}

.vmg-statement p {
  @apply m-0 leading-[1.75];
  color: var(--muted);
}

.vmg-vision {
  font-family: "Young Serif", Georgia, serif;
  font-size: clamp(1.45rem, 2.6vw, 2.15rem);
  font-style: italic;
  line-height: 1.42;
  color: var(--brand) !important;
}

.vmg-divider {
  @apply mt-7 h-0.5 w-[13rem];
  background: #d8ab58;
}

.vmg-link {
  @apply mt-8 inline-flex items-center gap-2 text-[0.95rem] font-black;
  color: var(--brand);
}

.intl-section,
.services-section {
  background: #faf7f8;
}

.news-section {
  @apply bg-white;
}

.program-grid,
.feature-grid,
.services-grid {
  @apply grid grid-cols-3 gap-4;
}

.news-grid {
  @apply grid grid-cols-4 gap-5;
}

.news-head {
  @apply items-center;
  grid-template-columns: auto minmax(0, 1fr);
  column-gap: clamp(1rem, 2vw, 1.5rem);
}

.news-video-feature {
  @apply m-0 justify-self-start overflow-hidden rounded-none bg-transparent;
  width: clamp(5rem, 8vw, 7rem);
}

.news-video-feature video {
  @apply block w-full object-contain;
  aspect-ratio: 4 / 3;
}

.news-card {
  @apply flex min-h-full flex-col rounded-xl shadow-none;
}

.news-card-media {
  @apply relative m-0 block overflow-hidden;
  color: #fff;
  background: var(--brand-dark);
}

.news-card-media img {
  @apply block w-full object-cover;
  aspect-ratio: 1.62 / 1;
}

.news-card-media span {
  @apply absolute bottom-3 left-[0.9rem] inline-flex items-center gap-[0.28rem] text-[0.74rem] font-black uppercase tracking-[0.02em] text-white;
  text-shadow: 0 2px 12px rgba(0, 0, 0, 0.5);
}

.news-card-body,
.program-card,
.feature-card,
.service-tile {
  @apply p-5;
}

.program-card span,
.service-tile em {
  @apply text-[0.76rem] font-black uppercase tracking-[0.08em] not-italic;
  color: var(--brand);
}

.news-byline {
  @apply text-[0.88rem];
}

.news-byline strong {
  color: var(--brand);
}

.news-byline time {
  color: var(--muted);
}

.news-card h3,
.program-card h3,
.feature-card h3 {
  @apply my-[0.45rem] text-[1.08rem] leading-[1.3];
  color: var(--brand-dark);
}

.news-card h3 {
  @apply mb-[0.9rem] mt-2 min-h-[3.35rem] overflow-hidden text-[1.13rem] leading-[1.28];
  display: -webkit-box;
  color: #201719;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
}

.news-card p,
.program-card p,
.feature-card p,
.diskurso-card p {
  @apply m-0 leading-[1.55];
  color: var(--muted);
}

.news-card-body > p:not(.news-byline) {
  display: none;
}

.sdg-list {
  @apply mb-[0.8rem] mt-0 flex min-h-[2.8rem] list-none flex-wrap gap-[0.35rem] p-0;
}

.sdg-list li {
  @apply relative grid size-[2.95rem] overflow-hidden rounded-[0.35rem] p-[0.22rem] text-white;
  grid-template-columns: 0.72rem 1fr;
  grid-template-rows: auto 1fr;
  gap: 0.1rem 0.15rem;
  align-items: center;
  box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.22);
}

.sdg-list strong {
  @apply self-start text-[0.72rem] leading-none;
}

.sdg-list svg {
  @apply size-5 justify-self-end;
  stroke-width: 2.35;
}

.sdg-list span {
  @apply col-span-full self-end text-[0.34rem] font-black uppercase leading-[1.02];
}

.read-article-link,
.program-card a {
  @apply inline-flex items-center gap-[0.35rem] font-black;
  color: var(--brand);
}

.read-article-link {
  @apply mt-auto text-[1.02rem] italic;
}

.outline-pill {
  @apply bg-white;
  color: var(--brand);
  border-color: rgba(107, 16, 32, 0.24);
}

.program-stats {
  @apply mb-4 flex flex-wrap gap-3;
}

.program-stats span {
  @apply inline-flex min-h-10 items-center gap-[0.45rem] rounded-full border px-[0.85rem] font-extrabold;
  color: var(--muted);
  background: #f7f1f3;
  border-color: var(--border);
}

.program-stats strong {
  @apply text-[1.3rem];
  color: var(--brand);
}

.feature-card span,
.service-icon,
.icon-badge {
  @apply inline-grid size-[2.6rem] place-items-center rounded-[0.8rem] border;
  color: var(--brand);
  background: rgba(107, 16, 32, 0.08);
  border: 1px solid rgba(107, 16, 32, 0.12);
}

.community-section {
  background:
    linear-gradient(108deg, rgba(255, 255, 255, 0.95), rgba(255, 248, 245, 0.84)),
    url("@/assets/auth/TCC_UNIFAST Front Page.webp") center / cover;
}

.chip-list {
  @apply mb-[1.6rem] mt-[1.4rem] grid list-none gap-3 p-0;
}

.chip-list li {
  @apply flex items-start gap-[0.8rem] rounded-2xl border bg-white p-4;
  border-color: var(--border);
}

.chip-list svg {
  @apply shrink-0 grow-0;
  color: var(--brand);
}

.chip-list strong,
.chip-list span span {
  @apply block;
}

.chip-list strong {
  color: var(--brand-dark);
}

.quote-card {
  @apply flex min-h-72 flex-col justify-center rounded-[1.25rem];
  padding: clamp(1.5rem, 3vw, 2.2rem);
  color: #fff;
  background:
    linear-gradient(152deg, rgba(26, 6, 12, 0.92), rgba(52, 11, 18, 0.86)),
    url("@/assets/auth/TCC_UNIFAST Front Page.webp") center / cover;
  box-shadow: 0 24px 56px rgba(36, 2, 8, 0.22);
}

.quote-card blockquote {
  @apply m-0 italic;
  font-size: clamp(1.1rem, 2vw, 1.35rem);
  line-height: 1.55;
}

.quote-card p {
  @apply mb-0 mt-4 text-[0.78rem] font-black uppercase tracking-[0.08em];
  color: #ffe0b6;
}

.research-section {
  background: #fff;
}

.research-intro {
  min-height: 24rem;
  padding: clamp(1.6rem, 4vw, 2.6rem);
  color: #fff;
  background:
    linear-gradient(115deg, rgba(14, 4, 8, 0.9), rgba(62, 12, 20, 0.78)),
    url("@/assets/auth/TCC_UNIFAST Front Page.webp") center / cover;
  border-radius: 1.25rem;
}

.research-intro h2 {
  color: #fff;
}

.research-intro p {
  color: rgba(255, 245, 242, 0.82);
  line-height: 1.65;
}

.research-intro ul {
  display: flex;
  flex-wrap: wrap;
  gap: 0.45rem;
  margin: 1.2rem 0;
  padding: 0;
  list-style: none;
}

.research-intro li {
  padding: 0.4rem 0.7rem;
  color: #ffebd6;
  border: 1px solid rgba(255, 255, 255, 0.28);
  border-radius: 999px;
  font-size: 0.72rem;
  font-weight: 900;
  text-transform: uppercase;
  letter-spacing: 0.08em;
}

.section-kicker--light {
  color: #f0c27a;
}

.light-button {
  color: var(--brand-dark);
  background: #fff;
}

.diskurso-card {
  display: grid;
  gap: 1rem;
  align-content: start;
  padding: 1.35rem;
}

.diskurso-card img {
  width: 100%;
  display: block;
  border: 1px solid var(--border);
  border-radius: 0.9rem;
}

.services-grid {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.service-tile {
  display: grid;
  grid-template-columns: auto 1fr auto;
  align-items: start;
  gap: 0.3rem 0.85rem;
  min-height: 9.5rem;
  color: inherit;
}

.service-tile--accent {
  background: linear-gradient(135deg, rgba(107, 16, 32, 0.08), rgba(198, 161, 74, 0.16)), #fff;
}

.service-tile strong,
.service-tile small,
.service-tile em {
  grid-column: 2;
}

.service-tile strong {
  color: var(--brand-dark);
  font-size: 1.1rem;
}

.service-tile small {
  color: var(--muted);
  line-height: 1.5;
}

.service-tile > svg {
  grid-column: 3;
  grid-row: 1 / 4;
  color: rgba(107, 16, 32, 0.45);
}

.workflow-section {
  @apply bg-white;
}

.workflow-layout {
  @apply grid py-12;
  grid-template-columns: minmax(0, 1fr) minmax(0, 0.98fr);
}

.workflow-panel {
  @apply pr-12;
}

.updates-panel {
  @apply border-l pl-12;
  border-color: rgba(107, 16, 32, 0.18);
}

.workflow-kicker {
  @apply mb-2 mt-0 inline-flex items-center gap-4 text-[0.78rem] font-black uppercase tracking-[0.15em];
  color: #c6963e;
}

.workflow-kicker::after {
  content: "";
  @apply h-0.5 w-8;
  background: #d8ab58;
}

.workflow-panel h2,
.updates-panel h2 {
  @apply m-0;
  font-family: "Young Serif", Georgia, serif;
  font-size: clamp(1.75rem, 3vw, 2.55rem);
  line-height: 1.12;
  font-weight: 400;
  color: var(--text);
}

.workflow-list {
  @apply m-0 mt-7 grid list-none p-0;
}

.workflow-list li {
  @apply grid items-start gap-6 border-t py-3;
  grid-template-columns: 3.25rem minmax(0, 1fr);
  border-color: rgba(107, 16, 32, 0.16);
}

.workflow-list li:first-child {
  @apply border-t-0 pt-0;
}

.workflow-list span {
  font-family: "Young Serif", Georgia, serif;
  font-size: clamp(1.9rem, 3.6vw, 2.7rem);
  line-height: 1;
  color: var(--brand);
}

.workflow-list strong,
.updates-list h3 {
  @apply block text-[0.98rem] font-black leading-[1.35];
  color: var(--text);
}

.workflow-list p,
.updates-list p {
  @apply mb-0 mt-1 leading-[1.5];
  color: var(--muted);
}

.updates-list {
  @apply mt-7 grid;
}

.updates-list article {
  @apply grid gap-6 border-t py-6;
  grid-template-columns: 7.5rem minmax(0, 1fr);
  border-color: rgba(107, 16, 32, 0.16);
}

.updates-list article:first-child {
  @apply border-t-0 pt-0;
}

.updates-list time {
  @apply text-[0.86rem] font-black uppercase tracking-[0.03em];
  color: var(--brand);
}

.updates-list h3 {
  @apply m-0;
}

.workflow-cta {
  @apply relative isolate overflow-hidden py-12;
  min-height: clamp(10rem, 18vw, 15rem);
  color: #fff8ee;
  background: linear-gradient(90deg, var(--brand), var(--brand-dark));
}

.workflow-cta__inner {
  @apply relative z-10 flex items-center justify-between gap-8;
}

.workflow-cta__photos {
  @apply absolute inset-0 z-0 flex;
}

.workflow-cta__photo {
  @apply relative m-0 min-w-0 flex-1 overflow-hidden;
  clip-path: polygon(8% 0, 100% 0, 92% 100%, 0 100%);
  margin-left: -1rem;
}

.workflow-cta__photo:first-child {
  margin-left: 0;
  clip-path: polygon(0 0, 100% 0, 92% 100%, 0 100%);
}

.workflow-cta__photo:last-child {
  clip-path: polygon(8% 0, 100% 0, 100% 100%, 0 100%);
}

.workflow-cta__photo::after {
  content: "";
  @apply pointer-events-none absolute inset-0 transition-opacity duration-300;
  background:
    linear-gradient(90deg, rgba(107, 16, 32, 0.42), rgba(48, 7, 14, 0.58)),
    linear-gradient(180deg, rgba(48, 7, 14, 0.12), rgba(48, 7, 14, 0.3));
}

.workflow-cta__photo img {
  @apply block h-full w-full object-cover transition duration-500 ease-out;
  filter: saturate(0.85) contrast(1.06);
}

.workflow-cta__photo:hover::after {
  opacity: 0.14;
}

.workflow-cta__photo:hover img {
  filter: saturate(1.08) contrast(1.04);
  transform: scale(1.08);
}

.workflow-cta p {
  @apply m-0 max-w-[38rem];
  font-family: "Young Serif", Georgia, serif;
  font-size: clamp(1.45rem, 2.8vw, 2.4rem);
  line-height: 1.2;
}

.site-footer {
  padding: 2.25rem 0;
  color: #fff8ee;
  background: linear-gradient(90deg, var(--brand-dark), #180408);
}

.footer-grid {
  display: grid;
  grid-template-columns: 1.2fr 1fr auto;
  gap: 2rem;
  align-items: center;
}

.footer-brand {
  @apply inline-flex items-center gap-3;
  color: #fff8ee;
}

.footer-brand img {
  @apply size-14 shrink-0 rounded-full object-contain;
}

.footer-brand > span {
  @apply grid gap-1;
}

.footer-brand strong {
  @apply block uppercase tracking-[0.24em];
  font-size: clamp(1.35rem, 2.5vw, 2.2rem);
  line-height: 1;
  color: #fff8ee;
}

.footer-brand em {
  @apply block uppercase tracking-[0.28em];
  font-style: normal;
  font-size: clamp(0.72rem, 1.25vw, 1rem);
  line-height: 1.1;
  color: rgba(255, 248, 238, 0.82);
}

.footer-grid p,
.footer-grid address {
  margin: 0.75rem 0 0;
  color: rgba(255, 248, 238, 0.76);
  line-height: 1.55;
  font-style: normal;
}

.footer-grid address,
.footer-social {
  display: grid;
  gap: 0.55rem;
}

.footer-social a {
  justify-content: flex-start;
  font-weight: 800;
}

@media (max-width: 1100px) {
  .primary-nav {
    display: none;
  }

  .header-main {
    grid-template-columns: auto 1fr;
  }

  .menu-button {
    display: inline-grid;
  }

  .hero-grid,
  .vmg-layout,
  .research-layout,
  .community-layout,
  .workflow-layout,
  .footer-grid {
    grid-template-columns: 1fr;
  }

  .workflow-panel {
    @apply pr-0;
  }

  .updates-panel {
    @apply mt-10 border-l-0 border-t pl-0 pt-10;
  }

  .workflow-cta__inner {
    @apply items-start;
  }

  .unifast-feature-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .unifast-feature:nth-child(odd) {
    @apply pl-0;
    border-left: 0;
  }

  .hero-section {
    min-height: auto;
  }

  .hero-copy {
    text-align: center;
  }

  .hero-actions,
  .hero-location {
    justify-content: center;
  }

  .hero-video-wall {
    max-width: 42rem;
  }

  .news-grid,
  .program-grid,
  .feature-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 720px) {
  .landing-container {
    width: min(100% - 1.5rem, 40rem);
  }

  .header-main {
    padding-inline: 1rem;
    min-height: 4rem;
  }

  .brand img {
    width: 150px;
  }

  .assistant-button,
  .portal-link {
    display: none;
  }

  .hero-grid {
    padding: 0.75rem 0 1.75rem;
  }

  .hero-copy h1 {
    max-width: none;
    font-size: clamp(1.75rem, 8.5vw, 2.5rem);
  }

  .hero-video-wall {
    gap: 0.75rem;
    max-width: 30rem;
    transform: translateY(0);
  }

  .hero-video-card {
    border-radius: 0.9rem;
  }

  .hero-actions,
  .hero-actions a,
  .hymn-button {
    width: 100%;
  }

  .workflow-layout {
    @apply py-10;
  }

  .workflow-list li,
  .updates-list article {
    @apply gap-4;
    grid-template-columns: 1fr;
  }

  .workflow-cta__inner {
    @apply flex-col;
  }

  .section-head--split,
  .unifast-feature-grid,
  .news-grid,
  .program-grid,
  .feature-grid,
  .services-grid {
    grid-template-columns: 1fr;
  }

  .unifast-heading-row {
    @apply items-center;
  }

  .unifast-heading-row img {
    @apply size-12;
  }

  .unifast-feature {
    @apply border-l-0 px-0 py-5;
    border-top: 1px solid rgba(107, 16, 32, 0.16);
  }

  .unifast-feature:first-child {
    @apply pt-0;
    border-top: 0;
  }

  .news-head {
    grid-template-columns: auto minmax(0, 1fr);
  }

  .news-video-feature {
    justify-self: start;
    width: 4.8rem;
  }

  .vmg-content {
    text-align: left;
  }

  .service-tile {
    grid-template-columns: auto 1fr;
  }

  .service-tile > svg {
    grid-column: 2;
    grid-row: auto;
    justify-self: start;
  }
}
</style>
