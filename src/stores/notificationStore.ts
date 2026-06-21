import { create } from "zustand";
import { mockNotifications, type Notification } from "@/data/mockNotifications";

interface State {
  items: Notification[];
  markAllRead: () => void;
  markRead: (id: string) => void;
}

export const useNotificationStore = create<State>((set) => ({
  items: mockNotifications,
  markAllRead: () => set((s) => ({ items: s.items.map((n) => ({ ...n, read: true })) })),
  markRead: (id) => set((s) => ({ items: s.items.map((n) => (n.id === id ? { ...n, read: true } : n)) })),
}));
