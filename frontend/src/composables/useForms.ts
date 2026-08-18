import { useQuery, useMutation, useQueryClient } from "@tanstack/vue-query";
import { queryKeys } from "@/api/queryKeys";
import * as formsApi from "@/api/forms";
import type { ListQuery } from "@/api/types";
import { computed, toValue, type Ref } from "vue";

// ─────────────────────────────────────────────────────────
// Admin Queries
// ─────────────────────────────────────────────────────────

export function useFormList(params: Ref<ListQuery> | ListQuery = {}) {
  const p = computed(() => toValue(params));
  return useQuery({
    queryKey: computed(() => queryKeys.forms(p.value)),
    queryFn: () => formsApi.listForms(p.value),
  });
}

export function useFormDetail(id: Ref<string | number>) {
  return useQuery({
    queryKey: computed(() => queryKeys.form(id.value)),
    queryFn: () => formsApi.getForm(id.value),
    enabled: computed(() => !!id.value),
  });
}

export function useCreateForm() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: formsApi.createForm,
    onSuccess: () => qc.invalidateQueries({ queryKey: ["forms"] }),
  });
}

export function useUpdateForm(id: Ref<string | number>) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (data: Parameters<typeof formsApi.updateForm>[1]) => formsApi.updateForm(id.value, data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: queryKeys.form(id.value) });
      qc.invalidateQueries({ queryKey: ["forms"] });
    },
  });
}

export function useDeleteForm() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: string | number) => formsApi.deleteForm(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["forms"] }),
  });
}

export function useToggleForm() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: string | number) => formsApi.toggleForm(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["forms"] }),
  });
}

export function usePublishForm() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: string | number) => formsApi.publishForm(id),
    onSuccess: (_data, id) => {
      qc.invalidateQueries({ queryKey: queryKeys.form(id) });
      qc.invalidateQueries({ queryKey: ["forms"] });
    },
  });
}

export function useCloseForm() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: string | number) => formsApi.closeForm(id),
    onSuccess: (_data, id) => {
      qc.invalidateQueries({ queryKey: queryKeys.form(id) });
      qc.invalidateQueries({ queryKey: ["forms"] });
    },
  });
}

export function useRegenerateToken() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: string | number) => formsApi.regenerateFormToken(id),
    onSuccess: (_data, id) => qc.invalidateQueries({ queryKey: queryKeys.form(id) }),
  });
}

// ─────────────────────────────────────────────────────────
// Analytics
// ─────────────────────────────────────────────────────────

export function useFormAnalytics(id: Ref<string | number>) {
  return useQuery({
    queryKey: computed(() => ["forms", id.value, "analytics"]),
    queryFn: () => formsApi.getFormAnalytics(id.value),
    enabled: computed(() => !!id.value),
    select: (res) => res.data,
  });
}

// ─────────────────────────────────────────────────────────
// Sections
// ─────────────────────────────────────────────────────────

export function useCreateSection(formId: Ref<string | number>) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (data: Parameters<typeof formsApi.createSection>[1]) => formsApi.createSection(formId.value, data),
    onSuccess: () => qc.invalidateQueries({ queryKey: queryKeys.form(formId.value) }),
  });
}

export function useUpdateSection(formId: Ref<string | number>) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ sectionId, data }: { sectionId: number; data: Parameters<typeof formsApi.updateSection>[2] }) =>
      formsApi.updateSection(formId.value, sectionId, data),
    onSuccess: () => qc.invalidateQueries({ queryKey: queryKeys.form(formId.value) }),
  });
}

export function useDeleteSection(formId: Ref<string | number>) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (sectionId: number) => formsApi.deleteSection(formId.value, sectionId),
    onSuccess: () => qc.invalidateQueries({ queryKey: queryKeys.form(formId.value) }),
  });
}

export function useReorderSections(formId: Ref<string | number>) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (order: number[]) => formsApi.reorderSections(formId.value, order),
    onSuccess: () => qc.invalidateQueries({ queryKey: queryKeys.form(formId.value) }),
  });
}

// ─────────────────────────────────────────────────────────
// Field mutations
// ─────────────────────────────────────────────────────────

export function useAddField(formId: Ref<string | number>) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (data: Parameters<typeof formsApi.addField>[1]) =>
      formsApi.addField(formId.value, data),
    onSuccess: () => qc.invalidateQueries({ queryKey: queryKeys.form(formId.value) }),
  });
}

export function useUpdateField(formId: Ref<string | number>) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ fieldId, data }: { fieldId: number; data: Parameters<typeof formsApi.updateField>[2] }) =>
      formsApi.updateField(formId.value, fieldId, data),
    onSuccess: () => qc.invalidateQueries({ queryKey: queryKeys.form(formId.value) }),
  });
}

export function useDeleteField(formId: Ref<string | number>) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (fieldId: number) => formsApi.deleteField(formId.value, fieldId),
    onSuccess: () => qc.invalidateQueries({ queryKey: queryKeys.form(formId.value) }),
  });
}

export function useReorderFields(formId: Ref<string | number>) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (order: number[]) => formsApi.reorderFields(formId.value, order),
    onSuccess: () => qc.invalidateQueries({ queryKey: queryKeys.form(formId.value) }),
  });
}

// ─────────────────────────────────────────────────────────
// Responses (admin + staff)
// ─────────────────────────────────────────────────────────

export function useFormResponses(formId: Ref<string | number>, params: Ref<ListQuery> = { value: {} } as Ref<ListQuery>) {
  return useQuery({
    queryKey: computed(() => queryKeys.formResponses(formId.value, params.value)),
    queryFn: () => formsApi.listFormResponses(formId.value, params.value),
    enabled: computed(() => !!formId.value),
  });
}

export function useFormSecurityLogs(formId: Ref<string | number>, params: Ref<ListQuery> = { value: {} } as Ref<ListQuery>) {
  return useQuery({
    queryKey: computed(() => queryKeys.formSecurityLogs(formId.value, params.value)),
    queryFn: () => formsApi.getSecurityLogs(formId.value, params.value),
    enabled: computed(() => !!formId.value),
  });
}

// ─────────────────────────────────────────────────────────
// Grantee portal
// ─────────────────────────────────────────────────────────

export function useAssignedForms() {
  return useQuery({
    queryKey: queryKeys.assignedForms,
    queryFn: formsApi.getAssignedForms,
  });
}

export function useFormSchema(id: Ref<string | number>) {
  return useQuery({
    queryKey: computed(() => queryKeys.form(id.value)),
    queryFn: () => formsApi.getFormSchema(id.value),
    enabled: computed(() => !!id.value),
  });
}

export function useSubmitFormResponse(id: Ref<string | number>) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (data: FormData | Record<string, unknown>) => formsApi.submitFormResponse(id.value, data),
    onSuccess: () => qc.invalidateQueries({ queryKey: queryKeys.assignedForms }),
  });
}

// ─────────────────────────────────────────────────────────
// Public
// ─────────────────────────────────────────────────────────

export function usePublicForm(token: Ref<string>) {
  return useQuery({
    queryKey: computed(() => queryKeys.publicForm(token.value)),
    queryFn: () => formsApi.getPublicForm(token.value),
    enabled: computed(() => !!token.value),
    retry: false,
  });
}

export function useSubmitPublicForm(token: Ref<string>) {
  return useMutation({
    mutationFn: (data: FormData | Record<string, unknown>) =>
      formsApi.submitPublicFormResponse(token.value, data),
  });
}
