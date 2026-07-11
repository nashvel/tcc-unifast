import { useMemo, useState } from "react";
import { useRouterState } from "@tanstack/react-router";
import { IconHelpCircle } from "@tabler/icons-react";
import { Joyride, type Step, type EventData, STATUS } from "react-joyride";
import { cn } from "@/lib/utils";
import { resolveTour } from "./tour-registry";

/**
 * Renders a "Tour" button that starts a react-joyride walkthrough for the
 * current route. Highlights the actual card / element on the page via CSS
 * selectors defined in tour-registry.ts. Renders nothing if no tour is
 * registered for the current path.
 */
export function HelpButton({ className }: { className?: string }) {
  const pathname = useRouterState({ select: (s) => s.location.pathname });
  const tour = resolveTour(pathname);
  const [run, setRun] = useState(false);

  const steps: Step[] = useMemo(
    () =>
      (tour?.steps ?? []).map((s) => ({
        target: s.target,
        title: s.title,
        content: s.body,
        disableBeacon: true,
        placement: s.target === "body" ? "center" : "auto",
        scrollTo: true,
        offset: 12,
      })),
    [tour],
  );

  if (!tour) return null;

  function onCallback(data: EventData) {
    const finished: string[] = [STATUS.FINISHED, STATUS.SKIPPED];
    if (finished.includes(data.status)) setRun(false);
  }

  return (
    <>
      <button
        type="button"
        onClick={() => setRun(true)}
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

      <Joyride
        steps={steps}
        run={run}
        continuous
        scrollToFirstStep

        onEvent={onCallback}
        locale={{ back: "Back", close: "Close", last: "Done", next: "Next", skip: "Skip" }}
        options={{
          primaryColor: "var(--color-primary)",
          textColor: "var(--color-text)",
          backgroundColor: "var(--color-surface)",
          arrowColor: "var(--color-surface)",
          overlayColor: "rgba(0,0,0,0.5)",
          zIndex: 10000,
          width: 340,
        }}
        styles={{
          tooltip: { borderRadius: 12, fontSize: 14, padding: 16, maxWidth: "calc(100vw - 24px)" },
          tooltipContainer: { textAlign: "left" },
          tooltipTitle: { fontSize: 15, fontWeight: 600, marginBottom: 6 },
          tooltipContent: { padding: "6px 0", fontSize: 13, lineHeight: 1.5 },
          buttonPrimary: { borderRadius: 6, fontSize: 13, padding: "8px 14px" },
          buttonBack: { color: "var(--color-text-muted)", fontSize: 13 },
          buttonSkip: { color: "var(--color-text-muted)", fontSize: 13 },
          buttonClose: { display: "none" },
        }}
      />
    </>
  );
}
