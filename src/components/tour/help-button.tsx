import { useEffect, useMemo, useState } from "react";
import { useRouterState } from "@tanstack/react-router";
import { IconHelpCircle } from "@tabler/icons-react";
import type { Middleware } from "@floating-ui/react-dom";
import { Joyride, type Step, type EventData, STATUS } from "react-joyride";
import { cn } from "@/lib/utils";
import { resolveTour } from "./tour-registry";

const VIEWPORT_PADDING = 16;
const APP_ZOOM = 1.25;
const TOOLTIP_WIDTH = 340;
const TOOLTIP_WIDTH_CSS = `min(${TOOLTIP_WIDTH}px, calc(100vw - ${VIEWPORT_PADDING * 2}px))`;
const TOOLTIP_MAX_HEIGHT_CSS = `calc(100dvh - ${VIEWPORT_PADDING * 2}px)`;

const viewportClampMiddleware: Middleware = {
  name: "viewportClamp",
  fn({ x, y, rects }) {
    const zoom = APP_ZOOM;
    const padding = VIEWPORT_PADDING / zoom;
    const safeWidth = Math.max(rects.floating.width, TOOLTIP_WIDTH);
    const safeHeight = rects.floating.height;
    const maxX = Math.max(padding, window.innerWidth / zoom - safeWidth - padding);
    const maxY = Math.max(padding, window.innerHeight / zoom - safeHeight - padding);

    return {
      x: Math.min(Math.max(x, padding), maxX),
      y: Math.min(Math.max(y, padding), maxY),
    };
  },
};

function isTourDebugEnabled() {
  if (typeof window === "undefined") return false;
  try {
    if (new URLSearchParams(window.location.search).get("tourDebug") === "1") return true;
    if (window.localStorage.getItem("tourDebug") === "1") return true;
  } catch {
    /* ignore */
  }
  return false;
}

function logTourDebug(label: string, payload: Record<string, unknown>) {
  if (!isTourDebugEnabled()) return;
  // eslint-disable-next-line no-console
  console.groupCollapsed(`[tour] ${label}`);
  for (const [k, v] of Object.entries(payload)) {
    // eslint-disable-next-line no-console
    console.log(k, v);
  }
  // eslint-disable-next-line no-console
  console.groupEnd();
}

function clampJoyrideTooltipToViewport() {
  const floater = document.querySelector<HTMLElement>("#react-joyride-portal .react-joyride__floater[data-testid='floater']");
  if (!floater) return;

  const rect = floater.getBoundingClientRect();
  const padding = VIEWPORT_PADDING;
  let deltaX = 0;
  let deltaY = 0;

  if (rect.left < padding) deltaX = padding - rect.left;
  if (rect.right > window.innerWidth - padding) deltaX = window.innerWidth - padding - rect.right;
  if (rect.top < padding) deltaY = padding - rect.top;
  if (rect.bottom > window.innerHeight - padding) deltaY = window.innerHeight - padding - rect.bottom;

  if (deltaX === 0 && deltaY === 0) {
    logTourDebug("tooltip bounds (in viewport)", {
      rect: rect.toJSON(),
      viewport: { w: window.innerWidth, h: window.innerHeight },
    });
    return;
  }

  const transform = floater.style.transform;
  const match = transform.match(/translate\(([-\d.]+)px,\s*([-\d.]+)px\)/);
  if (!match) return;

  const x = Number.parseFloat(match[1]);
  const y = Number.parseFloat(match[2]);
  if (!Number.isFinite(x) || !Number.isFinite(y)) return;

  floater.style.transform = `translate(${x + deltaX / APP_ZOOM}px, ${y + deltaY / APP_ZOOM}px)`;

  logTourDebug("tooltip clamped into viewport", {
    before: rect.toJSON(),
    after: floater.getBoundingClientRect().toJSON(),
    delta: { x: deltaX, y: deltaY },
    viewport: { w: window.innerWidth, h: window.innerHeight },
  });
}


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

  useEffect(() => {
    if (!run) return undefined;

    let frame = 0;
    const clamp = () => {
      window.cancelAnimationFrame(frame);
      frame = window.requestAnimationFrame(clampJoyrideTooltipToViewport);
    };
    const interval = window.setInterval(clamp, 120);

    clamp();
    window.addEventListener("resize", clamp);
    window.addEventListener("scroll", clamp, true);

    return () => {
      window.clearInterval(interval);
      window.cancelAnimationFrame(frame);
      window.removeEventListener("resize", clamp);
      window.removeEventListener("scroll", clamp, true);
    };
  }, [run]);

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
    window.requestAnimationFrame(clampJoyrideTooltipToViewport);
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
            transformOrigin: "top left",
            zoom: 1 / APP_ZOOM,
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
