import { useEffect, useState } from "react";
import { useRouterState } from "@tanstack/react-router";
import { IconHelpCircle, IconX, IconArrowLeft, IconArrowRight } from "@tabler/icons-react";
import { cn } from "@/lib/utils";
import { resolveTour } from "./tour-registry";

const SEEN_KEY = "unifast.tour.seen";

function markSeen(path: string) {
  try {
    const raw = localStorage.getItem(SEEN_KEY);
    const seen = raw ? (JSON.parse(raw) as string[]) : [];
    if (!seen.includes(path)) seen.push(path);
    localStorage.setItem(SEEN_KEY, JSON.stringify(seen));
  } catch {}
}

/**
 * Renders a "?" help button that opens a guided tour explaining the current
 * page. The tour content is resolved from tour-registry by pathname.
 * Renders nothing if no tour is registered for this route.
 */
export function HelpButton({ className }: { className?: string }) {
  const pathname = useRouterState({ select: (s) => s.location.pathname });
  const tour = resolveTour(pathname);
  const [open, setOpen] = useState(false);
  const [step, setStep] = useState(0);

  // reset step whenever the tour changes / reopens
  useEffect(() => { if (!open) setStep(0); }, [open]);

  if (!tour) return null;

  const s = tour.steps[step];
  const last = step === tour.steps.length - 1;

  function close() {
    markSeen(pathname);
    setOpen(false);
  }

  return (
    <>
      <button
        type="button"
        onClick={() => setOpen(true)}
        aria-label="Show tour for this page"
        title="Show tour for this page"
        className={cn(
          "inline-flex items-center gap-1.5 h-8 px-2.5 rounded-md border bg-surface text-text-muted hover:text-text hover:bg-surface-muted text-xs font-medium",
          className,
        )}
      >
        <IconHelpCircle size={15} />
        <span className="hidden sm:inline">Tour</span>
      </button>

      {open && (
        <div className="fixed inset-0 z-50 grid place-items-center p-4">
          <div className="absolute inset-0 bg-black/40" onClick={close} />
          <div
            role="dialog"
            aria-modal="true"
            aria-labelledby="tour-title"
            className="relative w-full max-w-md rounded-xl border bg-surface shadow-xl"
          >
            <div className="flex items-start justify-between p-4 border-b">
              <div className="min-w-0">
                <p className="text-2xs font-semibold uppercase tracking-wider text-primary">
                  {tour.title} · {step + 1}/{tour.steps.length}
                </p>
                <h2 id="tour-title" className="mt-0.5 text-base font-semibold tracking-tight truncate">
                  {s.title}
                </h2>
              </div>
              <button
                onClick={close}
                aria-label="Close tour"
                className="p-1 rounded hover:bg-surface-muted text-text-muted"
              >
                <IconX size={16} />
              </button>
            </div>

            <div className="p-4 text-sm text-text leading-relaxed">{s.body}</div>

            <div className="flex items-center justify-between gap-2 px-4 py-3 border-t bg-surface-muted/30 rounded-b-xl">
              <div className="flex gap-1">
                {tour.steps.map((_, i) => (
                  <span
                    key={i}
                    className={cn(
                      "h-1.5 w-4 rounded-full transition-colors",
                      i === step ? "bg-primary" : "bg-border",
                    )}
                  />
                ))}
              </div>
              <div className="flex items-center gap-1.5">
                <button
                  type="button"
                  onClick={() => setStep((v) => Math.max(0, v - 1))}
                  disabled={step === 0}
                  className="inline-flex items-center gap-1 h-8 px-2.5 rounded-md text-xs font-medium text-text-muted hover:text-text hover:bg-surface-muted disabled:opacity-40 disabled:hover:bg-transparent"
                >
                  <IconArrowLeft size={13} /> Back
                </button>
                {last ? (
                  <button
                    type="button"
                    onClick={close}
                    className="inline-flex items-center gap-1 h-8 px-3 rounded-md text-xs font-medium bg-primary text-primary-foreground hover:opacity-90"
                  >
                    Done
                  </button>
                ) : (
                  <button
                    type="button"
                    onClick={() => setStep((v) => Math.min(tour.steps.length - 1, v + 1))}
                    className="inline-flex items-center gap-1 h-8 px-3 rounded-md text-xs font-medium bg-primary text-primary-foreground hover:opacity-90"
                  >
                    Next <IconArrowRight size={13} />
                  </button>
                )}
              </div>
            </div>
          </div>
        </div>
      )}
    </>
  );
}
