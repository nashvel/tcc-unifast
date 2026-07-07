import { useCallback, useEffect, useRef, useState } from "react";
import { IconCamera, IconCheck, IconRefresh, IconId, IconUser, IconX } from "@tabler/icons-react";
import { Btn } from "@/components/ui/btn";
import { useAuthStore } from "@/stores/authStore";
import { toast } from "sonner";

type Step = "id" | "face" | "done";

interface Props {
  onClose: () => void;
  onSkip: () => void;
  onComplete: () => void;
}

const STEP_META: Record<Exclude<Step, "done">, { title: string; hint: string; icon: typeof IconId; frame: string }> = {
  id:   { title: "Scan your Student ID",  hint: "Place your ID inside the frame. Make sure all text is readable.", icon: IconId,   frame: "aspect-[1.6/1]" },
  face: { title: "Scan your face",        hint: "Center your face in the oval. Good lighting helps verification.", icon: IconUser, frame: "aspect-[3/4] max-w-[260px] mx-auto" },
};

const DONE_KEY = "unifast.mock.onboarding_completed_at";

export function OnboardingScan({ onClose, onSkip, onComplete }: Props) {
  const userId = useAuthStore((s) => s.userId);
  const [step, setStep] = useState<Step>("id");
  const [idShot, setIdShot] = useState<string | null>(null);
  const [faceShot, setFaceShot] = useState<string | null>(null);
  const [streamErr, setStreamErr] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  const videoRef = useRef<HTMLVideoElement | null>(null);
  const streamRef = useRef<MediaStream | null>(null);

  const stop = useCallback(() => {
    streamRef.current?.getTracks().forEach((t) => t.stop());
    streamRef.current = null;
  }, []);

  const start = useCallback(async (facingMode: "environment" | "user") => {
    setStreamErr(null);
    stop();
    try {
      const stream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode, width: { ideal: 1280 }, height: { ideal: 720 } },
        audio: false,
      });
      streamRef.current = stream;
      if (videoRef.current) {
        videoRef.current.srcObject = stream;
        await videoRef.current.play().catch(() => {});
      }
    } catch (e) {
      const msg = e instanceof Error ? e.message : "Camera unavailable";
      setStreamErr(msg);
    }
  }, [stop]);

  useEffect(() => {
    if (step === "done") return;
    const hasShot = step === "id" ? idShot : faceShot;
    if (hasShot) return;
    start(step === "id" ? "environment" : "user");
    return stop;
  }, [step, idShot, faceShot, start, stop]);

  useEffect(() => stop, [stop]);

  const capture = () => {
    const v = videoRef.current;
    if (!v || !v.videoWidth) return;
    const canvas = document.createElement("canvas");
    canvas.width = v.videoWidth;
    canvas.height = v.videoHeight;
    const ctx = canvas.getContext("2d");
    if (!ctx) return;
    ctx.drawImage(v, 0, 0);
    const dataUrl = canvas.toDataURL("image/jpeg", 0.85);
    if (step === "id") setIdShot(dataUrl);
    else setFaceShot(dataUrl);
    stop();
  };

  const retake = () => {
    if (step === "id") setIdShot(null);
    else setFaceShot(null);
  };

  const next = () => {
    if (step === "id" && idShot) setStep("face");
    else if (step === "face" && faceShot) finish();
  };

  const finish = async () => {
    if (!userId) return;
    setSaving(true);
    await new Promise((r) => setTimeout(r, 300));
    localStorage.setItem(DONE_KEY, new Date().toISOString());
    setSaving(false);
    toast.success("Verification scans saved");
    setStep("done");
    onComplete();
  };

  if (step === "done") return null;
  const meta = STEP_META[step];
  const Icon = meta.icon;
  const currentShot = step === "id" ? idShot : faceShot;

  return (
    <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm grid place-items-center p-4" role="dialog" aria-modal="true">
      <div className="bg-surface rounded-2xl shadow-xl w-full max-w-xl overflow-hidden flex flex-col max-h-[92vh]">
        <header className="flex items-center justify-between px-5 h-14 border-b">
          <div className="flex items-center gap-2">
            <span className="h-8 w-8 rounded-lg bg-primary-soft text-primary grid place-items-center"><Icon size={16} /></span>
            <div>
              <p className="text-sm font-semibold">{meta.title}</p>
              <p className="text-[11px] text-text-muted">Step {step === "id" ? 1 : 2} of 2 · Identity verification</p>
            </div>
          </div>
          <button onClick={onClose} aria-label="Close" className="p-1.5 rounded-md hover:bg-surface-muted"><IconX size={16} /></button>
        </header>

        <div className="p-5 space-y-4 overflow-y-auto">
          <p className="text-sm text-text-muted">{meta.hint}</p>

          <div className={`relative rounded-xl border bg-black overflow-hidden ${meta.frame}`}>
            {currentShot ? (
              <img src={currentShot} alt={`${step} capture preview`} className="w-full h-full object-cover" />
            ) : streamErr ? (
              <div className="absolute inset-0 grid place-items-center p-4 text-center text-white">
                <div>
                  <IconCamera size={28} className="mx-auto mb-2 opacity-70" />
                  <p className="text-sm">{streamErr}</p>
                  <p className="text-[11px] text-white/70 mt-1">Allow camera access or skip for now.</p>
                </div>
              </div>
            ) : (
              <>
                <video ref={videoRef} playsInline muted className="w-full h-full object-cover" />
                <div className={`pointer-events-none absolute inset-3 border-2 border-white/80 ${step === "face" ? "rounded-full" : "rounded-lg"}`} />
              </>
            )}
          </div>

          <div className="flex items-center justify-between gap-2">
            <Btn variant="ghost" onClick={onSkip}>Skip for now</Btn>
            <div className="flex gap-2">
              {currentShot ? (
                <>
                  <Btn variant="outline" icon={IconRefresh} onClick={retake}>Retake</Btn>
                  <Btn variant="primary" icon={IconCheck} onClick={next} disabled={saving}>
                    {step === "face" ? (saving ? "Saving…" : "Finish") : "Use photo"}
                  </Btn>
                </>
              ) : (
                <Btn variant="primary" icon={IconCamera} onClick={capture} disabled={!!streamErr}>Capture</Btn>
              )}
            </div>
          </div>
        </div>

        <footer className="px-5 py-3 border-t bg-surface-muted/40 text-[11px] text-text-muted">
          Skipping closes the prompt for now — it will reappear the next time you sign in.
        </footer>
      </div>
    </div>
  );
}
