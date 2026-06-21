import { useEffect, useState } from "react";
import { cn } from "@/lib/utils";
import { supabase } from "@/integrations/supabase/client";

/** Resolve a storage path in the `avatars` bucket to a signed URL (private bucket). */
export function useSignedAvatarUrl(path: string | null | undefined) {
  const [url, setUrl] = useState<string | null>(null);
  useEffect(() => {
    let alive = true;
    if (!path) { setUrl(null); return; }
    // If already a full URL (legacy/external), pass through.
    if (/^https?:\/\//.test(path)) { setUrl(path); return; }
    supabase.storage.from("avatars").createSignedUrl(path, 60 * 60).then(({ data }) => {
      if (alive) setUrl(data?.signedUrl ?? null);
    });
    return () => { alive = false; };
  }, [path]);
  return url;
}

/** Avatar that shows the user's uploaded picture when available, falling back to DiceBear adventurer. */
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
  const signed = useSignedAvatarUrl(path);
  const safeSeed = encodeURIComponent((seed || "anonymous").trim().toLowerCase());
  const fallback = `https://api.dicebear.com/9.x/adventurer/svg?seed=${safeSeed}&backgroundType=gradientLinear&radius=50`;
  return (
    <img
      src={signed ?? fallback}
      alt={alt}
      width={size}
      height={size}
      loading="lazy"
      className={cn("rounded-full bg-surface-2 object-cover shrink-0", className)}
      style={{ width: size, height: size }}
    />
  );
}

/** Backwards-compatible default avatar (no uploaded picture). */
export function DiceBearAvatar(props: { seed: string; size?: number; className?: string; alt?: string }) {
  return <UserAvatar {...props} />;
}
