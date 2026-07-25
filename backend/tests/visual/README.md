# Visual regression tests

Two layers guard against "AI slop" style drift.

## 1. Taste guard (automated, fast)

Grep-based check. Fails CI when source reintroduces:

- gradients (`linear-gradient`, `bg-gradient-to-*`, `from-* to-*`)
- neon / AI-default hex colors (`#a855f7`, `#ec4899`, `#3b82f6`, …)
- raw Tailwind palette utilities (`bg-blue-500`, `text-gray-700`, …)
- heavy shadows (blur ≥ 30px, colored shadows)

Run:

```bash
bun run test:taste
```

Allowlist lives in `taste-guard.mjs` (`ALLOWLIST` set + `FORBIDDEN` rules).
Extend as the design system evolves.

## 2. Screenshot baselines (manual review)

Playwright captures key pages at a fixed viewport. Diffs are reviewed by eye —
we intentionally don't pixel-diff, because font hinting and antialiasing produce
false positives that erode trust.

```bash
bun run dev                              # in another shell
bun run test:visual                      # writes tests/visual/current/
open tests/visual/current tests/visual/baseline  # review side-by-side
bun run test:visual -- --update          # promote current → baseline
```

Add new pages/states in `snapshot.mjs` → `PAGES`.
