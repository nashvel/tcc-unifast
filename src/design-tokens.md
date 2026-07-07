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
- Family: `font-sans` (Absans) / `font-mono` for IDs and codes
- Sizes: `text-xs` `text-sm` `text-base` `text-lg` `text-xl` `text-2xl` `text-3xl`
- Tracking: `tracking-tight` for headings, default for body
- Weights: 400 body, 500 emphasized, 600 headings. Skip 700+.

## Spacing
Tailwind's default 4px scale. Prefer `gap-*` / `p-*` in multiples of 2, 3, 4, 6, 8.

## Motion
`animate-fade-in` `animate-scale-in` `animate-slide-up`, plus `.stagger-children`.
Respect `prefers-reduced-motion` (already global).
