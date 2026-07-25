import { useEffect, type ReactNode } from "react";
import { cn } from "@/lib/utils";
import { IconX } from "@tabler/icons-react";

export function ConfirmModal({
  open, onClose, title, description, confirmLabel = "Confirm", onConfirm, danger,
}: {
  open: boolean; onClose: () => void; title: string; description?: string;
  confirmLabel?: string; onConfirm: () => void; danger?: boolean;
}) {
  useEffect(() => {
    if (!open) return;
    const onKey = (e: KeyboardEvent) => e.key === "Escape" && onClose();
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [open, onClose]);

  if (!open) return null;
  return (
    <div className="fixed inset-0 z-50 grid place-items-center bg-black/30 p-4" onClick={onClose}>
      <div className="bg-surface rounded-lg border shadow-lg w-full max-w-sm p-5" onClick={(e) => e.stopPropagation()}>
        <p className="text-sm font-semibold">{title}</p>
        {description && <p className="text-xs text-text-muted mt-1">{description}</p>}
        <div className="flex justify-end gap-2 mt-5">
          <button onClick={onClose} className="h-8 px-3 text-xs rounded-md border hover:bg-surface-muted">Cancel</button>
          <button
            onClick={() => { onConfirm(); onClose(); }}
            className={cn(
              "h-8 px-3 text-xs rounded-md text-white",
              danger ? "bg-danger hover:bg-danger/90" : "bg-primary hover:bg-primary-hover",
            )}
          >
            {confirmLabel}
          </button>
        </div>
      </div>
    </div>
  );
}

export function DetailDrawer({
  open, onClose, title, children,
}: { open: boolean; onClose: () => void; title: string; children: ReactNode }) {
  if (!open) return null;
  return (
    <div className="fixed inset-0 z-50 flex justify-end bg-black/30" onClick={onClose}>
      <div className="bg-surface w-full max-w-md h-full border-l shadow-lg overflow-y-auto" onClick={(e) => e.stopPropagation()}>
        <div className="flex items-center justify-between px-4 h-12 border-b sticky top-0 bg-surface">
          <p className="text-sm font-semibold">{title}</p>
          <button onClick={onClose} className="p-1.5 rounded-md hover:bg-surface-muted">
            <IconX size={16} />
          </button>
        </div>
        <div className="p-4">{children}</div>
      </div>
    </div>
  );
}
