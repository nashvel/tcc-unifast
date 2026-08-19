# UniFAST Flare Theme — Component Class Reference

## Buttons

### Primary button
```html
<button class="inline-flex items-center justify-center px-6 py-3 rounded-xl font-bold text-base
               bg-primary text-base hover:bg-primary-hover
               shadow-primary transition-all duration-200 active:scale-95">
  Submit
</button>
```

### Secondary button (outlined)
```html
<button class="inline-flex items-center justify-center px-6 py-3 rounded-xl font-bold text-base
               bg-transparent border-2 border-primary text-primary
               hover:bg-wash transition-all duration-200 active:scale-95">
  Cancel
</button>
```

### Ghost button
```html
<button class="inline-flex items-center justify-center px-4 py-3 rounded-xl font-bold text-base
               bg-transparent text-primary hover:bg-wash
               transition-all duration-200 active:scale-95">
  View
</button>
```

---

## Cards

### Standard card
```html
<div class="bg-base p-8 rounded-3xl shadow-card border border-ink/5">
  content here
</div>
```

### Wash card (orange tinted background)
```html
<div class="bg-wash p-8 rounded-3xl border border-primary/10">
  content here
</div>
```

---

## Typography

### Page heading (H1)
```html
<h1 class="font-display font-bold text-5xl tracking-tight text-ink">
  Heading
</h1>
```

### Section heading (H2)
```html
<h2 class="font-display font-bold text-3xl tracking-tight text-ink">
  Section Title
</h2>
```

### Body text
```html
<p class="font-sans text-base text-ink/70 leading-relaxed">
  Body copy here.
</p>
```

### Muted text
```html
<p class="font-sans text-sm text-ink/50">
  Secondary text
</p>
```

---

## Badges

### Primary badge
```html
<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold
             bg-primary/10 text-primary">
  Active
</span>
```

### Neutral badge
```html
<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold
             bg-ink/5 text-ink/60">
  Inactive
</span>
```

### Success badge
```html
<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold
             bg-green-50 text-green-700">
  Verified
</span>
```

### Danger badge
```html
<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold
             bg-red-50 text-red-600">
  Rejected
</span>
```

---

## Form inputs

### Text input
```html
<input
  type="text"
  class="w-full px-4 py-3 rounded-xl border border-ink/10 bg-base
         font-sans text-ink placeholder:text-ink/30
         focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary
         transition-all duration-200"
  placeholder="Enter value"
/>
```

### Input error state
```html
<input
  type="text"
  class="w-full px-4 py-3 rounded-xl border border-red-400 bg-base
         font-sans text-ink
         focus:outline-none focus:ring-2 focus:ring-red-200
         transition-all duration-200"
/>
<p class="mt-1 text-sm text-red-500">This field is required</p>
```

### Select dropdown
```html
<select class="w-full px-4 py-3 rounded-xl border border-ink/10 bg-base
               font-sans text-ink
               focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary
               transition-all duration-200">
  <option>Choose option</option>
</select>
```

### Label
```html
<label class="block text-sm font-bold text-ink mb-1">
  Field Label <span class="text-primary">*</span>
</label>
```

---

## Tables

### Table wrapper
```html
<div class="rounded-2xl border border-ink/5 overflow-hidden shadow-sm">
  <table class="w-full text-sm font-sans">
    <thead class="bg-wash">
      <tr>
        <th class="px-6 py-4 text-left font-bold text-ink/60 uppercase tracking-wide text-xs">
          Column
        </th>
      </tr>
    </thead>
    <tbody class="divide-y divide-ink/5 bg-base">
      <tr class="hover:bg-wash/50 transition-colors duration-150">
        <td class="px-6 py-4 text-ink">
          Value
        </td>
      </tr>
    </tbody>
  </table>
</div>
```

---

## Sections

### Full page section — porcelain background
```html
<section class="bg-base py-20">
  <div class="container-custom">
    content here
  </div>
</section>
```

### Wash section — orange tinted background
```html
<section class="bg-wash py-20">
  <div class="container-custom">
    content here
  </div>
</section>
```

---

## Navigation / Sidebar

### Sidebar item — default
```html
<a class="flex items-center gap-3 px-4 py-3 rounded-xl font-sans font-medium text-ink/60
          hover:bg-wash hover:text-ink transition-all duration-150 cursor-pointer">
  icon + label
</a>
```

### Sidebar item — active
```html
<a class="flex items-center gap-3 px-4 py-3 rounded-xl font-sans font-medium
          bg-primary/10 text-primary cursor-pointer">
  icon + label
</a>
```

---

## Modals

### Modal overlay
```html
<div class="fixed inset-0 bg-ink/20 backdrop-blur-sm z-50 flex items-center justify-center p-4">
  <div class="bg-base rounded-3xl shadow-xl shadow-ink/10 w-full max-w-lg p-8 border border-ink/5">
    <!-- Modal content -->
  </div>
</div>
```

### Modal header
```html
<div class="flex items-center justify-between mb-6">
  <h3 class="font-display font-bold text-2xl tracking-tight text-ink">
    Modal Title
  </h3>
  <button class="p-2 rounded-lg hover:bg-wash text-ink/40 hover:text-ink transition-colors">
    <!-- X icon -->
  </button>
</div>
```

---

## Animations

### Slide up on mount
```html
<div class="animate-slide-up">
  content
</div>
```

### Staggered slide up (use with delay style)
```html
<div class="animate-slide-up" style="animation-delay: 100ms">first</div>
<div class="animate-slide-up" style="animation-delay: 200ms">second</div>
<div class="animate-slide-up" style="animation-delay: 300ms">third</div>
```

---

## index.html font import

Add these lines inside your <head> tag:

```html
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link
  rel="stylesheet"
  href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,400;0,14..32,500;0,14..32,600&family=Space+Grotesk:wght@700&display=swap"
/>
```
