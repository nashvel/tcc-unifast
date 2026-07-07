import { createFileRoute } from "@tanstack/react-router";
import { PageHeader } from "@/components/ui/page-header";
import { Btn } from "@/components/ui/btn";
import { FormField, TextInput, TextArea, Selectish } from "@/components/ui/form-field";
import { SearchInput } from "@/components/ui/search-input";
import { DataTable, THead, Tr, Th, Td } from "@/components/ui/data-table";
import { TableStates } from "@/components/ui/table-states";
import { StatusBadge } from "@/components/ui/status-badge";
import { IconDownload, IconPlus, IconTrash } from "@tabler/icons-react";
import type { ReactNode } from "react";

export const Route = createFileRoute("/app/style-guide")({
  component: StyleGuide,
});

function StyleGuide() {
  return (
    <div className="space-y-8">
      <PageHeader
        title="UI Style Guide"
        description="Internal reference for approved tokens, components, and states. Match this — don't invent."
      />

      <Section title="Color tokens" hint="Semantic only. Never use raw Tailwind palette or hex.">
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
          <Swatch name="bg" className="bg-bg border" />
          <Swatch name="surface" className="bg-surface border" />
          <Swatch name="surface-muted" className="bg-surface-muted border" />
          <Swatch name="border-strong" className="bg-border-strong" />
          <Swatch name="primary" className="bg-primary" />
          <Swatch name="primary-soft" className="bg-primary-soft" />
          <Swatch name="success" className="bg-success" />
          <Swatch name="success-soft" className="bg-success-soft" />
          <Swatch name="warning" className="bg-warning" />
          <Swatch name="warning-soft" className="bg-warning-soft" />
          <Swatch name="danger" className="bg-danger" />
          <Swatch name="info" className="bg-info" />
        </div>
      </Section>

      <Section title="Typography" hint="Absans sans. Every text element must match one role below — see design-tokens.md § Typography.">
        <TypeRoles />
      </Section>


      <Section title="Spacing & radii" hint="4px scale. Prefer 2/3/4/6/8. Radius: sm, md (default), lg, full.">
        <div className="flex flex-wrap items-end gap-4">
          {["sm", "md", "lg", "xl"].map((r) => (
            <div key={r} className="flex flex-col items-center gap-1">
              <div className={`h-14 w-14 bg-primary-soft border border-primary/20 rounded-${r}`} />
              <span className="text-xs text-text-muted">rounded-{r}</span>
            </div>
          ))}
          <div className="flex flex-col items-center gap-1">
            <div className="h-14 w-14 bg-primary-soft border border-primary/20 rounded-full" />
            <span className="text-xs text-text-muted">rounded-full</span>
          </div>
        </div>
      </Section>

      <Section title="Shadows" hint="Flat by default. Elevation is signal, not decoration.">
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
          {(["shadow-xs", "shadow-sm", "shadow-md", "shadow-pop"] as const).map((s) => (
            <div key={s} className={`h-20 rounded-md bg-surface border ${s} grid place-items-center`}>
              <span className="text-xs text-text-muted">{s}</span>
            </div>
          ))}
        </div>
      </Section>

      <Section title="Buttons" hint="Sizes sm/md. Icon prop for leading Tabler icons.">
        <div className="flex flex-wrap items-center gap-2">
          <Btn variant="primary" icon={IconPlus}>Primary</Btn>
          <Btn variant="secondary">Secondary</Btn>
          <Btn variant="outline" icon={IconDownload}>Outline</Btn>
          <Btn variant="ghost">Ghost</Btn>
          <Btn variant="danger" icon={IconTrash}>Danger</Btn>
          <Btn variant="primary" disabled>Disabled</Btn>
        </div>
        <div className="flex flex-wrap items-center gap-2 mt-3">
          <Btn size="sm" variant="primary">Small primary</Btn>
          <Btn size="sm" variant="outline">Small outline</Btn>
          <Btn size="sm" variant="ghost">Small ghost</Btn>
        </div>
      </Section>

      <Section title="Badges" hint="Status only. Muted hues — never neon.">
        <div className="flex flex-wrap gap-2">
          <StatusBadge variant="neutral">Neutral</StatusBadge>
          <StatusBadge variant="primary">Primary</StatusBadge>
          <StatusBadge variant="success">Approved</StatusBadge>
          <StatusBadge variant="warning">Pending</StatusBadge>
          <StatusBadge variant="danger">Rejected</StatusBadge>
          <StatusBadge variant="info">Resubmission</StatusBadge>
        </div>
      </Section>

      <Section title="Forms" hint="Label above input. Helper text below in text-soft.">
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 max-w-2xl">
          <FormField label="Full name" required helper="As it appears on official records.">
            <TextInput placeholder="Juan Dela Cruz" />
          </FormField>
          <FormField label="Program">
            <Selectish>
              <option>BS Computer Science</option>
              <option>BS Accountancy</option>
            </Selectish>
          </FormField>
          <FormField label="Search" className="md:col-span-2">
            <SearchInput placeholder="Search by name or student #" />
          </FormField>
          <FormField label="Notes" helper="Optional." className="md:col-span-2">
            <TextArea placeholder="Add a note…" />
          </FormField>
          <FormField label="With error" error="Student number is required.">
            <TextInput defaultValue="" />
          </FormField>
        </div>
      </Section>

      <Section title="Table — with data">
        <SampleTable state="ok" />
      </Section>

      <Section title="Table — loading">
        <SampleTable state="loading" />
      </Section>

      <Section title="Table — empty">
        <SampleTable state="empty" />
      </Section>

      <Section title="Table — error">
        <SampleTable state="error" />
      </Section>
    </div>
  );
}

