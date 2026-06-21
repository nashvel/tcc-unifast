import { create } from "zustand";
import { mockDocuments, type DocumentItem, type DocStatus } from "@/data/mockDocuments";

interface State {
  docs: DocumentItem[];
  updateStatus: (id: string, status: DocStatus, remarks?: string) => void;
}

export const useDocumentStore = create<State>((set) => ({
  docs: mockDocuments,
  updateStatus: (id, status, remarks) =>
    set((s) => ({
      docs: s.docs.map((d) => (d.id === id ? { ...d, status, remarks: remarks ?? d.remarks } : d)),
    })),
}));
