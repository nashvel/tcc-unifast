type FaceApiModule = typeof import("face-api.js");

const modelUrl = "/models/face-api";
let faceapiPromise: Promise<FaceApiModule> | null = null;
let loading: Promise<void> | null = null;

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

export type Challenge = "blink" | "turn_left" | "turn_right";

export async function loadFaceModels() {
  if (!loading) {
    loading = (async () => {
      const api = await faceApi();
      await Promise.all([
        api.nets.tinyFaceDetector.loadFromUri(modelUrl),
        api.nets.faceLandmark68Net.loadFromUri(modelUrl),
        api.nets.faceRecognitionNet.loadFromUri(modelUrl),
      ]);
    })();
  }

  return loading;
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
