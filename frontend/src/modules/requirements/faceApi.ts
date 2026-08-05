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
  /** How many of the four guide borders show card-like edge contrast (0–4). */
  edgeSides: number;
};

export type IdFillOptions = {
  /**
   * Stricter card gate — prefer for back (no face detector).
   * Rejects bare floor/wall texture that can look “busy” enough for soft fill.
   */
  strict?: boolean;
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
 * (edge contrast + interior texture + multi-side border edges). No ML.
 */
export function estimateIdFillInGuide(
  video: HTMLVideoElement,
  guideEl: HTMLElement,
  options: IdFillOptions = {},
): IdFillGuideHit {
  const empty = (): IdFillGuideHit => ({ filled: false, fillRatio: 0, quality: 0, edgeSides: 0 });
  if (video.readyState < 2) {
    return empty();
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
  if (!ctx) return empty();

  ctx.drawImage(video, sx, sy, sw, sh, 0, 0, sampleW, sampleH);
  const { data } = ctx.getImageData(0, 0, sampleW, sampleH);

  const strict = Boolean(options.strict);
  /** Stronger gradient needed on back so floor grain does not count as a card edge. */
  const edgeGradMin = strict ? 40 : 32;

  let sum = 0;
  let sumSq = 0;
  let edgeHits = 0;
  let edgeTotal = 0;
  const sideHits = { top: 0, bottom: 0, left: 0, right: 0 };
  const sideTotal = { top: 0, bottom: 0, left: 0, right: 0 };
  const lumAt = (x: number, y: number) => {
    const i = (y * sampleW + x) * 4;
    return 0.299 * data[i] + 0.587 * data[i + 1] + 0.114 * data[i + 2];
  };

  for (let y = 0; y < sampleH; y += 1) {
    for (let x = 0; x < sampleW; x += 1) {
      const lum = lumAt(x, y);
      sum += lum;
      sumSq += lum * lum;
      const nearTop = y <= 2;
      const nearBottom = y >= sampleH - 3;
      const nearLeft = x <= 2;
      const nearRight = x >= sampleW - 3;
      const onBorder = nearTop || nearBottom || nearLeft || nearRight;
      if (onBorder && x + 1 < sampleW && y + 1 < sampleH) {
        edgeTotal += 1;
        const gx = Math.abs(lumAt(x + 1, y) - lum);
        const gy = Math.abs(lumAt(x, y + 1) - lum);
        const isEdge = gx + gy > edgeGradMin;
        if (isEdge) edgeHits += 1;
        if (nearTop) {
          sideTotal.top += 1;
          if (isEdge) sideHits.top += 1;
        }
        if (nearBottom) {
          sideTotal.bottom += 1;
          if (isEdge) sideHits.bottom += 1;
        }
        if (nearLeft) {
          sideTotal.left += 1;
          if (isEdge) sideHits.left += 1;
        }
        if (nearRight) {
          sideTotal.right += 1;
          if (isEdge) sideHits.right += 1;
        }
      }
    }
  }

  const n = sampleW * sampleH;
  const mean = sum / n;
  const variance = Math.max(0, sumSq / n - mean * mean);
  const std = Math.sqrt(variance);
  const edgeRatio = edgeTotal > 0 ? edgeHits / edgeTotal : 0;
  const sideEdgeMin = strict ? 0.12 : 0.08;
  const edgeSides = (["top", "bottom", "left", "right"] as const).filter((side) => {
    const total = sideTotal[side];
    return total > 0 && sideHits[side] / total >= sideEdgeMin;
  }).length;

  // Texture + border edges ≈ card filling the portrait guide.
  const fillRatio = Math.min(1, std / 42) * 0.55 + Math.min(1, edgeRatio / 0.22) * 0.45;
  const filled = strict
    ? fillRatio >= 0.62 && std >= 20 && edgeRatio >= 0.14 && edgeSides >= 3
    : fillRatio >= 0.52 && std >= 15 && edgeRatio >= 0.1 && edgeSides >= 2;

  return {
    filled,
    fillRatio: Number(fillRatio.toFixed(2)),
    quality: Number(Math.min(1, fillRatio).toFixed(2)),
    edgeSides,
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
    new api.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.32 }),
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
  const minFace = Math.min(guide.width, guide.height) * 0.05;
  const maxFace = Math.min(guide.width, guide.height) * 0.68;
  const faceSpan = Math.max(box.width, box.height);
  const sizedOk = faceSpan >= minFace && faceSpan <= maxFace;
  const inGuide = centerInside(box, guide, 0.1) && sizedOk && result.score >= 0.32;
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
      // Extra padding so the stored crop remains re-detectable (tight 240 crops often fail TinyFaceDetector).
      const pad2 = Math.max(box.width, box.height) * 0.45;
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
  const pad = Math.max(box.width, box.height) * 0.45;
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

/** Geometric crop from a live hint box (no face-api). Used when descriptor pass times out. */
async function encodeHintBoxCrop(
  video: HTMLVideoElement,
  hintBox: FaceBox,
): Promise<{ blob: Blob; box: FaceBox }> {
  const vw = video.videoWidth || 960;
  const vh = video.videoHeight || 720;
  const pad = Math.max(hintBox.width, hintBox.height) * 0.35;
  const sx = Math.max(0, hintBox.x - pad);
  const sy = Math.max(0, hintBox.y - pad);
  const sw = Math.min(vw - sx, hintBox.width + pad * 2);
  const sh = Math.min(vh - sy, hintBox.height + pad * 2);
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
  return { blob, box: { x: sx, y: sy, width: sw, height: sh } };
}

/**
 * Face crop with a hard timeout so phones aren't held during slow face-api.
 * On timeout: keep geometric lastFaceBox crop and race a short descriptor-only pass on it.
 */
export async function cropFaceFromVideoWithTimeout(
  video: HTMLVideoElement,
  hintBox: FaceBox | null = null,
  timeoutMs = 1800,
): Promise<FaceCropResult> {
  const timedOut = new Promise<never>((_, reject) => {
    window.setTimeout(() => reject(new Error("FACE_CROP_TIMEOUT")), timeoutMs);
  });

  try {
    return await Promise.race([cropFaceFromVideoFast(video, hintBox), timedOut]);
  } catch (exception) {
    const isTimeout =
      exception instanceof Error && exception.message === "FACE_CROP_TIMEOUT";
    if (!isTimeout || !hintBox || hintBox.width <= 8 || hintBox.height <= 8) {
      throw exception instanceof Error
        ? exception
        : new Error("Face crop failed. Tap Capture again.");
    }

    const geometric = await encodeHintBoxCrop(video, hintBox);
    const api = await faceApi();
    const cropImg = document.createElement("canvas");
    cropImg.width = 240;
    cropImg.height = 240;
    const cropCtx = cropImg.getContext("2d");
    if (!cropCtx) throw new Error("Unable to crop ID face.");
    // Re-draw from the encoded path: load blob into image for descriptor.
    const url = URL.createObjectURL(geometric.blob);
    try {
      const image = await new Promise<HTMLImageElement>((resolve, reject) => {
        const img = new Image();
        img.onload = () => resolve(img);
        img.onerror = () => reject(new Error("Unable to load face crop."));
        img.src = url;
      });
      cropCtx.drawImage(image, 0, 0, 240, 240);

      const descriptorTimeout = new Promise<never>((_, reject) => {
        window.setTimeout(() => reject(new Error("FACE_DESCRIPTOR_TIMEOUT")), 1200);
      });
      const result = await Promise.race([
        api
          .detectSingleFace(cropImg, new api.TinyFaceDetectorOptions({ inputSize: 160, scoreThreshold: 0.3 }))
          .withFaceLandmarks()
          .withFaceDescriptor(),
        descriptorTimeout,
      ]);

      if (result) {
        return {
          descriptor: Array.from(result.descriptor),
          quality: Number(result.detection.score.toFixed(2)),
          blob: geometric.blob,
          box: geometric.box,
        };
      }
    } catch {
      // Fall through — geometric crop without a fresh descriptor is not usable for liveness.
    } finally {
      URL.revokeObjectURL(url);
    }

    throw new Error("Face crop timed out. Tap Capture again with the ID held steady.");
  }
}

/** TinyFaceDetector passes tuned for full frames and tight face crops. */
const DESCRIPTOR_DETECT_PASSES: Array<{ inputSize: 160 | 224 | 320 | 416; scoreThreshold: number }> = [
  { inputSize: 224, scoreThreshold: 0.3 },
  { inputSize: 160, scoreThreshold: 0.25 },
  { inputSize: 320, scoreThreshold: 0.25 },
  { inputSize: 416, scoreThreshold: 0.2 },
];

async function detectDescriptorWithRetries(
  api: FaceApiModule,
  image: HTMLImageElement | HTMLCanvasElement,
) {
  for (const opts of DESCRIPTOR_DETECT_PASSES) {
    const result = await api
      .detectSingleFace(image, new api.TinyFaceDetectorOptions(opts))
      .withFaceLandmarks()
      .withFaceDescriptor();
    if (result) return result;
  }
  return null;
}

export async function descriptorFromImage(file: File | HTMLImageElement): Promise<FaceDescriptorResult> {
  await loadFaceModels();
  const api = await faceApi();
  let image: HTMLImageElement;
  try {
    image = file instanceof HTMLImageElement ? file : await api.bufferToImage(file);
  } catch {
    throw new Error("Unable to load the School ID face image. Check your connection and retry.");
  }

  if (!image.naturalWidth && !image.width) {
    throw new Error("Unable to load the School ID face image. Check your connection and retry.");
  }

  const result = await detectDescriptorWithRetries(api, image);

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
    try {
      image = await api.bufferToImage(source);
    } catch {
      throw new Error("Unable to load the ID card image. Check your connection and retry.");
    }
  } else {
    image = source;
  }

  const result = await detectDescriptorWithRetries(api, image);

  if (!result) {
    throw new Error("No face was detected on the ID card. Align the photo inside the guide frame.");
  }

  const box = result.detection.box;
  const pad = Math.max(box.width, box.height) * 0.45;
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

export type OvalFaceHit = {
  detected: boolean;
  /** Face centered in the oval with usable size/score (liveness ready). */
  ready: boolean;
  quality: number;
  box: FaceBox | null;
};

/** Lightweight live check: face inside oval guide (no ID-fill, no descriptor). */
export async function faceInOvalReady(
  video: HTMLVideoElement,
  ovalEl: HTMLElement,
): Promise<OvalFaceHit> {
  await loadFaceModels();
  const api = await faceApi();
  const result = await api.detectSingleFace(
    video,
    new api.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.32 }),
  );

  if (!result) {
    return { detected: false, ready: false, quality: 0, box: null };
  }

  const box: FaceBox = {
    x: result.box.x,
    y: result.box.y,
    width: result.box.width,
    height: result.box.height,
  };
  const guide = mapGuideRectToVideoPixels(video, ovalEl);
  const span = Math.min(guide.width, guide.height);
  const faceSpan = Math.max(box.width, box.height);
  // Larger oval: allow a bigger face fraction so the guide can fit a full head.
  const sizedOk = faceSpan >= span * 0.14 && faceSpan <= span * 1.35;
  const ready = centerInside(box, guide, 0.2) && sizedOk && result.score >= 0.28;

  return {
    detected: true,
    ready,
    quality: Number(result.score.toFixed(2)),
    box,
  };
}

