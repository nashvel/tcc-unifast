type FaceApiModule = typeof import("face-api.js");

/** Served from Vite `public/models/face-api` (or Laravel public in production builds). */
const modelUrl = "/models/face-api";
let faceapiPromise: Promise<FaceApiModule> | null = null;
let loading: Promise<void> | null = null;
let modelsReady = false;

async function faceApi(): Promise<FaceApiModule> {
  if (!faceapiPromise) {
    faceapiPromise = import("face-api.js");
  }
  return faceapiPromise;
}

export type FaceDescriptorResult = {
  descriptor: number[];
  quality: number;
};

export type FaceCropResult = FaceDescriptorResult & {
  blob: Blob;
  box: { x: number; y: number; width: number; height: number };
};

export type FaceBox = { x: number; y: number; width: number; height: number };

export type LiveFaceGuideHit = {
  detected: boolean;
  inGuide: boolean;
  /** Face in guide and ID card fills most of the guide rectangle. */
  ready: boolean;
  quality: number;
  fillRatio: number;
  box: FaceBox | null;
};

export type IdFillGuideHit = {
  filled: boolean;
  fillRatio: number;
  quality: number;
};

export type Challenge = "blink" | "turn_left" | "turn_right";

export function areFaceModelsReady() {
  return modelsReady;
}

/** Clear failed load so callers can retry after network/tunnel errors. */
export function resetFaceModelLoad() {
  loading = null;
  modelsReady = false;
}

export async function loadFaceModels() {
  if (modelsReady) return;
  if (!loading) {
    loading = (async () => {
      try {
        const api = await faceApi();
        await Promise.all([
          api.nets.tinyFaceDetector.loadFromUri(modelUrl),
          api.nets.faceLandmark68Net.loadFromUri(modelUrl),
          api.nets.faceRecognitionNet.loadFromUri(modelUrl),
        ]);
        modelsReady = true;
      } catch (error) {
        loading = null;
        modelsReady = false;
        const detail = error instanceof Error ? error.message : String(error);
        throw new Error(
          `Face detection models failed to load from ${modelUrl}. ${detail} Hard-refresh, then tap Retry models.`,
        );
      }
    })();
  }

  return loading;
}

/**
 * Map an overlay guide rect (DOM) into the video's intrinsic pixel space,
 * accounting for CSS `object-cover` cropping.
 */
export function mapGuideRectToVideoPixels(video: HTMLVideoElement, guideEl: HTMLElement): FaceBox {
  const vr = video.getBoundingClientRect();
  const gr = guideEl.getBoundingClientRect();
  const vw = video.videoWidth || 1;
  const vh = video.videoHeight || 1;
  const videoAspect = vw / vh;
  const elemAspect = vr.width / Math.max(vr.height, 1);

  let scale: number;
  let offsetX = 0;
  let offsetY = 0;
  if (elemAspect > videoAspect) {
    scale = vr.width / vw;
    offsetY = (vr.height - vh * scale) / 2;
  } else {
    scale = vr.height / vh;
    offsetX = (vr.width - vw * scale) / 2;
  }

  return {
    x: (gr.left - vr.left - offsetX) / scale,
    y: (gr.top - vr.top - offsetY) / scale,
    width: gr.width / scale,
    height: gr.height / scale,
  };
}

function boxCenter(box: FaceBox) {
  return { x: box.x + box.width / 2, y: box.y + box.height / 2 };
}

function centerInside(box: FaceBox, guide: FaceBox, inset = 0.08) {
  const c = boxCenter(box);
  const padX = guide.width * inset;
  const padY = guide.height * inset;
  return (
    c.x >= guide.x + padX &&
    c.x <= guide.x + guide.width - padX &&
    c.y >= guide.y + padY &&
    c.y <= guide.y + guide.height - padY
  );
}

/**
 * Estimate how much of the guide rectangle is filled by an ID-like card
 * (edge contrast + interior texture). No ML — used for back-side green-ready.
 */
