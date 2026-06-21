import { cn } from "@/lib/utils";

/** DiceBear "adventurer" default avatar — seeded by name/email so it's stable per user. */
export function DiceBearAvatar({
  seed,
  size = 28,
  className,
  alt = "",
}: {
  seed: string;
  size?: number;
  className?: string;
  alt?: string;
}) {
  const safeSeed = encodeURIComponent((seed || "anonymous").trim().toLowerCase());
  const src = `https://api.dicebear.com/9.x/adventurer/svg?seed=${safeSeed}&backgroundType=gradientLinear&radius=50`;
  return (
    <img
      src={src}
      alt={alt}
      width={size}
      height={size}
      loading="lazy"
      className={cn("rounded-full bg-surface-2 object-cover shrink-0", className)}
      style={{ width: size, height: size }}
    />
  );
}