const BLINK_SAMPLE_FRAMES = 10;
const BLINK_SAMPLE_GAP_MS = 55;
/** Phone cameras rarely hit classic EAR 0.2 — use softer absolute + relative drop. */
const BLINK_EAR_CLOSED = 0.29;
const BLINK_EAR_OPEN = 0.32;
const BLINK_EAR_DROP = 0.045;

function sleep(ms: number) {
  return new Promise<void>((resolve) => {
    window.setTimeout(resolve, ms);
  });
}

export type BlinkTracker = {
  ears: number[];
};

export function createBlinkTracker(): BlinkTracker {
  return { ears: [] };
}

export function resetBlinkTracker(tracker: BlinkTracker) {
  tracker.ears = [];
}

async function sampleEyeAspectRatio(video: HTMLVideoElement): Promise<number | null> {
  const api = await faceApi();
  const detector = new api.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.28 });
  const sample = await api.detectSingleFace(video, detector).withFaceLandmarks();
  if (!sample) return null;
  const left = eyeAspectRatio(sample.landmarks.getLeftEye());
  const right = eyeAspectRatio(sample.landmarks.getRightEye());
  // Mean is more stable than min on noisy phone landmarks.
  return (left + right) / 2;
}

function blinkFromEarSeries(ears: number[]): boolean {
  if (ears.length < 3) return false;
  const maxEar = Math.max(...ears);
  const minEar = Math.min(...ears);
  const closedHard = minEar <= BLINK_EAR_CLOSED;
  const openSeen = maxEar >= BLINK_EAR_OPEN;
  const dropped = maxEar - minEar >= BLINK_EAR_DROP;
  // Open then closed (or closed then open) within the window, or a clear EAR drop.
  return (closedHard && openSeen) || (openSeen && dropped) || minEar <= 0.25;
}

