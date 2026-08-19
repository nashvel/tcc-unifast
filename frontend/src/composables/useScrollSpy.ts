import { onBeforeUnmount, onMounted, ref } from "vue";

export function useScrollSpy(sectionIds: string[]) {
  const activeSectionId = ref(sectionIds[0] ?? "");
  let observer: IntersectionObserver | null = null;

  onMounted(() => {
    if (sectionIds.length === 0 || typeof IntersectionObserver === "undefined") return;

    const sections = sectionIds
      .map((id) => document.getElementById(id))
      .filter((section): section is HTMLElement => section !== null);

    observer = new IntersectionObserver(
      (entries) => {
        const visibleEntries = entries
          .filter((entry) => entry.isIntersecting)
          .sort((a, b) => b.intersectionRatio - a.intersectionRatio);

        const current = visibleEntries[0]?.target.id;
        if (current) activeSectionId.value = current;
      },
      {
        rootMargin: "-28% 0px -58% 0px",
        threshold: [0.12, 0.24, 0.4, 0.6],
      },
    );

    sections.forEach((section) => observer?.observe(section));
  });

  onBeforeUnmount(() => {
    observer?.disconnect();
    observer = null;
  });

  function setActiveSection(id: string) {
    activeSectionId.value = id;
  }

  return {
    activeSectionId,
    setActiveSection,
  };
}
