<script setup lang="ts">
import {
  ArrowLeft,
  BookOpen,
  CheckCircle2,
  ClipboardList,
  Coins,
  GraduationCap,
  Landmark,
  ShieldCheck,
  Users,
} from "lucide-vue-next";
import { RouterLink } from "vue-router";
import unifastLogo from "@/assets/unifast-logo.png";
import { useScrollSpy } from "@/composables/useScrollSpy";

const pageSections = [
  { id: "why-unifast", label: "Why UniFAST" },
  { id: "tes-overview", label: "TES overview" },
  { id: "tes-benefits", label: "TES benefits" },
  { id: "qualifications", label: "Qualifications" },
  { id: "beneficiaries", label: "Beneficiaries" },
  { id: "application-process", label: "How to apply" },
  { id: "requirements", label: "Requirements" },
  { id: "additional-benefits", label: "Additional benefits" },
  { id: "available-programs", label: "Available programs" },
  { id: "not-eligible", label: "Not eligible" },
];

const { activeSectionId, setActiveSection } = useScrollSpy(pageSections.map((section) => section.id));

const lucPublicBenefits = [
  "Up to PHP 40,000 per academic year for education-related expenses, including rental or purchase of computer, books and supplies, transportation, lodging, and board expenses.",
];

const privateBenefits = [
  "Up to PHP 20,000 per academic year for tuition and other school fees.",
  "Up to PHP 40,000 per academic year for education-related expenses, including rental or purchase of computer, books and supplies, transportation, lodging, and board expenses.",
];

const qualifications = [
  "Filipino citizen.",
  "Qualified for admission and retention requirements of the Higher Education Institution and has not exceeded the Maximum Residency Policy or Maximum Residency Rule.",
  "Has not been expelled from any Higher Education Institution.",
  "Enrolled in any SUC, CHED-recognized LUC, or private school program listed in the CHED Registry.",
  "Qualified under future policies that may be implemented by the UniFAST Board.",
];

const prioritizedBeneficiaries = [
  "Current TES grantees and ESGP-PA beneficiaries.",
  "Students included in the DSWD-National Household Targeting Office Listahanan, ranked by estimated per capita household income.",
  "Students not included in Listahanan but ranked by estimated per capita household income based on proof of income documents required by the UniFAST Board.",
];

const applicationSteps = [
  "After enrollment, talk to the TES Focal Person of your school about the TES Portal application process.",
  "The TES Focal Person submits TES applicant information into the TES Portal.",
  "UniFAST conducts nationwide assessment through name-matching, verification, validation, and ranking using school data.",
  "The HEI TES Portal shows the resulting list of qualified grantees.",
  "The school informs eligible grantees and collects documentary requirements.",
  "The school submits certified documentary requirements to the CHED Regional Office for verification and validation.",
  "After verification, the UniFAST Board approves the masterlist of qualified grantees and provides it to schools.",
  "The school receives the funds and distributes grants to TES beneficiaries.",
];

const requirements = [
  "Certification of Registration or Enrollment.",
  "Assessment of Fees.",
  "Certificate of Residency, if applicable.",
  "Photocopy of PWD ID, if applicable.",
];

const higherEducationPrograms = [
  "Undergraduate course or program in any Board-approved SUC campus.",
  "Undergraduate courses with Certificates of Program Compliance in CHED-recognized LUCs.",
  "Undergraduate courses with Government Recognition in private institutions listed in the UniFAST Registry of Quality-Assured Institutions and Programs.",
];

const tvetPrograms = [
  "TVET program with Certificate of Program Registration and officially listed in the TESDA Compendium of Registered Programs in any public or private technical-vocational institution.",
];

const higherEducationNotEligible = [
  "Students enrolled in programs or institutions not included in the Registry of CHED-recognized programs and institutions.",
  "Students who have exceeded their program's Maximum Residency Rule limit.",
];

