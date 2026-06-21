import { create } from "zustand";
import { mockMasterlist, type MasterlistRow } from "@/data/mockMasterlist";

interface State {
  rows: MasterlistRow[];
  activateAccount: (studentNumber: string) => void;
  setRows: (rows: MasterlistRow[]) => void;
}

export const useMasterlistStore = create<State>((set) => ({
  rows: mockMasterlist,
  activateAccount: (studentNumber) =>
    set((s) => ({
      rows: s.rows.map((r) =>
        r.studentNumber === studentNumber ? { ...r, accountStatus: "active" } : r,
      ),
    })),
  setRows: (rows) => set({ rows }),
}));
