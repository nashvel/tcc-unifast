import { cn } from "@/lib/utils";
import type { ReactNode } from "react";
import { HelpButton } from "@/components/tour/help-button";

interface Props {
  title: string;
  description?: ReactNode;
  actions?: ReactNode;
  className?: string;
  /** Hide the auto tour help button on this page (default: shown when a tour exists). */
  hideTour?: boolean;
}

export function PageHeader({ title, description, actions, className, hideTour }: Props) {
  return (
    <div data-tour="page-header" className={cn("flex flex-wrap items-start justify-between gap-3 mb-4", className)}>
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">{title}</h1>
        {description && <p className="text-xs text-text-muted mt-1">{description}</p>}
      </div>
      {(actions || !hideTour) && (
        <div className="flex items-center gap-2">
          {!hideTour && <HelpButton />}
          {actions}
        </div>
      )}
    </div>
  );
}