const tvetNotEligible = [
  "Learners enrolled in TVET programs not in the Registry of TESDA.",
  "Learners enrolled in TESDA-registered programs in any TVI who availed of government-funded StuFAPs other than Free TVET.",
];
</script>

<template>
  <main class="min-h-screen bg-white text-[#1a1214]">
    <section class="border-b border-[#e7dde0] bg-white">
      <div class="mx-auto flex w-[min(72rem,calc(100%_-_2rem))] items-center justify-between py-5">
        <RouterLink to="/" class="inline-flex items-center gap-2 text-sm font-black text-[#6b1020]">
          <ArrowLeft :size="17" aria-hidden="true" />
          Back to home
        </RouterLink>
      </div>
    </section>

    <section class="mx-auto grid w-[min(72rem,calc(100%_-_2rem))] gap-10 py-12 lg:grid-cols-[0.82fr_1.18fr] lg:py-16">
      <aside class="h-fit rounded-3xl border border-[#e7dde0] bg-[#fff9f6] p-8 lg:sticky lg:top-24">
        <img :src="unifastLogo" alt="UniFAST logo" class="h-24 w-24 rounded-2xl object-contain" />
        <p class="mt-6 text-xs font-black uppercase tracking-[0.16em] text-[#6b1020]">Republic Act No. 10687</p>
        <h1 class="mt-3 text-4xl leading-tight text-[#1a1214] lg:text-5xl">
          What is UniFAST?
        </h1>
        <p class="mt-5 text-base leading-8 text-[#6b5e61]">
          The Unified Financial Assistance System for Tertiary Education Act, or UniFAST, puts government-funded
          student financial assistance programs under one coordinated system for tertiary education.
        </p>
        <nav class="scrollspy-nav mt-8" aria-label="On this page">
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

      <div class="grid gap-4">
        <article id="why-unifast" class="scroll-target rounded-2xl border border-[#e7dde0] p-6">
          <span class="inline-flex items-center gap-2 font-black text-[#6b1020]">
            <ShieldCheck :size="18" aria-hidden="true" />
            Why UniFAST was established
          </span>
          <div class="mt-3 space-y-4 leading-8 text-[#6b5e61]">
            <p>
              The Unified Financial Assistance System for Tertiary Education Act, or UniFAST, also known as
              Republic Act No. 10687, was signed into law on 15 October 2015.
            </p>
            <p>
              UniFAST reconciles, improves, strengthens, expands, and puts under one body all government-funded
              modalities of Student Financial Assistance Programs for tertiary education and special purpose
              education assistance in both public and private institutions.
            </p>
            <p>
              These modalities include scholarships, grants-in-aid, student loans, and other specialized forms of
              student financial assistance formulated by the UniFAST Board.
            </p>
          </div>
        </article>

        <article id="tes-overview" class="scroll-target rounded-2xl border border-[#e7dde0] p-6">
          <span class="inline-flex items-center gap-2 font-black text-[#6b1020]">
            <GraduationCap :size="18" aria-hidden="true" />
            What is the Tertiary Education Subsidy?
          </span>
          <p class="mt-3 leading-8 text-[#6b5e61]">
            The Tertiary Education Subsidy is a program under Republic Act No. 10931, the Universal Access to
            Quality Tertiary Education Act. The law includes Free Higher Education, Free Technical-Vocational
            Education and Training, and the Student Loan Program.
          </p>
        </article>

        <article id="tes-benefits" class="scroll-target rounded-2xl border border-[#e7dde0] p-6">
          <span class="inline-flex items-center gap-2 font-black text-[#6b1020]">
            <Coins :size="18" aria-hidden="true" />
            TES benefits
          </span>
          <div class="mt-4 grid gap-4 md:grid-cols-2">
            <div class="rounded-2xl bg-[#fff9f6] p-5">
              <h2 class="text-base font-black text-[#1a1214]">LUCs, LGU-run TVIs, SUCs, and TESDA TTIs</h2>
              <ul class="mt-3 space-y-2 text-sm leading-7 text-[#6b5e61]">
                <li v-for="benefit in lucPublicBenefits" :key="benefit" class="flex gap-2">
                  <CheckCircle2 :size="16" class="mt-1 shrink-0 text-[#6b1020]" aria-hidden="true" />
                  <span>{{ benefit }}</span>
                </li>
              </ul>
            </div>
            <div class="rounded-2xl bg-[#fff9f6] p-5">
              <h2 class="text-base font-black text-[#1a1214]">Private TVIs and HEIs</h2>
              <ul class="mt-3 space-y-2 text-sm leading-7 text-[#6b5e61]">
                <li v-for="benefit in privateBenefits" :key="benefit" class="flex gap-2">
                  <CheckCircle2 :size="16" class="mt-1 shrink-0 text-[#6b1020]" aria-hidden="true" />
                  <span>{{ benefit }}</span>
                </li>
              </ul>
            </div>
          </div>
        </article>

        <article id="qualifications" class="scroll-target rounded-2xl border border-[#e7dde0] p-6">
          <span class="inline-flex items-center gap-2 font-black text-[#6b1020]">
            <ClipboardList :size="18" aria-hidden="true" />
            Qualifications
          </span>
          <ul class="mt-4 space-y-3 leading-7 text-[#6b5e61]">
            <li v-for="item in qualifications" :key="item" class="flex gap-3">
              <CheckCircle2 :size="17" class="mt-1 shrink-0 text-[#6b1020]" aria-hidden="true" />
              <span>{{ item }}</span>
            </li>
          </ul>
        </article>

        <article id="beneficiaries" class="scroll-target rounded-2xl border border-[#e7dde0] p-6">
          <span class="inline-flex items-center gap-2 font-black text-[#6b1020]">
            <Users :size="18" aria-hidden="true" />
            Who can avail of TES?
          </span>
          <p class="mt-3 leading-8 text-[#6b5e61]">
            The following individuals are prioritized beneficiaries of TES:
          </p>
          <ul class="mt-4 space-y-3 leading-7 text-[#6b5e61]">
            <li v-for="item in prioritizedBeneficiaries" :key="item" class="flex gap-3">
              <CheckCircle2 :size="17" class="mt-1 shrink-0 text-[#6b1020]" aria-hidden="true" />
              <span>{{ item }}</span>
            </li>
          </ul>
          <p class="mt-4 leading-8 text-[#6b5e61]">
            This prioritization does not apply to Filipino students in cities and municipalities with no existing
            SUC or LUC campus.
          </p>
        </article>

        <article id="application-process" class="scroll-target rounded-2xl border border-[#e7dde0] p-6">
          <span class="inline-flex items-center gap-2 font-black text-[#6b1020]">
            <BookOpen :size="18" aria-hidden="true" />
            How to apply for TES
          </span>
          <ol class="mt-4 space-y-3 leading-7 text-[#6b5e61]">
            <li v-for="(step, index) in applicationSteps" :key="step" class="flex gap-3">
              <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-[#6b1020] text-xs font-black text-white">
                {{ index + 1 }}
              </span>
              <span>{{ step }}</span>
            </li>
          </ol>
        </article>

        <article id="requirements" class="scroll-target rounded-2xl border border-[#e7dde0] p-6">
          <span class="inline-flex items-center gap-2 font-black text-[#6b1020]">
            <ClipboardList :size="18" aria-hidden="true" />
            Documentary requirements
          </span>
          <ul class="mt-4 space-y-3 leading-7 text-[#6b5e61]">
            <li v-for="item in requirements" :key="item" class="flex gap-3">
              <CheckCircle2 :size="17" class="mt-1 shrink-0 text-[#6b1020]" aria-hidden="true" />
              <span>{{ item }}</span>
            </li>
          </ul>
        </article>

        <article id="additional-benefits" class="scroll-target rounded-2xl border border-[#e7dde0] p-6">
          <span class="inline-flex items-center gap-2 font-black text-[#6b1020]">
            <Landmark :size="18" aria-hidden="true" />
            Additional TES benefits
          </span>
          <div class="mt-3 space-y-4 leading-8 text-[#6b5e61]">
            <p>
              PWD beneficiaries may receive additional funding of up to PHP 30,000 to help with special services,
              personal assistance, transportation, equipment, and legitimately incurred supplies.
            </p>
            <p>
              Students enrolled in programs requiring a professional license or certification may receive up to
              PHP 10,000 for the one-time cost of receiving first professional credentials or qualifications,
              including notarial fees, review class fees, insurance premium fees, and documentation fees.
            </p>
          </div>
        </article>

        <article id="available-programs" class="scroll-target rounded-2xl border border-[#e7dde0] p-6">
          <span class="inline-flex items-center gap-2 font-black text-[#6b1020]">
            <GraduationCap :size="18" aria-hidden="true" />
            Courses and programs available for TES grantees
          </span>
          <div class="mt-4 grid gap-4 md:grid-cols-2">
            <div class="rounded-2xl bg-[#fff9f6] p-5">
              <h2 class="text-base font-black text-[#1a1214]">Higher Education</h2>
              <ul class="mt-3 space-y-2 text-sm leading-7 text-[#6b5e61]">
                <li v-for="item in higherEducationPrograms" :key="item" class="flex gap-2">
                  <CheckCircle2 :size="16" class="mt-1 shrink-0 text-[#6b1020]" aria-hidden="true" />
                  <span>{{ item }}</span>
                </li>
              </ul>
            </div>
            <div class="rounded-2xl bg-[#fff9f6] p-5">
              <h2 class="text-base font-black text-[#1a1214]">TVET</h2>
              <ul class="mt-3 space-y-2 text-sm leading-7 text-[#6b5e61]">
                <li v-for="item in tvetPrograms" :key="item" class="flex gap-2">
                  <CheckCircle2 :size="16" class="mt-1 shrink-0 text-[#6b1020]" aria-hidden="true" />
                  <span>{{ item }}</span>
                </li>
              </ul>
            </div>
          </div>
        </article>

        <article id="not-eligible" class="scroll-target rounded-2xl border border-[#e7dde0] p-6">
          <span class="inline-flex items-center gap-2 font-black text-[#6b1020]">
            <ShieldCheck :size="18" aria-hidden="true" />
            Who are not eligible for TES benefits?
          </span>
          <div class="mt-4 grid gap-4 md:grid-cols-2">
            <div class="rounded-2xl bg-[#fff9f6] p-5">
              <h2 class="text-base font-black text-[#1a1214]">Higher Education</h2>
              <ul class="mt-3 space-y-2 text-sm leading-7 text-[#6b5e61]">
                <li v-for="item in higherEducationNotEligible" :key="item" class="flex gap-2">
                  <CheckCircle2 :size="16" class="mt-1 shrink-0 text-[#6b1020]" aria-hidden="true" />
                  <span>{{ item }}</span>
                </li>
              </ul>
            </div>
            <div class="rounded-2xl bg-[#fff9f6] p-5">
              <h2 class="text-base font-black text-[#1a1214]">TVET</h2>
              <ul class="mt-3 space-y-2 text-sm leading-7 text-[#6b5e61]">
                <li v-for="item in tvetNotEligible" :key="item" class="flex gap-2">
                  <CheckCircle2 :size="16" class="mt-1 shrink-0 text-[#6b1020]" aria-hidden="true" />
                  <span>{{ item }}</span>
                </li>
              </ul>
            </div>
          </div>
        </article>
      </div>
    </section>
  </main>
</template>

<style scoped>
@reference "../assets/app.css";

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