/**
 * One-frame blink progress for the auto liveness loop (does not block ~500ms+).
 * Accumulates EAR samples; returns true once a blink pattern is seen, then caller should reset.
 */
export async function tickBlinkTracker(video: HTMLVideoElement, tracker: BlinkTracker): Promise<boolean> {
  await loadFaceModels();
  const ear = await sampleEyeAspectRatio(video);
  if (ear == null) return false;
  tracker.ears.push(ear);
  // Keep ~2s of samples at ~280ms tick ≈ 8; allow up to 16 for denser loops.
  if (tracker.ears.length > 16) tracker.ears.shift();
  return blinkFromEarSeries(tracker.ears);
}

const TURN_SAMPLE_FRAMES = 5;
const TURN_SAMPLE_GAP_MS = 55;
/** Nose offset vs face center — lower = easier auto-detect on phones. */
const TURN_OFFSET_MIN = 0.028;
/**
 * Max |yaw| for the post-challenge match selfie.
 * Must stay below TURN_OFFSET_MIN so a turn pose cannot pass as frontal.
 */
const FRONTAL_YAW_MAX = 0.016;
/** Nose tip below eye line / face height — reject strong look-down / look-up. */
const FRONTAL_PITCH_MIN = 0.08;
const FRONTAL_PITCH_MAX = 0.42;