export function estimateIdFillInGuide(
  video: HTMLVideoElement,
  guideEl: HTMLElement,
): IdFillGuideHit {
  if (video.readyState < 2) {
    return { filled: false, fillRatio: 0, quality: 0 };
  }

  const guide = mapGuideRectToVideoPixels(video, guideEl);
  const sx = Math.max(0, Math.floor(guide.x));
  const sy = Math.max(0, Math.floor(guide.y));
  const sw = Math.max(8, Math.floor(guide.width));
  const sh = Math.max(8, Math.floor(guide.height));
  const sampleW = 48;
  const sampleH = 64;
  const canvas = document.createElement("canvas");
  canvas.width = sampleW;
  canvas.height = sampleH;
  const ctx = canvas.getContext("2d", { willReadFrequently: true });
  if (!ctx) return { filled: false, fillRatio: 0, quality: 0 };

  ctx.drawImage(video, sx, sy, sw, sh, 0, 0, sampleW, sampleH);
  const { data } = ctx.getImageData(0, 0, sampleW, sampleH);

  let sum = 0;
  let sumSq = 0;
  let edgeHits = 0;
  let edgeTotal = 0;
  const lumAt = (x: number, y: number) => {
    const i = (y * sampleW + x) * 4;
    return 0.299 * data[i] + 0.587 * data[i + 1] + 0.114 * data[i + 2];
  };

  for (let y = 0; y < sampleH; y += 1) {
    for (let x = 0; x < sampleW; x += 1) {
      const lum = lumAt(x, y);
      sum += lum;
      sumSq += lum * lum;
      const onBorder = x <= 2 || y <= 2 || x >= sampleW - 3 || y >= sampleH - 3;
      if (onBorder && x + 1 < sampleW && y + 1 < sampleH) {
        edgeTotal += 1;
        const gx = Math.abs(lumAt(x + 1, y) - lum);
        const gy = Math.abs(lumAt(x, y + 1) - lum);
        if (gx + gy > 28) edgeHits += 1;
      }
    }
  }

  const n = sampleW * sampleH;
  const mean = sum / n;
  const variance = Math.max(0, sumSq / n - mean * mean);
  const std = Math.sqrt(variance);
  const edgeRatio = edgeTotal > 0 ? edgeHits / edgeTotal : 0;
  // Texture + border edges ≈ card filling the portrait guide.
  const fillRatio = Math.min(1, std / 42) * 0.55 + Math.min(1, edgeRatio / 0.22) * 0.45;
  const filled = fillRatio >= 0.58 && std >= 16 && edgeRatio >= 0.12;

  return {
    filled,
    fillRatio: Number(fillRatio.toFixed(2)),
    quality: Number(Math.min(1, fillRatio).toFixed(2)),
  };
}

/** Lightweight live check: face inside guide with usable size/score (no descriptor). */
export async function detectFaceInGuide(
  video: HTMLVideoElement,
  guideEl: HTMLElement,
): Promise<LiveFaceGuideHit> {
  await loadFaceModels();
  const api = await faceApi();
  const fill = estimateIdFillInGuide(video, guideEl);
  const result = await api.detectSingleFace(
    video,
    new api.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.4 }),
  );

  if (!result) {
    return {
      detected: false,
      inGuide: false,
      ready: false,
      quality: 0,
      fillRatio: fill.fillRatio,
      box: null,
    };
  }

  const box: FaceBox = {
    x: result.box.x,
    y: result.box.y,
    width: result.box.width,
    height: result.box.height,
  };
  const guide = mapGuideRectToVideoPixels(video, guideEl);
  const minFace = Math.min(guide.width, guide.height) * 0.07;
  const maxFace = Math.min(guide.width, guide.height) * 0.62;
  const faceSpan = Math.max(box.width, box.height);
  const sizedOk = faceSpan >= minFace && faceSpan <= maxFace;
  const inGuide = centerInside(box, guide, 0.06) && sizedOk && result.score >= 0.4;
  const ready = inGuide && fill.filled;

  return {
    detected: true,
    inGuide,
    ready,
    quality: Number(result.score.toFixed(2)),
    fillRatio: fill.fillRatio,
    box,
  };
}

/**
 * Fast post-capture face crop: prefer a recent live box, run descriptor only on the crop.
 * Avoids a second full-frame 416px detection pass that made “Verifying…” feel stuck.
 */
