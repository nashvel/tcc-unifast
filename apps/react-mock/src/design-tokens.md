# Design Tokens

Single source of truth: `src/styles.css` (`:root` + `.dark` + `@theme inline`).
Never hardcode colors, shadows, or font sizes in components — always reference a token.

## Colors (Tailwind utilities)
- Surface: `bg-bg` `bg-surface` `bg-surface-muted`
- Text: `text-text` `text-text-muted` `text-text-soft`
- Border: `border-border` `border-border-strong`
- Brand: `bg-primary` `text-primary` `bg-primary-soft` `text-primary-foreground`
- Status: `success` `warning` `danger` `info` (each with `-soft` variant)

Do NOT use raw Tailwind palette (`bg-blue-500`, `text-gray-700`, `#hex`). Add a token instead.

## Radii
`rounded-sm` `rounded-md` (default) `rounded-lg` `rounded-xl` `rounded-full`

## Shadows
`shadow-xs` — hairline separators / raised inputs
`shadow-sm` — cards at rest
`shadow-md` — dropdowns, tooltips
`shadow-pop` — popovers, floating bars, command palette

No neon glows, no colored shadows, no `translate-y` hover lifts on cards.

## Typography

**Family** `font-sans` (Absans) for everything · `font-mono` for IDs, codes, timestamps in tables.
**Weights** 400 body · 500 emphasized / labels · 600 headings. Never 700+.
**Tracking** `tracking-tight` on headings ≥ `text-xl`; default elsewhere. `uppercase tracking-wide` only for micro eyebrows / table headers.

### Scale (usage is fixed — don't improvise)

| Role                        | Class                                  | Notes                                       |
| --------------------------- | -------------------------------------- | ------------------------------------------- |
| Page title (h1)             | `text-2xl font-semibold tracking-tight`| One per route, via `<PageHeader>`.          |
| Section title (h2)          | `text-base font-semibold tracking-tight`| Card / panel heading.                      |
| Subsection (h3)             | `text-sm font-semibold`                | Grouping inside a card.                     |
| Body — default              | `text-sm`                              | Table cells, forms, paragraphs.             |
| Body — reading              | `text-base`                            | Long-form only (announcements, docs).       |
| Muted meta                  | `text-xs text-text-muted`              | Helper text, descriptions, secondary info.  |
| Micro caption / timestamp   | `text-micro text-text-soft`            | Timestamps, footnotes.                      |
| Eyebrow / label chip        | `text-2xs uppercase tracking-wide text-text-muted font-medium` | Micro labels above values. |
| Form label                  | `text-xs font-medium text-text`        | Always above the input, via `<FormField>`.  |
| Table header (`<Th>`)       | `text-2xs uppercase tracking-wide text-text-muted font-medium` | Handled by `<THead>`.  |
| Table cell (`<Td>`)         | `text-sm`                              | Inherited from `<DataTable>`.               |
| ID / code / monospace       | `font-mono text-xs`                    | Student #, request IDs, IPs.                |
| Kbd hint                    | `font-mono text-2xs`                   | Keyboard shortcut chips.                    |

### Do / don't

- ✅ Use tokens above verbatim. Pick the closest role — don't invent a mix.
- ❌ Never `text-[Npx]`, `text-lg`+`font-bold`, or `uppercase` on body copy.
- ❌ Never stack `text-2xl` inside a card — that's a page-title move.
- ❌ Never use `font-mono` for anything except IDs, codes, IPs, or keys.

## Spacing
Tailwind's default 4px scale. Prefer `gap-*` / `p-*` in multiples of 2, 3, 4, 6, 8.

## Motion
`animate-fade-in` `animate-scale-in` `animate-slide-up`, plus `.stagger-children`.
Respect `prefers-reduced-motion` (already global).