function yawOffset(landmarks: {
  getLeftEye: () => { x: number; y: number }[];
  getRightEye: () => { x: number; y: number }[];
  getNose: () => { x: number; y: number }[];
}, boxWidth: number): number {
  const leftEye = landmarks.getLeftEye();
  const rightEye = landmarks.getRightEye();
  const nose = landmarks.getNose();
  const leftCenter = averageX(leftEye);
  const rightCenter = averageX(rightEye);
  const faceCenter = (leftCenter + rightCenter) / 2;
  const noseX = nose[3]?.x ?? faceCenter;
  return (noseX - faceCenter) / Math.max(boxWidth, 1);
}

function pitchOffset(landmarks: {
  getLeftEye: () => { x: number; y: number }[];
  getRightEye: () => { x: number; y: number }[];
  getNose: () => { x: number; y: number }[];
}, boxHeight: number): number {
  const leftEye = landmarks.getLeftEye();
  const rightEye = landmarks.getRightEye();
  const nose = landmarks.getNose();
  const eyeY =
    (leftEye.reduce((sum, point) => sum + point.y, 0) / leftEye.length +
      rightEye.reduce((sum, point) => sum + point.y, 0) / rightEye.length) /
    2;
  const noseY = nose[3]?.y ?? eyeY;
  return (noseY - eyeY) / Math.max(boxHeight, 1);
}

export type FrontalOvalHit = {
  detected: boolean;
  /** Face centered in the oval with usable size/score. */
  inOval: boolean;
  /** Not turned left/right and not looking sharply up/down. */
  frontal: boolean;
  /** Ready to capture the face-match selfie. */
  ready: boolean;
  yaw: number;
  pitch: number;
  quality: number;
};