function Section({ title, hint, children }: { title: string; hint?: string; children: ReactNode }) {
  return (
    <section className="rounded-md border bg-surface p-4 sm:p-5">
      <div className="mb-4">
        <h2 className="text-base font-semibold tracking-tight">{title}</h2>
        {hint && <p className="text-xs text-text-muted mt-0.5">{hint}</p>}
      </div>
      {children}
    </section>
  );
}

function Swatch({ name, className }: { name: string; className: string }) {
  return (
    <div className="flex items-center gap-2">
      <div className={`h-9 w-9 rounded-md ${className}`} />
      <code className="text-xs text-text-muted">{name}</code>
    </div>
  );
}

const rows = [
  { id: "2024-000123", name: "Ana Reyes", program: "BS CS", status: "approved" as const },
  { id: "2024-000124", name: "Miguel Santos", program: "BS Accountancy", status: "pending" as const },
  { id: "2024-000125", name: "Liza Cruz", program: "BS Nursing", status: "rejected" as const },
];

function SampleTable({ state }: { state: "ok" | "loading" | "empty" | "error" }) {
  return (
    <DataTable>
      <THead>
        <Tr><Th>Student #</Th><Th>Name</Th><Th>Program</Th><Th>Status</Th></Tr>
      </THead>
      <tbody>
        <TableStates
          colSpan={4}
          isLoading={state === "loading"}
          isError={state === "error"}
          error={new Error("Network request failed (503).")}
          isEmpty={state === "empty"}
          onRetry={() => {}}
          skeletonRows={4}
          emptyTitle="No grantees match your filters"
          emptyHint="Clear filters or import a masterlist to see records."
        />
        {state === "ok" && rows.map((r) => (
          <Tr key={r.id}>
            <Td className="font-mono text-xs">{r.id}</Td>
            <Td className="font-medium">{r.name}</Td>
            <Td className="text-text-muted">{r.program}</Td>
            <Td>
              <StatusBadge variant={r.status === "approved" ? "success" : r.status === "pending" ? "warning" : "danger"}>
                {r.status}
              </StatusBadge>
            </Td>
          </Tr>
        ))}
      </tbody>
    </DataTable>
  );
}
