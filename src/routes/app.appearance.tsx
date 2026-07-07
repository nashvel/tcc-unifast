import { createFileRoute } from "@tanstack/react-router";
import { IconCheck, IconMoon, IconSun } from "@tabler/icons-react";
import { PageHeader } from "@/components/ui/page-header";
import { useThemeStore, FONT_OPTIONS, type FontKey } from "@/stores/themeStore";
import { cn } from "@/lib/utils";

export const Route = createFileRoute("/app/appearance")({
  component: AppearancePage,
});

function AppearancePage() {
  const dark = useThemeStore((s) => s.dark);
  const font = useThemeStore((s) => s.font);
  const setDark = useThemeStore((s) => s.set);
  const setFont = useThemeStore((s) => s.setFont);

  return (
    <div className="space-y-6 max-w-3xl">
      <PageHeader
        title="Appearance"
        description="Choose how the UniFAST TES workspace looks on this device. Preferences are saved locally."
      />

      <section className="rounded-md border bg-surface p-4 sm:p-5">
        <h2 className="text-base font-semibold tracking-tight">Theme</h2>
        <p className="text-xs text-text-muted mt-0.5">Government Slate — light or dark.</p>

        <div className="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
          <ThemeCard
            active={!dark}
            onClick={() => setDark(false)}
            label="Light"
            icon={<IconSun size={16} />}
            swatch={["#f4f6f8", "#ffffff", "#0f4c5c", "#14202b"]}
          />
          <ThemeCard
            active={dark}
            onClick={() => setDark(true)}
            label="Dark"
            icon={<IconMoon size={16} />}
            swatch={["#0e1418", "#141b21", "#6fb5c4", "#e6ecf0"]}
          />
        </div>
      </section>

      <section className="rounded-md border bg-surface p-4 sm:p-5">
        <h2 className="text-base font-semibold tracking-tight">Font</h2>
        <p className="text-xs text-text-muted mt-0.5">Applies to the entire workspace. Non-default fonts load on demand.</p>

        <div className="mt-4 divide-y">
          {FONT_OPTIONS.map((f) => (
            <FontRow key={f.key} option={f} active={font === f.key} onSelect={() => setFont(f.key)} />
          ))}
        </div>
      </section>
    </div>
  );
}

function ThemeCard({
  active, onClick, label, icon, swatch,
}: {
  active: boolean;
  onClick: () => void;
  label: string;
  icon: React.ReactNode;
  swatch: string[];
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      aria-pressed={active}
      className={cn(
        "text-left rounded-md border p-3 focus-ring transition-colors",
        active ? "border-primary bg-primary-soft" : "hover:bg-surface-muted",
      )}
    >
      <div className="flex items-center justify-between">
        <span className="inline-flex items-center gap-2 text-sm font-medium">
          {icon} {label}
        </span>
        {active && <IconCheck size={16} className="text-primary" />}
      </div>
      <div className="mt-3 flex overflow-hidden rounded border h-8">
        {swatch.map((c) => (
          <div key={c} className="flex-1" style={{ backgroundColor: c }} />
        ))}
      </div>
    </button>
  );
}

function FontRow({
  option, active, onSelect,
}: {
  option: (typeof FONT_OPTIONS)[number];
  active: boolean;
  onSelect: () => void;
}) {
  return (
    <button
      type="button"
      onClick={onSelect}
      aria-pressed={active}
      className={cn(
        "w-full text-left py-3 first:pt-0 last:pb-0 grid grid-cols-[1fr_auto] items-center gap-3 focus-ring rounded",
      )}
    >
      <div>
        <div className="flex items-center gap-2">
          <span className="text-sm font-medium">{option.label}</span>
          {active && <IconCheck size={14} className="text-primary" />}
        </div>
        <p className="text-xs text-text-muted mt-0.5">{option.description}</p>
        <p className="mt-1.5 text-base" style={{ fontFamily: option.stack }}>
          The quick brown fox jumps over the lazy dog · 0123456789
        </p>
      </div>
      <span className={cn("text-2xs uppercase tracking-wide font-medium", active ? "text-primary" : "text-text-soft")}>
        {active ? "Active" : "Select"}
      </span>
    </button>
  );
}

export type { FontKey };
