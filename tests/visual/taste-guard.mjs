#!/usr/bin/env node
/**
 * Taste guard — fails when forbidden "AI slop" patterns appear in source.
 * Runs in CI. No browser needed. Extend FORBIDDEN as the design evolves.
 *
 *   node tests/visual/taste-guard.mjs
 */
import { readdirSync, readFileSync, statSync } from "node:fs";
import { join, extname } from "node:path";

const ROOT = new URL("../../src/", import.meta.url).pathname;

// Files that are allowed to contain otherwise-forbidden strings.
const ALLOWLIST = new Set([
  "styles.css",                    // token definitions live here
  "components/ui/chart.tsx",       // recharts default selectors
  "components/ui/sonner.tsx",      // toast library primitives
  "components/ui/data-table.tsx",  // scroll-fade masks use gradients intentionally
  "routes/app.security.tsx",       // security severity palette is intentional
  "routes/app.security.memory.tsx",// security severity palette is intentional
  "lib/error-page.ts",             // standalone HTML fallback — no runtime tokens
  "components/ui/sidebar.tsx",     // shadcn primitive — uses 0-blur shadow as a border
  "routes/app.reports.preview.tsx",// printable PDF markup — self-contained styles
]);

const FORBIDDEN = [
  {
    id: "gradient",
    re: /linear-gradient\(|bg-gradient-to-|from-\w+-\d+\s+to-\w+-\d+/,
    msg: "Gradients are banned. Use flat surface tokens.",
  },
  {
    id: "neon-hex",
    // Vivid purples / pinks / cyans commonly emitted by AI defaults.
    re: /#(a855f7|8b5cf6|d946ef|ec4899|f472b6|06b6d4|22d3ee|0ea5e9|3b82f6|6366f1)\b/i,
    msg: "Neon/AI-default hex color. Use a semantic token (primary, info, …).",
  },
  {
    id: "raw-tailwind-palette",
    // Named Tailwind palette utilities bypass the token system.
    re: /\b(bg|text|border|ring|from|to|via)-(slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose)-\d{2,3}\b/,
    msg: "Raw Tailwind palette utility. Use semantic tokens (bg-surface, text-text-muted, …).",
  },
  {
    id: "heavy-shadow",
    // Any shadow with a blur radius ≥ 30px reads as glow/float.
    re: /shadow-\[[^\]]*_([3-9]\d|\d{3,})px[^\]]*\]|drop-shadow-\[[^\]]*_([3-9]\d|\d{3,})px/,
    msg: "Shadow blur too large — use shadow-sm / shadow-md / shadow-pop tokens.",
  },
  {
    id: "colored-shadow",
    re: /shadow-(red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose)-\d{2,3}/,
    msg: "Colored shadows read as neon glow. Remove.",
  },
  {
    id: "arbitrary-text-size",
    re: /\btext-\[\d+(px|rem|em)\]/,
    msg: "Arbitrary text size. Use text-2xs / text-micro / text-xs / text-sm / text-base / text-lg / text-xl / text-2xl / text-3xl.",
  },
  {
    id: "arbitrary-radius",
    re: /\brounded-\[\d/,
    msg: "Arbitrary radius. Use rounded-sm / rounded-md / rounded-lg / rounded-xl / rounded-full.",
  },
  {
    id: "arbitrary-shadow",
    re: /\bshadow-\[/,
    msg: "Arbitrary shadow. Use shadow-xs / shadow-sm / shadow-md / shadow-pop.",
  },
  {
    id: "oversized-text",
    // text-4xl through text-9xl exceed the documented scale (max text-3xl).
    re: /\btext-(4xl|5xl|6xl|7xl|8xl|9xl)\b/,
    msg: "Text size exceeds the documented scale (max text-3xl). See design-tokens.md § Typography.",
  },
  {
    id: "heavy-font-weight",
    // Documented weights: 400 / 500 / 600. Anything above breaks the rhythm.
    re: /\bfont-(bold|extrabold|black)\b/,
    msg: "Font weight above 600 is not allowed. Use font-medium (500) or font-semibold (600).",
  },
  {
    id: "unknown-text-size",
    // Catches text-[N] and typo'd sizes like text-md that aren't in the scale.
    re: /\btext-(md|4xs|3xs)\b/,
    msg: "Unknown text size. Use text-2xs / text-micro / text-xs / text-sm / text-base / text-lg / text-xl / text-2xl / text-3xl.",
  },
  {
    id: "uppercase-on-body",
    // Eyebrows / table headers are the only allowed uppercase — always text-2xs or text-micro.
    re: /\buppercase\b[^"'`]*\btext-(sm|base|lg|xl|2xl|3xl)\b|\btext-(sm|base|lg|xl|2xl|3xl)\b[^"'`]*\buppercase\b/,
    msg: "uppercase is reserved for eyebrows / table headers (text-2xs or text-micro only). See design-tokens.md § Typography.",
  },
  {
    id: "mono-too-large",
    // font-mono is for IDs / codes / kbd — never above text-xs.
    re: /\bfont-mono\b[^"'`]*\btext-(sm|base|lg|xl|2xl|3xl)\b|\btext-(sm|base|lg|xl|2xl|3xl)\b[^"'`]*\bfont-mono\b/,
    msg: "font-mono is limited to IDs / codes / kbd (max text-xs). Use font-sans for prose.",
  },
  {
    id: "heading-tag-off-scale",
    // <h1>/<h2>/<h3> must land on a documented heading size on the same line.
    re: /<h[1-3]\b(?![^>]*\btext-(base|lg|xl|2xl|3xl|sm)\b)[^>]*className=/,
    msg: "Heading tag without a documented size. h1 → text-2xl, h2 → text-base, h3 → text-sm (see design-tokens.md).",
  },
];

const violations = [];

function walk(dir) {
  for (const entry of readdirSync(dir)) {
    const full = join(dir, entry);
    const rel = full.slice(ROOT.length);
    if (ALLOWLIST.has(rel)) continue;
    const s = statSync(full);
    if (s.isDirectory()) { walk(full); continue; }
    if (![".ts", ".tsx", ".css"].includes(extname(entry))) continue;
    const text = readFileSync(full, "utf8");
    text.split("\n").forEach((line, i) => {
      for (const rule of FORBIDDEN) {
        if (rule.re.test(line)) {
          violations.push({ file: rel, line: i + 1, rule: rule.id, msg: rule.msg, snippet: line.trim().slice(0, 140) });
        }
      }
    });
  }
}

walk(ROOT);

if (violations.length) {
  console.error(`\n✖ Taste guard: ${violations.length} violation(s)\n`);
  for (const v of violations) {
    console.error(`  ${v.file}:${v.line}  [${v.rule}]  ${v.msg}`);
    console.error(`    ${v.snippet}\n`);
  }
  process.exit(1);
}

console.log("✓ Taste guard passed — no gradients, neon colors, or excessive shadows found.");
