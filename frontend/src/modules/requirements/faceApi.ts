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

export async function descriptorFromImage(file: File): Promise<FaceDescriptorResult> {
  await loadFaceModels();
  const api = await faceApi();
  const image = await api.bufferToImage(file);
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
