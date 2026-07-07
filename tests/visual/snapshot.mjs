#!/usr/bin/env node
/**
 * Visual snapshot capture — Playwright screenshots of key pages and states.
 *
 *   node tests/visual/snapshot.mjs           # writes into tests/visual/current/
 *   node tests/visual/snapshot.mjs --update  # promotes current/ to baseline/
 *
 * Requires the dev server to be running at http://localhost:8080.
 * Review diffs by opening baseline/ and current/ side-by-side.
 */
import { chromium } from "playwright";
import { mkdirSync, cpSync, rmSync, existsSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

const HERE = dirname(fileURLToPath(import.meta.url));
const OUT = join(HERE, "current");
const BASELINE = join(HERE, "baseline");
const BASE_URL = process.env.BASE_URL ?? "http://localhost:8080";

const PAGES = [
  { name: "login",        path: "/login" },
  { name: "style-guide",  path: "/app/style-guide" },
  { name: "grantees",     path: "/app/grantees" },
  { name: "masterlist",   path: "/app/masterlist" },
  { name: "documents",    path: "/app/documents" },
  { name: "audit",        path: "/app/audit" },
];

rmSync(OUT, { recursive: true, force: true });
mkdirSync(OUT, { recursive: true });

const browser = await chromium.launch();
const context = await browser.newContext({ viewport: { width: 1280, height: 1800 } });
const page = await context.newPage();

for (const p of PAGES) {
  try {
    await page.goto(BASE_URL + p.path, { waitUntil: "networkidle", timeout: 15_000 });
    await page.waitForTimeout(400); // let motion settle
    await page.screenshot({ path: join(OUT, `${p.name}.png`) });
    console.log(`✓ ${p.name}`);
  } catch (err) {
    console.error(`✗ ${p.name}: ${err.message}`);
  }
}

await browser.close();

if (process.argv.includes("--update")) {
  rmSync(BASELINE, { recursive: true, force: true });
  cpSync(OUT, BASELINE, { recursive: true });
  console.log(`\nBaseline updated (${BASELINE}).`);
} else if (!existsSync(BASELINE)) {
  console.log(`\nNo baseline yet. Review ${OUT}, then run with --update.`);
} else {
  console.log(`\nCompare ${OUT} vs ${BASELINE}.`);
}