export async function cropFaceFromVideoFast(
  video: HTMLVideoElement,
  hintBox: FaceBox | null = null,
): Promise<FaceCropResult> {
  await loadFaceModels();
  const api = await faceApi();
  const vw = video.videoWidth || 960;
  const vh = video.videoHeight || 720;

  if (hintBox && hintBox.width > 8 && hintBox.height > 8) {
    const pad = Math.max(hintBox.width, hintBox.height) * 0.45;
    const sx = Math.max(0, hintBox.x - pad);
    const sy = Math.max(0, hintBox.y - pad);
    const sw = Math.min(vw - sx, hintBox.width + pad * 2);
    const sh = Math.min(vh - sy, hintBox.height + pad * 2);
    const cropCanvas = document.createElement("canvas");
    cropCanvas.width = Math.max(160, Math.round(sw));
    cropCanvas.height = Math.max(160, Math.round(sh));
    const cropCtx = cropCanvas.getContext("2d");
    if (!cropCtx) throw new Error("Unable to crop ID face.");
    cropCtx.drawImage(video, sx, sy, sw, sh, 0, 0, cropCanvas.width, cropCanvas.height);

    const result = await api
      .detectSingleFace(cropCanvas, new api.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.35 }))
      .withFaceLandmarks()
      .withFaceDescriptor();

    if (result) {
      const out = document.createElement("canvas");
      out.width = 240;
      out.height = 240;
      const outCtx = out.getContext("2d");
      if (!outCtx) throw new Error("Unable to crop ID face.");
      const box = result.detection.box;
      const pad2 = Math.max(box.width, box.height) * 0.3;
      const cx = Math.max(0, box.x - pad2);
      const cy = Math.max(0, box.y - pad2);
      const cw = Math.min(cropCanvas.width - cx, box.width + pad2 * 2);
      const ch = Math.min(cropCanvas.height - cy, box.height + pad2 * 2);
      outCtx.drawImage(cropCanvas, cx, cy, cw, ch, 0, 0, 240, 240);
      const blob = await new Promise<Blob>((resolve, reject) => {
        out.toBlob(
          (value) => (value ? resolve(value) : reject(new Error("Unable to encode face crop."))),
          "image/jpeg",
          0.88,
        );
      });
      return {
        descriptor: Array.from(result.descriptor),
        quality: Number(result.detection.score.toFixed(2)),
        blob,
        box: {
          x: sx + box.x,
          y: sy + box.y,
          width: box.width,
          height: box.height,
        },
      };
    }
  }

  // Fallback: one lighter full-frame pass (320) instead of 416.
  const result = await api
    .detectSingleFace(video, new api.TinyFaceDetectorOptions({ inputSize: 320, scoreThreshold: 0.4 }))
    .withFaceLandmarks()
    .withFaceDescriptor();

  if (!result) {
    throw new Error("No face was detected on the ID card. Align the photo inside the guide frame.");
  }

  const box = result.detection.box;
  const pad = Math.max(box.width, box.height) * 0.35;
  const sx = Math.max(0, box.x - pad);
  const sy = Math.max(0, box.y - pad);
  const sw = Math.min(vw - sx, box.width + pad * 2);
  const sh = Math.min(vh - sy, box.height + pad * 2);
  const canvas = document.createElement("canvas");
  canvas.width = 240;
  canvas.height = 240;
  const ctx = canvas.getContext("2d");
  if (!ctx) throw new Error("Unable to crop ID face.");
  ctx.drawImage(video, sx, sy, sw, sh, 0, 0, 240, 240);
  const blob = await new Promise<Blob>((resolve, reject) => {
    canvas.toBlob(
      (value) => (value ? resolve(value) : reject(new Error("Unable to encode face crop."))),
      "image/jpeg",
      0.88,
    );
  });

  return {
    descriptor: Array.from(result.descriptor),
    quality: Number(result.detection.score.toFixed(2)),
    blob,
    box: { x: box.x, y: box.y, width: box.width, height: box.height },
  };
}

export async function descriptorFromImage(file: File | HTMLImageElement): Promise<FaceDescriptorResult> {
  await loadFaceModels();
  const api = await faceApi();
  const image = file instanceof HTMLImageElement ? file : await api.bufferToImage(file);
  const result = await api
    .detectSingleFace(image, new api.TinyFaceDetectorOptions({ inputSize: 416 }))
    .withFaceLandmarks()
    .withFaceDescriptor();

  if (!result) {
    throw new Error("No face was detected on the School ID front image.");
  }

  return {
    descriptor: Array.from(result.descriptor),
    quality: Number(result.detection.score.toFixed(2)),
  };
}

export async function cropFaceFromImage(source: File | HTMLImageElement | HTMLCanvasElement): Promise<FaceCropResult> {
  await loadFaceModels();
  const api = await faceApi();
  let image: HTMLImageElement | HTMLCanvasElement;
  if (source instanceof File) {
    image = await api.bufferToImage(source);
  } else {
    image = source;
  }

  const result = await api
    .detectSingleFace(image, new api.TinyFaceDetectorOptions({ inputSize: 416 }))
    .withFaceLandmarks()
    .withFaceDescriptor();

  if (!result) {
    throw new Error("No face was detected on the ID card. Align the photo inside the guide frame.");
  }

  const box = result.detection.box;
  const pad = Math.max(box.width, box.height) * 0.35;
  const sx = Math.max(0, box.x - pad);
  const sy = Math.max(0, box.y - pad);
  const sw = Math.min((image as HTMLImageElement).width || (image as HTMLCanvasElement).width, box.width + pad * 2);
  const sh = Math.min((image as HTMLImageElement).height || (image as HTMLCanvasElement).height, box.height + pad * 2);

  const canvas = document.createElement("canvas");
  canvas.width = 240;
  canvas.height = 240;
  const ctx = canvas.getContext("2d");
  if (!ctx) throw new Error("Unable to crop ID face.");
  ctx.drawImage(image, sx, sy, sw, sh, 0, 0, 240, 240);

  const blob = await new Promise<Blob>((resolve, reject) => {
    canvas.toBlob((value) => (value ? resolve(value) : reject(new Error("Unable to encode face crop."))), "image/jpeg", 0.92);
  });

  return {
    descriptor: Array.from(result.descriptor),
    quality: Number(result.detection.score.toFixed(2)),
    blob,
    box: { x: box.x, y: box.y, width: box.width, height: box.height },
  };
}

