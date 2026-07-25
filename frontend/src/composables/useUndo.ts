import { toast } from "@/composables/useToast";

const DEFAULT_MS = 5000;

type UndoOptions<T> = {
  /** Message shown while waiting to commit. */
  message: string;
  description?: string;
  durationMs?: number;
  /** Snapshot / label for the undo button. */
  undoLabel?: string;
  /** Runs immediately for optimistic UI. Return a rollback fn. */
  optimistic?: () => (() => void) | void;
  /** Permanent commit after the countdown. */
  commit: () => Promise<T> | T;
  /** Called when the user undoes before commit. */
  onUndo?: () => void;
  onError?: (error: unknown) => void;
};

type Pending = {
  timer: ReturnType<typeof setTimeout>;
  toastId: string | number;
  rollback?: () => void;
};

const pending = new Map<string, Pending>();

/**
 * Schedule a destructive/update action with a 5-second undo window.
 * Optimistic UI runs immediately; the server commit waits until the timer ends.
 */
export function scheduleUndo<T>(key: string, options: UndoOptions<T>): Promise<T | null> {
  cancelUndo(key, false);

  const duration = options.durationMs ?? DEFAULT_MS;
  let rollback: (() => void) | void;
  try {
    rollback = options.optimistic?.();
  } catch (error) {
    options.onError?.(error);
    return Promise.resolve(null);
  }

  return new Promise((resolve) => {
    let settled = false;

    const toastId = toast.message(options.message, {
      description: options.description ?? "You can undo for a few seconds.",
      duration,
      action: {
        label: options.undoLabel ?? "Undo",
        onClick: () => {
          if (settled) return;
          settled = true;
          cancelUndo(key, true);
          options.onUndo?.();
          resolve(null);
        },
      },
    });

    const timer = setTimeout(async () => {
      pending.delete(key);
      if (settled) return;
      settled = true;
      try {
        const result = await options.commit();
        resolve(result);
      } catch (error) {
        rollback?.();
        options.onError?.(error);
        const message = error instanceof Error ? error.message : "Action failed.";
        toast.error(message);
        resolve(null);
      }
    }, duration);

    pending.set(key, { timer, toastId, rollback: rollback || undefined });
  });
}

export function cancelUndo(key: string, restore = true) {
  const entry = pending.get(key);
  if (!entry) return;
  clearTimeout(entry.timer);
  toast.dismiss(entry.toastId);
  if (restore) entry.rollback?.();
  pending.delete(key);
}

export function useUndo() {
  return { scheduleUndo, cancelUndo };
}
