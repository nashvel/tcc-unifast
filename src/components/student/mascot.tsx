import { useEffect, useState } from "react";
import { AnimatePresence, motion } from "framer-motion";

interface Props {
  /** % of required documents submitted (0–100). */
  completion: number;
  studentName?: string;
}

/**
 * Friendly study-buddy mascot for the student dashboard.
 * Pure-CSS cartoon character (woman at a laptop).
 * - Eyes animate side-to-side on hover / tap.
 * - Blinks every ~4s and on tap.
 * - Tap cycles encouraging messages contextual to progress.
 */
export function DashboardMascot({ completion, studentName }: Props) {
  const [bubbleIdx, setBubbleIdx] = useState(0);
  const [looking, setLooking] = useState(false);
  const [blink, setBlink] = useState(false);

  const firstName = (studentName || "").split(" ")[0];
  const greet = firstName ? `Hi, ${firstName}!` : "Hi there!";

  const lines =
    completion >= 100
      ? [`${greet} 🎉`, "All documents in — nice work!", "Sit tight, we're reviewing."]
      : completion >= 50
      ? [`${greet} 👋`, "You're more than halfway there.", "Tap a missing doc to upload."]
      : [`${greet} 👋`, "Let's get your TES set up.", "Start with your Student ID."];

  useEffect(() => {
    const t = setInterval(() => {
      setBlink(true);
      setTimeout(() => setBlink(false), 150);
    }, 4200);
    return () => clearInterval(t);
  }, []);

  useEffect(() => {
    const t = setInterval(() => setBubbleIdx((i) => (i + 1) % lines.length), 5200);
    return () => clearInterval(t);
  }, [lines.length]);

  function poke() {
    setLooking(true);
    setBlink(true);
    setBubbleIdx((i) => (i + 1) % lines.length);
    setTimeout(() => setBlink(false), 160);
    setTimeout(() => setLooking(false), 1600);
  }

  return (
    <div className="relative flex items-end gap-3 select-none">
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

      {/* Cartoon */}
      <button
        type="button"
        onClick={poke}
        onMouseEnter={() => setLooking(true)}
        onMouseLeave={() => setLooking(false)}
        aria-label="Say hello to your study buddy"
        className="shrink-0 outline-none focus-visible:ring-2 focus-visible:ring-primary rounded-2xl overflow-hidden"
        style={{ width: 168, height: 168 }}
      >
        <div className={`cartoon-stage ${looking ? "is-looking" : ""} ${blink ? "is-blinking" : ""}`}>
          <div className="cartoon">
            <div className="hair-back hb ha" />
            <div className="ear b" />
            <div className="ear b" />
            <div className="earring r b" />
            <div className="earring r b" />
            <div className="neck" />
            <div className="face b">
              <div className="eyebrow" />
              <div className="eyebrow" style={{ left: "auto", right: "20%", transform: "rotate(10deg)" }} />
              <div className="eye b">
                <div className="pupil r hb" />
              </div>
              <div className="eye b">
                <div className="pupil r hb" />
              </div>
              <div className="cheek" />
              <div className="cheek" />
              <div className="nose" />
              <div className="mouth" />
            </div>
            <div className="bangs-1" />
            <div className="bangs-2" />
            <div className="body">
              <div className="table" />
              <div className="computer ha" />
              <div className="coffee" />
            </div>
          </div>
        </div>
      </button>

      <style>{cartoonCss}</style>
    </div>
  );
}