export async function descriptorFromVideo(video: HTMLVideoElement): Promise<FaceDescriptorResult> {
  await loadFaceModels();
  const api = await faceApi();
  const result = await api
    .detectSingleFace(video, new api.TinyFaceDetectorOptions({ inputSize: 416 }))
    .withFaceLandmarks()
    .withFaceDescriptor();

  if (!result) {
    throw new Error("No live face was detected. Keep your face centered and well lit.");
  }

  return {
    descriptor: Array.from(result.descriptor),
    quality: Number(result.detection.score.toFixed(2)),
  };
}

export async function detectChallenge(video: HTMLVideoElement, challenge: Challenge): Promise<boolean> {
  await loadFaceModels();
  const api = await faceApi();
  const result = await api
    .detectSingleFace(video, new api.TinyFaceDetectorOptions({ inputSize: 224 }))
    .withFaceLandmarks();

  if (!result) return false;

  const landmarks = result.landmarks;
  const leftEye = landmarks.getLeftEye();
  const rightEye = landmarks.getRightEye();
  const nose = landmarks.getNose();
  const box = result.detection.box;

  if (challenge === "blink") {
    return eyeAspectRatio(leftEye) < 0.23 && eyeAspectRatio(rightEye) < 0.23;
  }

  const leftCenter = averageX(leftEye);
  const rightCenter = averageX(rightEye);
  const faceCenter = (leftCenter + rightCenter) / 2;
  const noseX = nose[3]?.x ?? faceCenter;
  const offset = (noseX - faceCenter) / Math.max(box.width, 1);

  if (challenge === "turn_left") return offset > 0.045;
  return offset < -0.045;
}

export function euclideanDistance(first: number[], second: number[]) {
  if (first.length !== second.length) {
    throw new Error("Face descriptor sizes do not match.");
  }

  const total = first.reduce((sum, value, index) => sum + (value - second[index]) ** 2, 0);
  return Math.sqrt(total);
}

export async function descriptorFromUrl(url: string): Promise<FaceDescriptorResult> {
  await loadFaceModels();
  const api = await faceApi();

  // Authenticated API photo routes need a Bearer fetch; <img> cannot send tokens.
  if (url.includes("/api/") && !url.includes("signature=")) {
    const { getAuthToken } = await import("@/auth/session");
    const response = await fetch(url, {
      headers: {
        Accept: "image/*,application/octet-stream",
        Authorization: `Bearer ${getAuthToken() || ""}`,
      },
      credentials: "include",
    });
    if (!response.ok) {
      throw new Error("Unable to load reference face photo.");
    }
    const blob = await response.blob();
    const objectUrl = URL.createObjectURL(blob);
    try {
      const image = await api.fetchImage(objectUrl);
      return descriptorFromImage(image);
    } finally {
      URL.revokeObjectURL(objectUrl);
    }
  }

  const image = await api.fetchImage(url);
  return descriptorFromImage(image);
}

export function captureVideoFrame(video: HTMLVideoElement, quality = 0.9): Promise<Blob> {
  const canvas = document.createElement("canvas");
  canvas.width = video.videoWidth || 960;
  canvas.height = video.videoHeight || 720;
  const ctx = canvas.getContext("2d");
  if (!ctx) return Promise.reject(new Error("Unable to capture camera frame."));
  ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
  return new Promise((resolve, reject) => {
    canvas.toBlob((blob) => (blob ? resolve(blob) : reject(new Error("Unable to encode camera frame."))), "image/jpeg", quality);
  });
}

export function shuffleChallenges(): Challenge[] {
  return (["blink", "turn_left", "turn_right"] as Challenge[]).sort(() => Math.random() - 0.5);
}

function eyeAspectRatio(points: { x: number; y: number }[]) {
  const vertical = distance(points[1], points[5]) + distance(points[2], points[4]);
  const horizontal = 2 * distance(points[0], points[3]);
  return horizontal === 0 ? 1 : vertical / horizontal;
}

function distance(first: { x: number; y: number }, second: { x: number; y: number }) {
  return Math.hypot(first.x - second.x, first.y - second.y);
}

function averageX(points: { x: number; y: number }[]) {
  return points.reduce((sum, point) => sum + point.x, 0) / points.length;
}
