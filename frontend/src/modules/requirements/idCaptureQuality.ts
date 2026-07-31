/**
 * Blur / glare checks on the School ID guide ROI (dev-phase capture gates).
 * Samples the guide rectangle from the live video (object-cover aware via caller mapping).
 */

import { mapGuideRectToVideoPixels } from "./faceApi";

export type GuideQualityHit = {
  ok: boolean;
  blurry: boolean;
  glare: boolean;
  /** Laplacian-ish variance proxy (higher = sharper). */
  sharpness: number;
  /** Fraction of near-white hot pixels (0–1). */
  glareRatio: number;
  reason: string;
};

const SAMPLE_W = 48;
const SAMPLE_H = 64;
/** Below this → too blurry for OCR. */
const MIN_SHARPNESS = 28;
/** Above this fraction of hot pixels → glare / flash. */
const MAX_GLARE_RATIO = 0.12;

export function analyzeGuideQuality(
  video: HTMLVideoElement,
  guideEl: HTMLElement,
): GuideQualityHit {
  if (video.readyState < 2) {
    return {
      ok: false,
      blurry: true,
      glare: false,
      sharpness: 0,
      glareRatio: 0,
      reason: "Camera not ready",
    };
  }

  const guide = mapGuideRectToVideoPixels(video, guideEl);
  const sx = Math.max(0, Math.floor(guide.x));
  const sy = Math.max(0, Math.floor(guide.y));
  const sw = Math.max(8, Math.floor(guide.width));
  const sh = Math.max(8, Math.floor(guide.height));
  const canvas = document.createElement("canvas");
  canvas.width = SAMPLE_W;
  canvas.height = SAMPLE_H;
  const ctx = canvas.getContext("2d", { willReadFrequently: true });
  if (!ctx) {
    return {
      ok: false,
      blurry: true,
      glare: false,
      sharpness: 0,
      glareRatio: 0,
      reason: "Unable to sample frame",
    };
  }

  ctx.drawImage(video, sx, sy, sw, sh, 0, 0, SAMPLE_W, SAMPLE_H);
  const { data } = ctx.getImageData(0, 0, SAMPLE_W, SAMPLE_H);

  const gray = new Float32Array(SAMPLE_W * SAMPLE_H);
  let hot = 0;
  for (let i = 0, p = 0; i < data.length; i += 4, p += 1) {
    const lum = 0.299 * data[i] + 0.587 * data[i + 1] + 0.114 * data[i + 2];
    gray[p] = lum;
    if (lum >= 245) hot += 1;
  }

  // Discrete Laplacian variance (blur proxy).
  let lapSum = 0;
  let lapSumSq = 0;
  let lapN = 0;
  for (let y = 1; y < SAMPLE_H - 1; y += 1) {
    for (let x = 1; x < SAMPLE_W - 1; x += 1) {
      const i = y * SAMPLE_W + x;
      const lap =
        -gray[i - SAMPLE_W] - gray[i - 1] + 4 * gray[i] - gray[i + 1] - gray[i + SAMPLE_W];
      lapSum += lap;
      lapSumSq += lap * lap;
      lapN += 1;
    }
  }
  const mean = lapN > 0 ? lapSum / lapN : 0;
  const sharpness = lapN > 0 ? lapSumSq / lapN - mean * mean : 0;
  const glareRatio = hot / Math.max(SAMPLE_W * SAMPLE_H, 1);
  const blurry = sharpness < MIN_SHARPNESS;
  const glare = glareRatio > MAX_GLARE_RATIO;

  let reason = "";
  if (blurry) reason = "Too blurry — hold steady";
  else if (glare) reason = "Reduce glare — tilt the ID (avoid flash on the sleeve)";

  return {
    ok: !blurry && !glare,
    blurry,
    glare,
    sharpness: Number(sharpness.toFixed(1)),
    glareRatio: Number(glareRatio.toFixed(3)),
    reason,
  };
}
