import * as faceapi from "face-api.js";

const modelUrl = "/models/face-api";
let loading: Promise<void> | null = null;

export type FaceDescriptorResult = {
  descriptor: number[];
  quality: number;
};

export type Challenge = "blink" | "turn_left" | "turn_right";

export async function loadFaceModels() {
  if (!loading) {
    loading = Promise.all([
      faceapi.nets.tinyFaceDetector.loadFromUri(modelUrl),
      faceapi.nets.faceLandmark68Net.loadFromUri(modelUrl),
      faceapi.nets.faceRecognitionNet.loadFromUri(modelUrl),
    ]).then(() => undefined);
  }

  return loading;
}

export async function descriptorFromImage(file: File): Promise<FaceDescriptorResult> {
  await loadFaceModels();
  const image = await faceapi.bufferToImage(file);
  const result = await faceapi
    .detectSingleFace(image, new faceapi.TinyFaceDetectorOptions({ inputSize: 416 }))
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
  const result = await faceapi
    .detectSingleFace(video, new faceapi.TinyFaceDetectorOptions({ inputSize: 416 }))
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
  const result = await faceapi
    .detectSingleFace(video, new faceapi.TinyFaceDetectorOptions({ inputSize: 224 }))
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

function eyeAspectRatio(points: faceapi.Point[]) {
  const vertical = distance(points[1], points[5]) + distance(points[2], points[4]);
  const horizontal = 2 * distance(points[0], points[3]);
  return horizontal === 0 ? 1 : vertical / horizontal;
}

function distance(first: faceapi.Point, second: faceapi.Point) {
  return Math.hypot(first.x - second.x, first.y - second.y);
}

function averageX(points: faceapi.Point[]) {
  return points.reduce((sum, point) => sum + point.x, 0) / points.length;
}
