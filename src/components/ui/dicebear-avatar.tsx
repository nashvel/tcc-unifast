import { useEffect, useState } from "react";
import { cn } from "@/lib/utils";

/**
 * Mock-only: `path` is treated as a plain URL (data: or http[s]). No backend calls.
 */
export function useSignedAvatarUrl(path: string | null | undefined) {
  const [url, setUrl] = useState<string | null>(null);
  useEffect(() => {
    if (!path) { setUrl(null); return; }
    setUrl(path);
  }, [path]);
  return url;
}

export function UserAvatar({
  seed,
  path,
  size = 28,
  className,
  alt = "",
}: {
  seed: string;
  path?: string | null;
  size?: number;
  className?: string;
  alt?: string;
}) {
  const stored = useSignedAvatarUrl(path);
  const safeSeed = encodeURIComponent((seed || "anonymous").trim().toLowerCase());
  const fallback = `https://api.dicebear.com/9.x/adventurer/svg?seed=${safeSeed}&backgroundType=gradientLinear&radius=50`;
  return (
    <img
      src={stored ?? fallback}
      alt={alt}
      width={size}
      height={size}
      loading="lazy"
      className={cn("rounded-full bg-surface-2 object-cover shrink-0", className)}
      style={{ width: size, height: size }}
    />
  );
}

export function DiceBearAvatar(props: { seed: string; size?: number; className?: string; alt?: string }) {
  return <UserAvatar {...props} />;
}
