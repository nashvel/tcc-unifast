import { useRef, useState } from "react";
import { useAuthStore } from "@/stores/authStore";
import { UserAvatar } from "@/components/ui/dicebear-avatar";
import { Btn } from "@/components/ui/btn";
import { IconUpload, IconTrash } from "@tabler/icons-react";
import { toast } from "sonner";
import { setAvatarUrlLocal } from "@/lib/mock-auth";

const MAX_BYTES = 4 * 1024 * 1024;

/** Mock avatar editor: stores a data URL in localStorage. */
export function AvatarEditor() {
  const email = useAuthStore((s) => s.email);
  const role = useAuthStore((s) => s.role);
  const profile = useAuthStore((s) => s.profile);
  const setProfile = useAuthStore((s) => s.setProfile);

  const [busy, setBusy] = useState(false);
  const fileRef = useRef<HTMLInputElement>(null);

  const displayName = profile?.full_name || email || "User";
  const seed = email || displayName;

  async function handleFile(file: File) {
    if (!role) return toast.error("Not signed in");
    if (!file.type.startsWith("image/")) return toast.error("Pick an image file");
    if (file.size > MAX_BYTES) return toast.error("Image must be under 4 MB");
    setBusy(true);
    try {
      const dataUrl: string = await new Promise((resolve, reject) => {
        const r = new FileReader();
        r.onload = () => resolve(r.result as string);
        r.onerror = () => reject(new Error("Read failed"));
        r.readAsDataURL(file);
      });
      setAvatarUrlLocal(role, dataUrl);
      if (profile) setProfile({ ...profile, avatar_url: dataUrl });
      toast.success("Avatar updated");
    } catch (e) {
      toast.error(e instanceof Error ? e.message : "Upload failed");
    } finally {
      setBusy(false);
      if (fileRef.current) fileRef.current.value = "";
    }
  }

  function removeAvatar() {
    if (!role) return;
    setAvatarUrlLocal(role, null);
    if (profile) setProfile({ ...profile, avatar_url: null });
    toast.success("Avatar reset to default");
  }

  return (
    <div className="rounded-lg border bg-surface p-4">
      <p className="text-sm font-semibold mb-3">Profile picture</p>
      <div className="flex items-center gap-4">
        <UserAvatar seed={seed} path={profile?.avatar_url} size={72} className="ring-2 ring-border" />
        <div className="flex-1 min-w-0">
          <p className="text-xs text-text-muted mb-2">
            PNG or JPG, square works best. Up to 4 MB. Stored locally in demo mode.
          </p>
          <div className="flex flex-wrap gap-2">
            <Btn variant="primary" icon={IconUpload} onClick={() => fileRef.current?.click()} disabled={busy}>
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
