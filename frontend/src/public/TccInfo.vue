<script setup lang="ts">
import { ArrowLeft, ExternalLink, Facebook, Globe2, Mail, MapPin, Phone, User } from "lucide-vue-next";
import { RouterLink } from "vue-router";
import headerLogo from "@/assets/tcc-header-logo.png";
import campusImage from "@/assets/landing-hero-4k.png";
import { useScrollSpy } from "@/composables/useScrollSpy";

const pageSections = [
  { id: "about-tcc", label: "About TCC" },
  { id: "campus-services", label: "Campus services" },
  { id: "official-links", label: "Official links" },
  { id: "contact-social", label: "Contact and social media" },
];

const { activeSectionId, setActiveSection } = useScrollSpy(pageSections.map((section) => section.id));
</script>

<template>
  <main class="min-h-screen bg-white text-[#1a1214]">
    <header class="border-b border-[#e7dde0] bg-white">
      <div class="mx-auto flex w-[min(76rem,calc(100%_-_2rem))] items-center justify-between py-5">
        <RouterLink to="/" class="inline-flex items-center gap-2 text-sm font-black text-[#6b1020]">
          <ArrowLeft :size="17" aria-hidden="true" />
          Back to home
        </RouterLink>
        <img :src="headerLogo" alt="Tagoloan Community College" class="h-12 w-auto" />
      </div>
    </header>

    <div class="mx-auto grid w-[min(76rem,calc(100%_-_2rem))] gap-10 py-12 lg:grid-cols-[17rem_minmax(0,1fr)] lg:py-16">
      <aside class="h-fit rounded-3xl border border-[#e7dde0] bg-[#fff9f6] p-6 lg:sticky lg:top-24">
        <p class="text-xs font-black uppercase tracking-[0.16em] text-[#6b1020]">Explore more</p>
        <h1 class="mt-3 text-2xl leading-tight text-[#1a1214]">Tagoloan Community College</h1>
        <nav class="scrollspy-nav mt-6" aria-label="On this page">
          <p>On this page</p>
          <a
            v-for="section in pageSections"
            :key="section.id"
            :href="`#${section.id}`"
            :aria-current="activeSectionId === section.id ? 'location' : undefined"
            @click="setActiveSection(section.id)"
          >
            {{ section.label }}
          </a>
        </nav>
      </aside>

      <div class="grid gap-10">
        <section id="about-tcc" class="scroll-target grid gap-10 lg:grid-cols-[0.95fr_1.05fr]">
          <figure class="m-0 overflow-hidden rounded-3xl border border-[#e7dde0] bg-[#30070e]">
            <img :src="campusImage" alt="Tagoloan Community College campus display" class="h-auto w-full object-contain" />
          </figure>

          <div>
            <p class="text-xs font-black uppercase tracking-[0.16em] text-[#6b1020]">About TCC</p>
            <h2 class="mt-3 text-4xl leading-tight lg:text-5xl">Tagoloan Community College</h2>
            <p class="mt-5 text-base leading-8 text-[#6b5e61]">
              Tagoloan Community College is a community-based institution serving learners in Tagoloan, Misamis
              Oriental. This UniFAST TES portal connects students to scholarship-related requirements, submissions,
              announcements, and public campus updates.
            </p>
          </div>
        </section>

        <section id="campus-services" class="scroll-target grid gap-4 sm:grid-cols-2">
          <article class="rounded-2xl border border-[#e7dde0] p-5">
            <span class="inline-flex items-center gap-2 font-black text-[#6b1020]">
              <MapPin :size="18" aria-hidden="true" />
              Campus
            </span>
            <p class="mt-3 leading-7 text-[#6b5e61]">Tagoloan, Misamis Oriental, Philippines</p>
          </article>

          <article class="rounded-2xl border border-[#e7dde0] p-5">
            <span class="inline-flex items-center gap-2 font-black text-[#6b1020]">
              <User :size="18" aria-hidden="true" />
              Student services
            </span>
            <p class="mt-3 leading-7 text-[#6b5e61]">TES assistance, requirements tracking, document guidance, and campus announcements.</p>
          </article>
        </section>

        <section class="grid gap-6 lg:grid-cols-2">
          <article id="official-links" class="scroll-target rounded-3xl border border-[#e7dde0] p-6">
          <h2 class="text-2xl">Official links</h2>
          <div class="mt-5 grid gap-3">
            <a class="info-link" href="https://tcc.edu.ph/" target="_blank" rel="noreferrer">
              <Globe2 :size="18" aria-hidden="true" />
              TCC official website
              <ExternalLink :size="15" aria-hidden="true" />
            </a>
            <a class="info-link" href="https://tcc.edu.ph/news" target="_blank" rel="noreferrer">
              <Globe2 :size="18" aria-hidden="true" />
              TCC news and events
              <ExternalLink :size="15" aria-hidden="true" />
            </a>
            <RouterLink class="info-link" to="/unifast">
              <Globe2 :size="18" aria-hidden="true" />
              UniFAST information
              <ExternalLink :size="15" aria-hidden="true" />
            </RouterLink>
          </div>
        </article>

        <article id="contact-social" class="scroll-target rounded-3xl border border-[#e7dde0] p-6">
          <h2 class="text-2xl">Contact and social media</h2>
          <div class="mt-5 grid gap-3">
            <a class="info-link" href="https://www.facebook.com/TagoloanCommunityCollege" target="_blank" rel="noreferrer">
              <Facebook :size="18" aria-hidden="true" />
              Tagoloan Community College on Facebook
              <ExternalLink :size="15" aria-hidden="true" />
            </a>
            <a class="info-link" href="mailto:info@tcc.edu.ph">
              <Mail :size="18" aria-hidden="true" />
              info@tcc.edu.ph
            </a>
            <a class="info-link" href="tel:+630000000000">
              <Phone :size="18" aria-hidden="true" />
              +63 (000) 000-0000
            </a>
          </div>
        </article>
        </section>
      </div>
    </div>
  </main>
</template>

<style scoped>
@reference "../assets/app.css";

.info-link {
  @apply inline-flex min-h-12 items-center gap-3 rounded-2xl border border-[#e7dde0] px-4 font-bold text-[#6b1020] transition hover:bg-[#fff9f6];
}

.info-link svg:last-child {
  @apply ml-auto;
}

.scroll-target {
  scroll-margin-top: 6.5rem;
}

.scrollspy-nav {
  @apply grid gap-1 border-t border-[#e7dde0] pt-5;
}

.scrollspy-nav p {
  @apply mb-2 mt-0 text-xs font-black uppercase tracking-[0.16em] text-[#6b5e61];
}

.scrollspy-nav a {
  @apply relative rounded-xl px-3 py-2 text-sm font-black text-[#6b5e61] transition;
}

.scrollspy-nav a::before {
  content: "";
  @apply absolute bottom-2 left-0 top-2 w-1 rounded-full bg-transparent transition;
}

.scrollspy-nav a:hover,
.scrollspy-nav a[aria-current="location"] {
  @apply bg-white text-[#6b1020];
}

.scrollspy-nav a[aria-current="location"]::before {
  @apply bg-[#6b1020];
}
</style>
