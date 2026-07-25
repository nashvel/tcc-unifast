import { branding } from "@/config/branding";
import { cn } from "@/lib/utils";

const SIZE_PX = { sm: 28, md: 32, lg: 36 } as const;
type Size = keyof typeof SIZE_PX;

/**
 * Standardized brand mark: square 1:1 aspect, `object-contain`, fixed sizes.
 * Use in sidebars and top headers so alignment is identical everywhere.
 */
export function BrandLogo({
  size = "md",
  className,
}: {
  size?: Size;
  className?: string;
}) {
  const px = SIZE_PX[size];
  return (
    <img
      src={branding.systemLogoUrl}
      alt={branding.institution}
      width={px}
      height={px}
      className={cn("shrink-0 aspect-square object-contain select-none", className)}
      draggable={false}
    />
  );
}
