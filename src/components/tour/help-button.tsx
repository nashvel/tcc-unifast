import { useMemo, useState } from "react";
import { useRouterState } from "@tanstack/react-router";
import { IconHelpCircle } from "@tabler/icons-react";
import type { Middleware } from "@floating-ui/react-dom";
import { Joyride, type Step, type EventData, STATUS } from "react-joyride";
import { cn } from "@/lib/utils";
import { resolveTour } from "./tour-registry";

const VIEWPORT_PADDING = 16;
const APP_ZOOM = 1.25;
const TOOLTIP_WIDTH = 340;
const scaledSize = (value: number) => value / APP_ZOOM;
const TOOLTIP_WIDTH_CSS = `min(${scaledSize(TOOLTIP_WIDTH)}px, calc((100vw - ${VIEWPORT_PADDING * 2}px) / ${APP_ZOOM}))`;
const TOOLTIP_MAX_HEIGHT_CSS = `calc((100dvh - ${VIEWPORT_PADDING * 2}px) / ${APP_ZOOM})`;

const viewportClampMiddleware: Middleware = {
  name: "viewportClamp",
  fn({ x, y, rects }) {
    const parsedZoom = Number.parseFloat(getComputedStyle(document.documentElement).zoom);
    const renderedZoom = document.documentElement.getBoundingClientRect().width / window.innerWidth;
    const zoom = renderedZoom > 1 ? renderedZoom : Number.isFinite(parsedZoom) && parsedZoom > 1 ? parsedZoom : APP_ZOOM;
    const padding = VIEWPORT_PADDING / zoom;
    const maxX = Math.max(padding, window.innerWidth / zoom - rects.floating.width - padding);
    const maxY = Math.max(padding, window.innerHeight / zoom - rects.floating.height - padding);

    return {
      x: Math.min(Math.max(x, padding), maxX),
      y: Math.min(Math.max(y, padding), maxY),
    };
  },
};

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
        isFixed: true,
        placement: s.target === "body" ? "center" : "bottom",
        offset: 10,
        scrollOffset: 112,
        scrollDuration: 240,
        skipBeacon: true,
        skipScroll: false,
        spotlightPadding: 8,
        spotlightRadius: 8,
        targetWaitTimeout: 1500,
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
        floatingOptions={{
          strategy: "fixed",
          autoUpdate: {
            ancestorScroll: true,
            ancestorResize: true,
            elementResize: true,
            layoutShift: true,
            animationFrame: true,
          },
          flipOptions: {
            padding: VIEWPORT_PADDING,
            rootBoundary: "viewport",
            fallbackStrategy: "bestFit",
          },
          shiftOptions: {
            padding: VIEWPORT_PADDING,
            rootBoundary: "viewport",
            crossAxis: true,
          },
          middleware: [viewportClampMiddleware],
        }}
        options={{
          buttons: ["skip", "back", "primary"],
          primaryColor: "var(--color-primary)",
          textColor: "var(--color-text)",
          backgroundColor: "var(--color-surface)",
          arrowColor: "var(--color-surface)",
          overlayColor: "rgba(0,0,0,0.5)",
          overlayClickAction: false,
          scrollOffset: 112,
          spotlightPadding: 8,
          zIndex: 10000,
          width: TOOLTIP_WIDTH_CSS,
        }}
        styles={{
          floater: {
            maxWidth: TOOLTIP_WIDTH_CSS,
            pointerEvents: "auto",
          },
          tooltip: {
            borderRadius: 12,
            boxSizing: "border-box",
            display: "flex",
            flexDirection: "column",
            fontSize: 14,
            maxHeight: TOOLTIP_MAX_HEIGHT_CSS,
            maxWidth: TOOLTIP_WIDTH_CSS,
            overflow: "hidden",
            padding: 16,
            width: TOOLTIP_WIDTH_CSS,
          },
          tooltipContainer: {
            flex: "1 1 auto",
            minHeight: 0,
            overflowY: "auto",
            overscrollBehavior: "contain",
            paddingRight: 2,
            textAlign: "left",
          },
          tooltipTitle: { fontSize: 15, fontWeight: 600, marginBottom: 6 },
          tooltipContent: { padding: "6px 0", fontSize: 13, lineHeight: 1.5 },
          tooltipFooter: {
            alignItems: "center",
            display: "flex",
            flex: "0 0 auto",
            flexWrap: "wrap",
            gap: 8,
            justifyContent: "flex-end",
            marginTop: 12,
            paddingTop: 10,
          },
          tooltipFooterSpacer: { flex: "1 1 auto", minWidth: 56 },
          buttonPrimary: {
            borderRadius: 6,
            fontSize: 13,
            minHeight: 36,
            padding: "8px 14px",
            whiteSpace: "nowrap",
          },
          buttonBack: {
            color: "var(--color-text-muted)",
            fontSize: 13,
            minHeight: 36,
            padding: "8px 10px",
            whiteSpace: "nowrap",
          },
          buttonSkip: {
            color: "var(--color-text-muted)",
            fontSize: 13,
            minHeight: 36,
            padding: "8px 10px",
            whiteSpace: "nowrap",
          },
          buttonClose: { display: "none" },
        }}
      />
    </>
  );
}