/**
 * Post-challenge gate: face in oval and facing the camera (invert of turn challenges).
 * Used so the match selfie is frontal, not a leftover turn pose.
 */
export async function faceFrontalInOvalReady(
  video: HTMLVideoElement,
  ovalEl: HTMLElement,
): Promise<FrontalOvalHit> {
  await loadFaceModels();
  const api = await faceApi();
  const result = await api
    .detectSingleFace(
      video,
      new api.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.32 }),
    )
    .withFaceLandmarks();

  if (!result) {
    return {
      detected: false,
      inOval: false,
      frontal: false,
      ready: false,
      yaw: 0,
      pitch: 0,
      quality: 0,
    };
  }

  const box: FaceBox = {
    x: result.detection.box.x,
    y: result.detection.box.y,
    width: result.detection.box.width,
    height: result.detection.box.height,
  };
  const guide = mapGuideRectToVideoPixels(video, ovalEl);
  const span = Math.min(guide.width, guide.height);
  const faceSpan = Math.max(box.width, box.height);
  const sizedOk = faceSpan >= span * 0.14 && faceSpan <= span * 1.35;
  const inOval = centerInside(box, guide, 0.2) && sizedOk && result.detection.score >= 0.28;
  const yaw = yawOffset(result.landmarks, box.width);
  const pitch = pitchOffset(result.landmarks, box.height);
  const frontal =
    Math.abs(yaw) <= FRONTAL_YAW_MAX &&
    pitch >= FRONTAL_PITCH_MIN &&
    pitch <= FRONTAL_PITCH_MAX;
  const ready = inOval && frontal;

  return {
    detected: true,
    inOval,
    frontal,
    ready,
    yaw: Number(yaw.toFixed(4)),
    pitch: Number(pitch.toFixed(4)),
    quality: Number(result.detection.score.toFixed(2)),
  };
}

/**
 * Challenge detection. Blink + turns sample over a short window for auto-mode.
 * Preview is CSS-mirrored; turn_left/right map to the user's mirrored selfie view
 * (raw camera coords are opposite of mirrored display).
 */
export async function detectChallenge(video: HTMLVideoElement, challenge: Challenge): Promise<boolean> {
  await loadFaceModels();
  const api = await faceApi();
  const detector = new api.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.28 });

  if (challenge === "blink") {
    const ears: number[] = [];
    for (let i = 0; i < BLINK_SAMPLE_FRAMES; i += 1) {
      const ear = await sampleEyeAspectRatio(video);
      if (ear != null) ears.push(ear);
      if (i < BLINK_SAMPLE_FRAMES - 1) await sleep(BLINK_SAMPLE_GAP_MS);
    }
    return blinkFromEarSeries(ears);
  }

  // Turns: sample several frames; use peak offset. Flip left/right for mirrored preview.
  let peakLeft = 0; // most negative offset in raw coords = user's left in mirror
  let peakRight = 0; // most positive offset in raw coords = user's right in mirror
  for (let i = 0; i < TURN_SAMPLE_FRAMES; i += 1) {
    const sample = await api.detectSingleFace(video, detector).withFaceLandmarks();
    if (sample) {
      const offset = yawOffset(sample.landmarks, sample.detection.box.width);
      if (offset < peakLeft) peakLeft = offset;
      if (offset > peakRight) peakRight = offset;
    }
    if (i < TURN_SAMPLE_FRAMES - 1) await sleep(TURN_SAMPLE_GAP_MS);
  }

  if (challenge === "turn_left") return peakLeft <= -TURN_OFFSET_MIN;
  return peakRight >= TURN_OFFSET_MIN;
}

export function euclideanDistance(first: number[], second: number[]) {
  if (first.length !== second.length) {
    throw new Error("Face descriptor sizes do not match.");
  }

  const total = first.reduce((sum, value, index) => sum + (value - second[index]) ** 2, 0);
  return Math.sqrt(total);
}

