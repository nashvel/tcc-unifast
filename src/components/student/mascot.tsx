import { useEffect, useState } from "react";
import { AnimatePresence, motion } from "framer-motion";

interface Props {
  /** % of required documents submitted (0–100). */
  completion: number;
  studentName?: string;
}

declare global {
  namespace JSX {
    interface IntrinsicElements {
      "creattie-embed": React.DetailedHTMLProps<
        React.HTMLAttributes<HTMLElement> & {
          src?: string;
          delay?: string | number;
          speed?: string | number;
          frame_rate?: string | number;
          trigger?: string;
        },
        HTMLElement
      >;
    }
  }
}

const CREATTIE_SCRIPT_ID = "creattie-embed-script";
const CREATTIE_SCRIPT_SRC =
  "https://creattie.com/js/embed.js?id=f702d08fa3177bffcb38";

function useCreattieScript() {
  useEffect(() => {
    if (document.getElementById(CREATTIE_SCRIPT_ID)) return;
    const s = document.createElement("script");
    s.id = CREATTIE_SCRIPT_ID;
    s.src = CREATTIE_SCRIPT_SRC;
    s.defer = true;
    document.body.appendChild(s);
  }, []);
}

export function DashboardMascot({ completion, studentName }: Props) {
  useCreattieScript();
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
    <div className="relative flex items-end gap-3 select-none">
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

      <button
        type="button"
        onClick={poke}
        aria-label="Say hello to your study buddy"
        className="shrink-0 outline-none focus-visible:ring-2 focus-visible:ring-primary rounded-2xl overflow-hidden"
        style={{ width: 168, height: 168 }}
      >
        <creattie-embed
          src="https://ik.imagekit.io/creattie/main/saved_colors/144952/EMNZ23FWagNTKia8.json"
          delay="1"
          speed="100"
          frame_rate="24"
          trigger="loop"
          style={{ width: "100%", height: "100%", display: "block" }}
        />
      </button>
    </div>
  );
}
