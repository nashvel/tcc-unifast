import { useEffect, useRef, useState } from "react";
import { motion, AnimatePresence } from "framer-motion";

interface Props {
  /** % of required documents submitted (0–100). */
  completion: number;
  studentName?: string;
}

/**
 * Friendly study-buddy mascot for the student dashboard.
 * - Eyes track the cursor / touch position.
 * - Blinks every ~4s and on tap.
 * - Tap or hover cycles encouraging messages contextual to progress.
 */
export function DashboardMascot({ completion, studentName }: Props) {
  const wrapRef = useRef<HTMLDivElement>(null);
  const [pupil, setPupil] = useState({ x: 0, y: 0 });
  const [blink, setBlink] = useState(false);
  const [bubbleIdx, setBubbleIdx] = useState(0);
  const [waving, setWaving] = useState(false);

  const firstName = (studentName || "").split(" ")[0];
  const greet = firstName ? `Hi, ${firstName}!` : "Hi there!";

  const lines =
    completion >= 100
      ? [
          `${greet} 🎉`,
          "All documents in — nice work!",
          "Sit tight, we're reviewing.",
        ]
      : completion >= 50
      ? [
          `${greet} 👋`,
          "You're more than halfway there.",
          "Tap a missing doc to upload it.",
        ]
      : [
          `${greet} 👋`,
          "Let's get your TES set up.",
          "Start with your Student ID.",
        ];

  // Eye tracking
  useEffect(() => {
    function move(e: PointerEvent) {
      const el = wrapRef.current;
      if (!el) return;
      const r = el.getBoundingClientRect();
      const cx = r.left + r.width / 2;
      const cy = r.top + r.height / 2;
      const dx = e.clientX - cx;
      const dy = e.clientY - cy;
      const len = Math.hypot(dx, dy) || 1;
      const max = 3; // pixel travel inside eye
      setPupil({
        x: (dx / len) * Math.min(max, len / 40),
        y: (dy / len) * Math.min(max, len / 40),
      });
    }
    window.addEventListener("pointermove", move);
    return () => window.removeEventListener("pointermove", move);
  }, []);

  // Idle blink
  useEffect(() => {
    const t = setInterval(() => {
      setBlink(true);
      setTimeout(() => setBlink(false), 140);
    }, 4200);
    return () => clearInterval(t);
  }, []);

  // Auto-cycle bubble
  useEffect(() => {
    const t = setInterval(() => setBubbleIdx((i) => (i + 1) % lines.length), 5000);
    return () => clearInterval(t);
  }, [lines.length]);

  function poke() {
    setBlink(true);
    setWaving(true);
    setBubbleIdx((i) => (i + 1) % lines.length);
    setTimeout(() => setBlink(false), 160);
    setTimeout(() => setWaving(false), 900);
  }

  return (
    <div
      ref={wrapRef}
      className="relative flex items-end gap-3 select-none"
    >
      {/* Speech bubble */}
      <div className="relative flex-1 min-w-0 pb-2">
        <AnimatePresence mode="wait">
          <motion.div
            key={bubbleIdx}
            initial={{ opacity: 0, y: 6, scale: 0.96 }}
            animate={{ opacity: 1, y: 0, scale: 1 }}
            exit={{ opacity: 0, y: -4, scale: 0.98 }}
            transition={{ duration: 0.22, ease: "easeOut" }}
            className="relative rounded-2xl rounded-br-sm bg-surface border px-3.5 py-2.5 shadow-sm"
          >
            <p className="text-sm leading-snug">{lines[bubbleIdx]}</p>
            <span
              aria-hidden
              className="absolute -bottom-1.5 right-4 h-3 w-3 rotate-45 bg-surface border-b border-r"
            />
          </motion.div>
        </AnimatePresence>
      </div>

      {/* Mascot */}
      <button
        onClick={poke}
        aria-label="Say hello to your study buddy"
        className="shrink-0 outline-none focus-visible:ring-2 focus-visible:ring-primary rounded-full"
      >
        <motion.div
          whileTap={{ scale: 0.92 }}
          animate={{ y: [0, -3, 0] }}
          transition={{ duration: 3.2, repeat: Infinity, ease: "easeInOut" }}
          className="relative"
        >
          <svg width="84" height="84" viewBox="0 0 100 100" aria-hidden>
            {/* Shadow */}
            <ellipse cx="50" cy="92" rx="22" ry="3" fill="rgb(0 0 0 / 0.12)" />
            {/* Body */}
            <circle
              cx="50"
              cy="52"
              r="34"
              fill="var(--primary)"
              stroke="var(--primary-hover)"
              strokeWidth="2"
            />
            {/* Belly */}
            <ellipse cx="50" cy="62" rx="20" ry="16" fill="var(--primary-soft)" />
            {/* Ears */}
            <path
              d="M22 28 L30 14 L36 30 Z"
              fill="var(--primary)"
              stroke="var(--primary-hover)"
              strokeWidth="2"
              strokeLinejoin="round"
            />
            <path
              d="M78 28 L70 14 L64 30 Z"
              fill="var(--primary)"
              stroke="var(--primary-hover)"
              strokeWidth="2"
              strokeLinejoin="round"
            />
            {/* Eyes */}
            <g>
              {/* Left eye white */}
              <circle cx="40" cy="48" r="7" fill="white" />
              <circle cx="60" cy="48" r="7" fill="white" />
              {/* Pupils */}
              <circle cx={40 + pupil.x} cy={48 + pupil.y} r="3" fill="#0f172a" />
              <circle cx={60 + pupil.x} cy={48 + pupil.y} r="3" fill="#0f172a" />
              {/* Sparkle */}
              <circle cx={41 + pupil.x} cy={47 + pupil.y} r="0.9" fill="white" />
              <circle cx={61 + pupil.x} cy={47 + pupil.y} r="0.9" fill="white" />
              {/* Eyelids (blink) */}
              <motion.rect
                x="33"
                y="41"
                width="14"
                height="14"
                fill="var(--primary)"
                animate={{ scaleY: blink ? 1 : 0 }}
                style={{ transformOrigin: "40px 48px" }}
                transition={{ duration: 0.08 }}
              />
              <motion.rect
                x="53"
                y="41"
                width="14"
                height="14"
                fill="var(--primary)"
                animate={{ scaleY: blink ? 1 : 0 }}
                style={{ transformOrigin: "60px 48px" }}
                transition={{ duration: 0.08 }}
              />
            </g>
            {/* Cheeks */}
            <circle cx="32" cy="58" r="3" fill="#fb7185" opacity="0.55" />
            <circle cx="68" cy="58" r="3" fill="#fb7185" opacity="0.55" />
            {/* Smile */}
            <path
              d="M44 60 Q50 66 56 60"
              stroke="#0f172a"
              strokeWidth="2"
              strokeLinecap="round"
              fill="none"
            />
            {/* Waving paw */}
            <motion.g
              style={{ transformOrigin: "82px 60px" }}
              animate={
                waving
                  ? { rotate: [0, -25, 18, -18, 0] }
                  : { rotate: [0, -6, 0] }
              }
              transition={{
                duration: waving ? 0.9 : 3.4,
                repeat: waving ? 0 : Infinity,
                ease: "easeInOut",
              }}
            >
              <circle
                cx="82"
                cy="58"
                r="8"
                fill="var(--primary)"
                stroke="var(--primary-hover)"
                strokeWidth="2"
              />
            </motion.g>
          </svg>
        </motion.div>
      </button>
    </div>
  );
}
