import { ref } from "vue";

/** Staff package detail sets this so breadcrumbs show the student name instead of "Package". */
const breadcrumbTailLabel = ref<string | null>(null);

export function useBreadcrumbTail() {
  return {
    breadcrumbTailLabel,
    setBreadcrumbTail(label: string | null) {
      breadcrumbTailLabel.value = label;
    },
  };
}
