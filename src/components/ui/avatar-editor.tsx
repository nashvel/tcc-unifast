import { useRef, useState } from "react";
import { supabase } from "@/integrations/supabase/client";
import { useAuthStore } from "@/stores/authStore";
import { UserAvatar } from "@/components/ui/dicebear-avatar";
import { Btn } from "@/components/ui/btn";
import { IconUpload, IconTrash } from "@tabler/icons-react";
import { toast } from "sonner";

const MAX_BYTES = 4 * 1024 * 1024;

/** Settings card: upload, replace, or remove the signed-in user's avatar. */
export function AvatarEditor() {
  const userId = useAuthStore((s) => s.userId);
  const email = useAuthStore((s) => s.email);
  const profile = useAuthStore((s) => s.profile);
  const setProfile = useAuthStore((s) => s.setProfile);

  const [busy, setBusy] = useState(false);
  const fileRef = useRef<HTMLInputElement>(null);

  const displayName = profile?.full_name || email || "User";
  const seed = email || displayName;

  async function persistAvatarUrl(path: string | null) {
    if (!userId) return;
    const { error } = await supabase.from("profiles").update({ avatar_url: path }).eq("id", userId);
    if (error) throw error;
    setProfile(profile ? { ...profile, avatar_url: path } : profile);
  }

  async function handleFile(file: File) {
    if (!userId) return toast.error("Not signed in");
    if (!file.type.startsWith("image/")) return toast.error("Pick an image file");
    if (file.size > MAX_BYTES) return toast.error("Image must be under 4 MB");
    setBusy(true);
    try {
      const ext = file.name.split(".").pop()?.toLowerCase() || "png";
      const path = `${userId}/avatar-${Date.now()}.${ext}`;
      const { error: upErr } = await supabase.storage.from("avatars").upload(path, file, {
        upsert: true,
        contentType: file.type,
      });
      if (upErr) throw upErr;

      // Best-effort cleanup of the previous file.
      const prev = profile?.avatar_url;
      if (prev && !/^https?:\/\//.test(prev) && prev !== path) {
        await supabase.storage.from("avatars").remove([prev]);
      }
      await persistAvatarUrl(path);
      toast.success("Avatar updated");
    } catch (e) {
      const msg = e instanceof Error ? e.message : "Upload failed";
      toast.error(msg);
    } finally {
      setBusy(false);
      if (fileRef.current) fileRef.current.value = "";
    }
  }

  async function removeAvatar() {
    if (!profile?.avatar_url) return;
    setBusy(true);
    try {
      const prev = profile.avatar_url;
      if (prev && !/^https?:\/\//.test(prev)) {
        await supabase.storage.from("avatars").remove([prev]);
      }
      await persistAvatarUrl(null);
      toast.success("Avatar reset to default");
    } catch (e) {
      const msg = e instanceof Error ? e.message : "Could not remove";
      toast.error(msg);
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="rounded-lg border bg-surface p-4">
      <p className="text-sm font-semibold mb-3">Profile picture</p>
      <div className="flex items-center gap-4">
        <UserAvatar seed={seed} path={profile?.avatar_url} size={72} className="ring-2 ring-border" />
        <div className="flex-1 min-w-0">
          <p className="text-xs text-text-muted mb-2">
            PNG or JPG, square works best. Up to 4 MB. Leave empty to use the default DiceBear avatar.
          </p>
          <div className="flex flex-wrap gap-2">
            <Btn
              variant="primary"
              icon={IconUpload}
              onClick={() => fileRef.current?.click()}
              disabled={busy}
            >
              {profile?.avatar_url ? "Replace" : "Upload"}
            </Btn>
            {profile?.avatar_url && (
              <Btn variant="outline" icon={IconTrash} onClick={removeAvatar} disabled={busy}>
                Remove
              </Btn>
            )}
          </div>
          <input
            ref={fileRef}
            type="file"
            accept="image/*"
            className="hidden"
            onChange={(e) => {
              const f = e.target.files?.[0];
              if (f) handleFile(f);
            }}
          />
        </div>
      </div>
    </div>
  );
}
