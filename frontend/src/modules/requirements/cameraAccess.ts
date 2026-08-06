/** Camera requires a secure context (HTTPS or localhost). LAN HTTP IPs often lack mediaDevices. */

export function cameraUnavailableReason(): string | null {
  if (typeof window === "undefined") {
    return "Camera is not available in this environment.";
  }
  if (!window.isSecureContext) {
    return "Camera requires HTTPS or localhost. Opening this page over a LAN HTTP IP (e.g. 192.168.x.x) blocks getUserMedia — use https://… or http://localhost:5173.";
  }
  if (!navigator.mediaDevices?.getUserMedia) {
    return "Camera API is unavailable in this browser. Use a modern browser over HTTPS or localhost.";
  }
  return null;
}

export async function getUserMediaSafe(
  constraints: MediaStreamConstraints,
): Promise<MediaStream> {
  const reason = cameraUnavailableReason();
  if (reason) {
    throw new Error(reason);
  }
  return navigator.mediaDevices.getUserMedia(constraints);
}
