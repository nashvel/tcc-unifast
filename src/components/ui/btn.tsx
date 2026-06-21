import { cn } from "@/lib/utils";
import type { ButtonHTMLAttributes, ComponentType } from "react";

type Variant = "primary" | "secondary" | "danger" | "ghost" | "outline";
type Size = "sm" | "md";

interface Props extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: Variant;
  size?: Size;
  icon?: ComponentType<{ size?: number; className?: string }>;
}

const variants: Record<Variant, string> = {
  primary: "bg-primary text-primary-foreground hover:bg-primary-hover border border-transparent",
  secondary: "bg-surface text-text border hover:bg-surface-muted",
  danger: "bg-danger text-white hover:bg-danger/90 border border-transparent",
  ghost: "bg-transparent text-text hover:bg-surface-muted border border-transparent",
  outline: "bg-transparent text-text border hover:bg-surface-muted",
};

const sizes: Record<Size, string> = {
  sm: "h-8 px-2.5 text-xs gap-1.5",
  md: "h-9 px-3 text-sm gap-1.5",
};

export function Btn({ variant = "secondary", size = "md", icon: Icon, className, children, ...props }: Props) {
  return (
    <button
      className={cn(
        "inline-flex items-center justify-center rounded-md font-medium transition-colors focus-ring disabled:opacity-50 disabled:pointer-events-none",
        variants[variant],
        sizes[size],
        className,
      )}
      {...props}
    >
      {Icon && <Icon size={size === "sm" ? 14 : 15} />}
      {children}
    </button>
  );
}