/* -------------------------------------------------------------------------- */
/* Scoped cartoon CSS. All `vmin` units rewritten as `em`; the .cartoon       */
/* container sets `font-size: calc(var(--size) / 80)`, so 80em == --size.     */
/* -------------------------------------------------------------------------- */
const cartoonCss = `
.cartoon-stage {
  --size: 168px;
  position: relative;
  width: 100%;
  height: 100%;
  overflow: hidden;
  background: #fff7e6;
  font-size: calc(var(--size) / 80);
}
.cartoon-stage.is-looking .pupil { animation: eyemove 0.9s ease-in-out alternate infinite; }
.cartoon-stage.is-blinking .eye { height: 0.4em !important; }

.cartoon { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); }
.cartoon div { position: absolute; box-sizing: border-box; }
.b { border: 0.75em solid black; }
.r { border-radius: 100%; }
.hb::before, .ha::after { content: ""; display: block; position: absolute; }

.cartoon {
  --skin: #fca;
  --line: #963;
  --shadow: rgba(80,0,0,0.075);
  --hair: #630;
  --shirt: #887389;
  width: 80em;
  height: 80em;
}

.hair-back {
  width: 60%; height: 50%;
  background: var(--hair);
  border-radius: 40% 40% 0% 0%;
  transform: translate(-50%, 0);
  left: 50%; top: 5%;
  clip-path: polygon(-50% 0%, 150% 0%, 150% 100%, 100% 100%, 98% 90%, 99.5% 100%, 98% 100%, 97.25% 96%, 97.5% 99.75%, 75% 99%, 74.5% 98%, 74% 99%, 50% 98%, 10% 99.5%, 9.75% 96%, 9.5% 99.5%, -50% 100%);
  box-shadow: inset 0 0 0 100in rgba(0,0,0,0.2);
}
.hair-back::before {
  width: 15%; height: 100%; border-radius: 50%;
  box-shadow: 0 0 0 100in rgba(0,0,0,0.2), 5em 0 0 5em var(--hair);
  left: -15%; top: 5%;
  clip-path: polygon(50% 50%, 150% 50%, 150% 100%, 50% 100%);
}
.hair-back::after {
  width: 15%; height: 100%; border-radius: 50%;
  box-shadow: 0 0 0 100in rgba(0,0,0,0.2), -5em 0 0 5em var(--hair);
  right: -15%; top: 0%;
  clip-path: polygon(-50% 50%, 50% 50%, 50% 100%, -50% 100%);
}

.face {
  width: 50%; height: 40%;
  background: var(--skin);
  border-radius: 60% 60% 100% 100% / 100% 100% 60% 60%;
  transform: translate(-50%, 0);
  left: 50%; top: 10%;
}

.nose {
  width: 10%; height: 12%;
  border-color: var(--line);
  border-left: 0.25em solid transparent;
  top: 60%; left: 50%;
  transform: translate(-50%, 0) rotate(-35deg);
}

.mouth {
  width: 20%; height: 20%;
  border-color: transparent;
  border-bottom: 0.75em solid var(--line);
  border-right: 0.25em solid transparent;
  transform: translate(-50%, 0) rotate(30deg);
  top: 63%; left: 45%;
}

.eye {
  width: 20%; height: 30%;
  background: white;
  border-radius: 100% 60% 10% 20% / 100% 60% 100% 40%;
  top: 30%; left: 22%;
  overflow: hidden;
  box-shadow: 0 -0.75em var(--shadow);
  transition: height 0.08s ease;
}
@keyframes eyemove {
  from { transform: translate(15%) }
  to   { transform: translate(-15%) }
}
.pupil {
  width: 5em; height: 5em;
  background: #333;
  bottom: -0.5em; right: 1em;
}
.eye + .eye {
  left: auto; right: 22%;
  border-radius: 60% 100% 20% 10% / 60% 100% 40% 100%;
}
.eye + .eye .pupil { left: 1em; right: auto; }

.cheek {
  width: 30%; height: 10%;
  background: rgba(255,0,0,0.1);
  filter: blur(5px);
  top: 60%; left: 15%;
}
.cheek + .cheek { left: auto; right: 15%; }

.ear {
  width: 12%; height: 13%;
  background: var(--skin);
  top: 25%; left: 18%;
  box-shadow: inset -19.75em 0 0 -15.5em var(--shadow);
}
.ear::after {
  width: 15%; height: 17%;
  border-radius: 50%;
  border: 0.5em solid var(--line);
  border-right: 0.25em solid transparent;
  top: 50%; left: 50%;
  transform: translate(-40%, 0) rotate(-10deg);
}
.ear + .ear {
  left: auto; right: 18%;
  box-shadow: inset 19.75em 0 0 -15.5em var(--shadow);
}
.ear + .ear::after {
  left: auto; right: 50%;
  border: 0.5em solid var(--line);
  border-left: 0.25em solid transparent;
  transform: translate(40%, 0) rotate(10deg);
}

.neck {
  width: 12%; height: 20%;
  background: var(--skin);
  top: 45%; left: 50%;
  transform: translate(-50%, 0);
  border-radius: 20% 20% 0 0;
  box-shadow: inset 0 8.75em 0 -4em var(--shadow);
}

.pupil::before {
  width: 1em; height: 1em;
  background: white;
  border-radius: 50%;
  top: 0.5em; left: 0.5em;
}

.bangs-1 {
  width: 24%; height: 18%;
  border-radius: 80% 0 80% 0;
  background: var(--hair);
  top: 5%; left: 15%;
}
.bangs-1::after {
  width: 130%; height: 120%;
  right: 5%;
  border-radius: 50%;
  top: -20%;
  box-shadow: 2.5em -0.3em var(--hair);
  clip-path: polygon(0% 50%, 150% 50%, 150% 150%, 0% 150%);
}
.bangs-2 {
  width: 45%; height: 30%;
  border-radius: 0 100% 0 90%;
  background: var(--hair);
  top: 5%; left: 35%;
  transform: rotate(-20deg);
  transform-origin: top left;
  clip-path: polygon(0% 0%, 100% 0%, 100% 100%, 90% 90%, 98% 100%, 0% 100%);
}

.eyebrow {
  width: 20%; height: 4%;
  box-shadow: 0 -0.75em 0 0.5em rgba(0,0,0,0.2), 0 -0.75em 0 0.5em var(--hair);
  top: 23%; left: 20%;
  transform: rotate(-10deg);
}

.body {
  width: 60%; height: 90%;
  background: var(--skin);
  left: 50%;
  transform: translate(-50%, 0);
  top: 55%;
  clip-path: polygon(0% 0%, 100% 0%, 100% 50%, 0% 50%);
}
.body::after {
  width: 100.25%; height: 100.25%;
  top: -0.125%; left: -0.125%;
  background: var(--shirt);
  border-radius: 50%;
  clip-path: polygon(0% 0%, 35% 0%, 50% 10%, 65% 0%, 100% 0%, 100% 100%, 0% 100%);
}

.table {
  bottom: -5%; left: -12%;
  width: 124%; height: 5%;
  background: #966f33;
}
.computer {
  width: 65%; height: 38%;
  background: linear-gradient(#ccc, #bbb);
  bottom: 0; left: 50%;
  transform: translate(-50%, 0);
  clip-path: polygon(0% 0%, 100% 0%, 99% 100%, 1% 100%);
  border-radius: 5%;
  box-shadow: inset 0 0.25em #fff8;
}
.computer::after {
  width: 6em; height: 5.5em;
  border-radius: 50%;
  background: #fff6;
  top: 55%; left: 50%;
  transform: translate(-50%, -50%);
}
.coffee {
  width: 17%; height: 25%;
  background: linear-gradient(#eee, #ddd);
  bottom: 0; right: -10%;
  box-shadow: inset 0 2em;
  clip-path: polygon(0% 10%, 5% 0%, 95% 0%, 100% 10%, 95% 10%, 90% 100%, 10% 100%, 5% 10%, 0% 10%);
}

.earring {
  border-color: gold;
  width: 3em; height: 4em;
  top: 37%; left: 23%;
  border-top: 0;
}
.earring + .earring { left: auto; right: 23%; }
`;
