import { useEffect, useRef, useState } from "react";
import { AnimatePresence, motion } from "framer-motion";
import Lottie from "lottie-react";
import mascotAsset from "@/assets/mascot.json.asset.json";

interface Props {
  /** % of required documents submitted (0–100). */
  completion: number;
  studentName?: string;
}

/** Mounts children only once their wrapper scrolls into view. */
function useInView<T extends HTMLElement>(rootMargin = "200px") {
  const ref = useRef<T | null>(null);
  const [inView, setInView] = useState(false);
  useEffect(() => {
    if (inView || !ref.current) return;
    if (typeof IntersectionObserver === "undefined") {
      setInView(true);
      return;
    }
    const io = new IntersectionObserver(
      (entries) => {
        if (entries.some((e) => e.isIntersecting)) {
          setInView(true);
          io.disconnect();
        }
      },
      { rootMargin },
    );
    io.observe(ref.current);
    return () => io.disconnect();
  }, [inView, rootMargin]);
  return { ref, inView };
}

/** Lazily fetches the Lottie JSON once `enabled` flips true. */
function useLottieData(enabled: boolean) {
  const [data, setData] = useState<unknown | null>(null);
  useEffect(() => {
    if (!enabled || data) return;
    let cancelled = false;
    fetch(mascotAsset.url)
      .then((r) => r.json())
      .then((json) => {
        if (!cancelled) setData(json);
      })
      .catch(() => {});
    return () => {
      cancelled = true;
    };
  }, [enabled, data]);
  return data;
}

export function DashboardMascot({ completion, studentName }: Props) {
  const { ref: mascotRef, inView } = useInView<HTMLButtonElement>("200px");
  const animationData = useLottieData(inView);
  const [bubbleIdx, setBubbleIdx] = useState(0);

  const firstName = (studentName || "").split(" ")[0];
  const greet = firstName ? `Hi, ${firstName}!` : "Hi there!";

  const lines =
    completion >= 100
      ? [`${greet} 🎉`, "All documents in — nice work!", "Sit tight, we're reviewing."]
      : completion >= 50
      ? [`${greet} 👋`, "You're more than halfway there.", "Tap a missing doc to upload."]
      : [`${greet} 👋`, "Let's get your TES set up.", "Start with your Student ID."];

  useEffect(() => {
    const t = setInterval(() => setBubbleIdx((i) => (i + 1) % lines.length), 5200);
    return () => clearInterval(t);
  }, [lines.length]);

  function poke() {
    setBubbleIdx((i) => (i + 1) % lines.length);
  }

  return (
    <div className="relative flex items-center gap-3 select-none">
      {/* Speech bubble — arrow on the right edge, pointing at the mascot */}
      <div className="relative flex-1 min-w-0">
        <AnimatePresence mode="wait">
          <motion.div
            key={bubbleIdx}
            initial={{ opacity: 0, y: 6, scale: 0.96 }}
            animate={{ opacity: 1, y: 0, scale: 1 }}
            exit={{ opacity: 0, y: -4, scale: 0.98 }}
            transition={{ duration: 0.22, ease: "easeOut" }}
            className="relative rounded-2xl bg-surface border px-3.5 py-2.5 shadow-sm"
          >
            <p className="text-sm leading-snug">{lines[bubbleIdx]}</p>
            <span
              aria-hidden
              className="absolute top-1/2 -right-1.5 h-3 w-3 -translate-y-1/2 rotate-45 bg-surface border-t border-r"
            />
          </motion.div>
        </AnimatePresence>
      </div>

      <button
        ref={mascotRef}
        type="button"
        onClick={poke}
        aria-label="Say hello to your study buddy"
        className="shrink-0 outline-none focus-visible:ring-2 focus-visible:ring-primary rounded-2xl overflow-hidden"
        style={{ width: 168, height: 168 }}
      >
        {animationData ? (
          <Lottie
            animationData={animationData}
            loop
            autoplay
            style={{ width: "100%", height: "100%" }}
          />
        ) : null}
      </button>
    </div>
  );
}