/**
 * Load a face descriptor from an image URL.
 * @param fallbackUrls Optional alternate images (e.g. full ID front frame) if the primary crop has no face.
 */
export async function descriptorFromUrl(
  url: string,
  fallbackUrls: string[] = [],
): Promise<FaceDescriptorResult> {
  const candidates = [url, ...fallbackUrls.filter((u) => u && u !== url)];
  let lastError: Error | null = null;

  for (const candidate of candidates) {
    try {
      return await descriptorFromUrlOnce(candidate);
    } catch (exception) {
      lastError = exception instanceof Error ? exception : new Error(String(exception));
      // Retry next candidate only when detection missed a face (not auth/load failures).
      if (!/no face was detected/i.test(lastError.message)) {
        throw lastError;
      }
    }
  }

  throw lastError ?? new Error("No face was detected on the School ID front image.");
}

async function descriptorFromUrlOnce(url: string): Promise<FaceDescriptorResult> {
  await loadFaceModels();
  const api = await faceApi();

  // Authenticated API photo routes need a Bearer fetch; <img> cannot send tokens.
  if (url.includes("/api/") && !url.includes("signature=")) {
    const { getAuthToken } = await import("@/auth/session");
    let response: Response;
    try {
      response = await fetch(url, {
        headers: {
          Accept: "image/*,application/octet-stream",
          Authorization: `Bearer ${getAuthToken() || ""}`,
        },
        credentials: "include",
      });
    } catch {
      throw new Error("Unable to load reference face photo.");
    }
    if (!response.ok) {
      throw new Error("Unable to load reference face photo.");
    }
    const blob = await response.blob();
    if (!blob.size) {
      throw new Error("Unable to load reference face photo.");
    }
    const objectUrl = URL.createObjectURL(blob);
    try {
      const image = await api.fetchImage(objectUrl);
      return descriptorFromImage(image);
    } finally {
      URL.revokeObjectURL(objectUrl);
    }
  }

  try {
    const image = await api.fetchImage(url);
    return descriptorFromImage(image);
  } catch (exception) {
    if (exception instanceof Error && /no face was detected/i.test(exception.message)) {
      throw exception;
    }
    throw new Error("Unable to load reference face photo.");
  }
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

/** Crop the live video to the guide overlay ROI, then JPEG-encode (drops outside margins). */
export function captureGuideVideoFrame(
  video: HTMLVideoElement,
  guideEl: HTMLElement,
  quality = 0.9,
  options?: { padRatio?: number; minLongEdge?: number },
): Promise<Blob> {
  if (video.readyState < 2) {
    return Promise.reject(new Error("Camera not ready."));
  }
  const guide = mapGuideRectToVideoPixels(video, guideEl);
  const vw = video.videoWidth || 960;
  const vh = video.videoHeight || 720;
  const padRatio = Math.max(0, Math.min(0.12, options?.padRatio ?? 0.04));
  const padX = guide.width * padRatio;
  const padY = guide.height * padRatio;
  const sx = Math.max(0, Math.floor(guide.x - padX));
  const sy = Math.max(0, Math.floor(guide.y - padY));
  const sw = Math.max(8, Math.min(vw - sx, Math.floor(guide.width + padX * 2)));
  const sh = Math.max(8, Math.min(vh - sy, Math.floor(guide.height + padY * 2)));
  const minLongEdge = options?.minLongEdge ?? 1400;
  const longEdge = Math.max(sw, sh);
  const scale = longEdge > 0 && longEdge < minLongEdge ? minLongEdge / longEdge : 1;
  const outW = Math.max(8, Math.round(sw * scale));
  const outH = Math.max(8, Math.round(sh * scale));
  const canvas = document.createElement("canvas");
  canvas.width = outW;
  canvas.height = outH;
  const ctx = canvas.getContext("2d");
  if (!ctx) return Promise.reject(new Error("Unable to capture camera frame."));
  ctx.imageSmoothingEnabled = true;
  ctx.imageSmoothingQuality = "high";
  ctx.drawImage(video, sx, sy, sw, sh, 0, 0, outW, outH);
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
