import jsQR from "jsqr";

const DEFAULT_DOMAINS = ["registrar.tcc.edu.ph", "sis.tcc.edu.ph", "tcc.edu.ph"];

function configuredDomains(): string[] {
  const fromEnv = String(import.meta.env.VITE_TCC_REGISTRAR_DOMAINS || "")
    .split(",")
    .map((value) => value.trim().toLowerCase())
    .filter(Boolean);
  return fromEnv.length ? fromEnv : DEFAULT_DOMAINS;
}

export function isTccRegistrarQr(payload: string): boolean {
  const value = payload.trim().toLowerCase();
  if (!value) return false;

  let host = "";
  try {
    if (/^https?:\/\//i.test(payload)) {
      host = new URL(payload).hostname.toLowerCase();
    }
  } catch {
    host = "";
  }

  return configuredDomains().some((domain) => {
    if (host && (host === domain || host.endsWith(`.${domain}`))) return true;
    return value.includes(domain);
  });
}

export function decodeQrFromVideo(video: HTMLVideoElement): string | null {
  if (!video.videoWidth || !video.videoHeight) return null;

  const canvas = document.createElement("canvas");
  canvas.width = video.videoWidth;
  canvas.height = video.videoHeight;
  const ctx = canvas.getContext("2d", { willReadFrequently: true });
  if (!ctx) return null;
  ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
  const image = ctx.getImageData(0, 0, canvas.width, canvas.height);
  const code = jsQR(image.data, image.width, image.height, { inversionAttempts: "attemptBoth" });
  return code?.data?.trim() || null;
}

export async function decodeQrFromBlob(blob: Blob): Promise<string | null> {
  const bitmap = await createImageBitmap(blob);
  const canvas = document.createElement("canvas");
  canvas.width = bitmap.width;
  canvas.height = bitmap.height;
  const ctx = canvas.getContext("2d", { willReadFrequently: true });
  if (!ctx) return null;
  ctx.drawImage(bitmap, 0, 0);
  const image = ctx.getImageData(0, 0, canvas.width, canvas.height);
  const code = jsQR(image.data, image.width, image.height, { inversionAttempts: "attemptBoth" });
  return code?.data?.trim() || null;
}
