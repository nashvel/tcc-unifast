import { defineComponent, ref, watch, nextTick, onBeforeUnmount, mergeProps, unref, withCtx, createVNode, openBlock, createBlock, toDisplayString, createCommentVNode, createTextVNode, useSSRContext } from "vue";
import { ssrRenderAttrs, ssrRenderComponent, ssrRenderAttr, ssrInterpolate, ssrIncludeBooleanAttr, ssrRenderClass } from "vue/server-renderer";
import { useRouter } from "vue-router";
import { IconId, IconChevronLeft, IconChevronRight, IconCheck, IconLock, IconCamera, IconShieldCheck, IconRefresh } from "@tabler/icons-vue";
import { _ as _sfc_main$2 } from "./AppDialog-CSs1wZpw.js";
import { _ as _sfc_main$1 } from "./PageHeader-DV7ufis9.js";
import { c as csrfToken } from "../ssr.js";
const sampleStudentId = "/build/assets/sample-student-id-HlbjhKv3.png";
const sampleStudentIdBack = "/build/assets/sample-student-id-back-DeNiPe_g.png";
const _sfc_main = /* @__PURE__ */ defineComponent({
  __name: "StudentVerification",
  __ssrInlineRender: true,
  setup(__props) {
    useRouter();
    const faceScanned = ref(false);
    const matching = ref(false);
    const matchScore = ref(null);
    const error = ref("");
    const idSide = ref("front");
    const verifyDialog = ref(false);
    const video = ref(null);
    const cameraReady = ref(false);
    const cameraStep = ref("id");
    const capturedIdBlob = ref(null);
    const capturedFaceBlob = ref(null);
    let cameraStream = null;
    watch(verifyDialog, async (open) => {
      if (open) {
        await nextTick();
        await startCamera();
      } else {
        stopCamera();
      }
    });
    onBeforeUnmount(stopCamera);
    async function startCamera() {
      error.value = "";
      cameraReady.value = false;
      stopCamera();
      try {
        if (!navigator.mediaDevices?.getUserMedia) {
          throw new Error("Camera access is not supported by this browser.");
        }
        cameraStream = await navigator.mediaDevices.getUserMedia({
          video: { facingMode: "user", width: { ideal: 960 }, height: { ideal: 720 } },
          audio: false
        });
        if (video.value) {
          video.value.srcObject = cameraStream;
          await video.value.play();
          cameraReady.value = true;
        }
      } catch (exception) {
        error.value = exception instanceof Error ? exception.message : "Unable to open camera. Please allow camera permission and try again.";
      }
    }
    function stopCamera() {
      cameraStream?.getTracks().forEach((track) => track.stop());
      cameraStream = null;
      cameraReady.value = false;
      if (video.value) video.value.srcObject = null;
    }
    async function captureCameraBlob() {
      if (!video.value || !cameraReady.value) {
        throw new Error("Camera is not ready yet.");
      }
      const canvas = document.createElement("canvas");
      canvas.width = video.value.videoWidth || 960;
      canvas.height = video.value.videoHeight || 720;
      const context = canvas.getContext("2d");
      if (!context) throw new Error("Unable to capture camera frame.");
      context.drawImage(video.value, 0, 0, canvas.width, canvas.height);
      return await new Promise((resolve, reject) => {
        canvas.toBlob((blob) => {
          if (blob) resolve(blob);
          else reject(new Error("Unable to encode camera frame."));
        }, "image/jpeg", 0.92);
      });
    }
    async function captureAndVerify() {
      matching.value = true;
      error.value = "";
      const body = new FormData();
      try {
        const idBlob = capturedIdBlob.value;
        const faceBlob = capturedFaceBlob.value;
        if (!idBlob || !faceBlob) {
          throw new Error("Capture the ID and face before submitting verification.");
        }
        body.append("student_id_document", idBlob, "live-id-capture.jpg");
        body.append("face_capture", faceBlob, "live-face-capture.jpg");
        const response = await fetch("/api/student/identity/face-verify", {
          method: "POST",
          headers: { "X-CSRF-TOKEN": csrfToken(), Accept: "application/json" },
          body
        });
        const payload = await response.json();
        if (!response.ok) {
          throw new Error(payload.message || "Face verification failed.");
        }
        faceScanned.value = Boolean(payload.matched);
        matchScore.value = Number(payload.score ?? 0);
        if (!payload.matched) {
          error.value = `Face match did not reach the ${payload.threshold}% threshold.`;
          return;
        }
        verifyDialog.value = false;
      } catch (exception) {
        error.value = exception instanceof Error ? exception.message : "Face verification failed.";
      } finally {
        matching.value = false;
      }
    }
    async function captureCurrentStep() {
      error.value = "";
      try {
        const blob = await captureCameraBlob();
        if (cameraStep.value === "id") {
          capturedIdBlob.value = blob;
          cameraStep.value = "face";
          return;
        }
        capturedFaceBlob.value = blob;
        await captureAndVerify();
      } catch (exception) {
        error.value = exception instanceof Error ? exception.message : "Unable to capture camera frame.";
      }
    }
    function retakeId() {
      capturedIdBlob.value = null;
      capturedFaceBlob.value = null;
      cameraStep.value = "id";
      error.value = "";
    }
    return (_ctx, _push, _parent, _attrs) => {
      _push(`<div${ssrRenderAttrs(mergeProps({ class: "space-y-5" }, _attrs))}>`);
      _push(ssrRenderComponent(_sfc_main$1, {
        title: "Identity Verification",
        description: "Review the official ID reference, then scan your physical ID and face live through the camera."
      }, null, _parent));
      _push(`<section class="space-y-4"><article class="overflow-hidden rounded-2xl border bg-surface shadow-sm"><div class="border-b bg-surface-muted px-4 py-3"><p class="flex items-center gap-2 text-sm font-semibold">`);
      _push(ssrRenderComponent(unref(IconId), {
        size: 17,
        class: "text-primary"
      }, null, _parent));
      _push(` Admin reference ID sample </p><p class="mt-1 text-xs text-text-muted"> Review the front first, then click Next to check the back side. </p></div><div class="grid gap-5 bg-[radial-gradient(circle_at_top,_rgba(147,25,45,0.10),transparent_55%)] p-5 lg:grid-cols-[300px_1fr]"><figure class="mx-auto w-full max-w-[260px] rounded-2xl border bg-white p-3 shadow-xl"><img${ssrRenderAttr("src", idSide.value === "front" ? unref(sampleStudentId) : unref(sampleStudentIdBack))}${ssrRenderAttr("alt", idSide.value === "front" ? "Sample TCC student ID front reference" : "Sample TCC student ID back reference")} class="mx-auto max-h-[430px] w-full object-contain"><figcaption class="mt-3 rounded-lg bg-surface-muted px-3 py-2 text-center text-xs font-semibold">${ssrInterpolate(idSide.value === "front" ? "Front - face reference" : "Back - QR and emergency details")}</figcaption><div class="mt-3 grid grid-cols-2 gap-2"><button class="inline-flex items-center justify-center gap-1 rounded-md border px-3 py-2 text-xs disabled:opacity-50"${ssrIncludeBooleanAttr(idSide.value === "front") ? " disabled" : ""}>`);
      _push(ssrRenderComponent(unref(IconChevronLeft), { size: 13 }, null, _parent));
      _push(` Front </button><button class="inline-flex items-center justify-center gap-1 rounded-md border px-3 py-2 text-xs disabled:opacity-50"${ssrIncludeBooleanAttr(idSide.value === "back") ? " disabled" : ""}> Back `);
      _push(ssrRenderComponent(unref(IconChevronRight), { size: 13 }, null, _parent));
      _push(`</button></div></figure><div class="flex flex-col justify-center"><span class="inline-flex w-fit items-center gap-1.5 rounded-full bg-success-soft px-3 py-1 text-xs font-semibold text-success">`);
      _push(ssrRenderComponent(unref(IconCheck), { size: 14 }, null, _parent));
      _push(` Reference sample ready </span><h2 class="mt-4 text-2xl font-semibold tracking-tight">${ssrInterpolate(idSide.value === "front" ? "Front side is used for face matching." : "Back side supports QR and validity checks.")}</h2><p class="mt-2 max-w-xl text-sm text-text-muted">${ssrInterpolate(idSide.value === "front" ? "The live face scan compares against the front ID photo. Click Next to inspect the back side." : "The back side contains school year, QR, emergency contact, and validity details. Click Front to return to face reference.")}</p><div class="mt-5 flex max-w-md rounded-full border bg-surface p-1 text-xs"><button class="${ssrRenderClass([
        "flex-1 rounded-full px-3 py-1.5",
        idSide.value === "front" ? "bg-primary text-white" : "text-text-muted"
      ])}"> 1. Front </button><button class="${ssrRenderClass([
        "flex-1 rounded-full px-3 py-1.5",
        idSide.value === "back" ? "bg-primary text-white" : "text-text-muted"
      ])}"> 2. Back </button></div><div class="mt-6 rounded-xl border bg-surface p-4"><div class="flex items-start gap-3">`);
      _push(ssrRenderComponent(unref(IconLock), {
        size: 18,
        class: "mt-0.5 shrink-0 text-warning"
      }, null, _parent));
      _push(`<div><p class="text-sm font-semibold">Ready for live verification</p><p class="mt-1 text-xs text-text-muted"> The next step opens the camera, traces the ID mask first, then captures your face. </p></div></div><button class="mt-4 inline-flex h-10 items-center gap-2 rounded-md bg-primary px-4 text-sm font-medium text-white">`);
      _push(ssrRenderComponent(unref(IconCamera), { size: 16 }, null, _parent));
      _push(` Get started verification </button></div></div></div></article>`);
      if (faceScanned.value && matchScore.value !== null) {
        _push(`<div class="rounded-lg border border-success/30 bg-success-soft p-3"><p class="flex items-center gap-2 text-sm font-semibold text-success">`);
        _push(ssrRenderComponent(unref(IconCheck), { size: 16 }, null, _parent));
        _push(` Face match passed </p><p class="mt-1 text-xs text-text-muted"> Face API confidence score: ${ssrInterpolate(matchScore.value.toFixed(1))}%. </p><button class="mt-3 h-9 rounded-md bg-primary px-4 text-xs font-medium text-white"> Unlock dashboard and menus </button></div>`);
      } else {
        _push(`<!---->`);
      }
      if (error.value && !verifyDialog.value) {
        _push(`<p class="rounded-md border border-danger/30 bg-danger-soft p-3 text-xs text-danger">${ssrInterpolate(error.value)}</p>`);
      } else {
        _push(`<!---->`);
      }
      _push(`<article class="rounded-xl border bg-surface p-5"><h2 class="flex items-center gap-2 text-sm font-semibold">`);
      _push(ssrRenderComponent(unref(IconShieldCheck), { size: 17 }, null, _parent));
      _push(` Verification flow </h2><ol class="mt-3 grid gap-3 text-xs text-text-muted md:grid-cols-4"><li class="flex gap-2 rounded-lg bg-surface-muted p-3">`);
      _push(ssrRenderComponent(unref(IconId), {
        size: 15,
        class: "text-primary"
      }, null, _parent));
      _push(` Review front and back ID reference. </li><li class="flex gap-2 rounded-lg bg-surface-muted p-3">`);
      _push(ssrRenderComponent(unref(IconCamera), {
        size: 15,
        class: "text-primary"
      }, null, _parent));
      _push(` Scan physical ID using the mask. </li><li class="flex gap-2 rounded-lg bg-surface-muted p-3">`);
      _push(ssrRenderComponent(unref(IconCheck), {
        size: 15,
        class: "text-primary"
      }, null, _parent));
      _push(` Capture live face and upload both. </li><li class="flex gap-2 rounded-lg bg-surface-muted p-3">`);
      _push(ssrRenderComponent(unref(IconShieldCheck), {
        size: 15,
        class: "text-primary"
      }, null, _parent));
      _push(` If matched, document upload unlocks. </li></ol></article></section>`);
      _push(ssrRenderComponent(_sfc_main$2, {
        modelValue: verifyDialog.value,
        "onUpdate:modelValue": ($event) => verifyDialog.value = $event,
        title: "Live identity verification",
        description: cameraStep.value === "id" ? "Place your physical student ID inside the traced card mask, then capture it live." : "Now position your face inside the oval mask, then capture and verify.",
        size: "lg"
      }, {
        footer: withCtx(({ close }, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<button class="rounded-md border px-4 py-2 text-xs"${_scopeId}>Cancel</button><button class="rounded-md bg-primary px-4 py-2 text-xs text-white disabled:opacity-50"${ssrIncludeBooleanAttr(matching.value || !cameraReady.value) ? " disabled" : ""}${_scopeId}>${ssrInterpolate(matching.value ? "Verifying..." : cameraStep.value === "id" ? "Capture ID and continue" : "Capture face and verify")}</button>`);
          } else {
            return [
              createVNode("button", {
                class: "rounded-md border px-4 py-2 text-xs",
                onClick: close
              }, "Cancel", 8, ["onClick"]),
              createVNode("button", {
                class: "rounded-md bg-primary px-4 py-2 text-xs text-white disabled:opacity-50",
                disabled: matching.value || !cameraReady.value,
                onClick: captureCurrentStep
              }, toDisplayString(matching.value ? "Verifying..." : cameraStep.value === "id" ? "Capture ID and continue" : "Capture face and verify"), 9, ["disabled"])
            ];
          }
        }),
        default: withCtx((_, _push2, _parent2, _scopeId) => {
          if (_push2) {
            _push2(`<div class="space-y-4"${_scopeId}><div class="grid grid-cols-2 gap-2 text-xs"${_scopeId}><div class="${ssrRenderClass([
              "rounded-lg border p-2 text-center",
              capturedIdBlob.value ? "border-success bg-success-soft text-success" : cameraStep.value === "id" ? "border-primary bg-primary-soft text-primary" : "bg-surface-muted text-text-muted"
            ])}"${_scopeId}> 1. Live ID scan </div><div class="${ssrRenderClass([
              "rounded-lg border p-2 text-center",
              capturedFaceBlob.value ? "border-success bg-success-soft text-success" : cameraStep.value === "face" ? "border-primary bg-primary-soft text-primary" : "bg-surface-muted text-text-muted"
            ])}"${_scopeId}> 2. Live face scan </div></div><div class="relative overflow-hidden rounded-xl border bg-black"${_scopeId}><video class="aspect-video w-full object-cover" autoplay playsinline muted${_scopeId}></video><div class="pointer-events-none absolute inset-0 bg-black/10"${_scopeId}>`);
            if (cameraStep.value === "id") {
              _push2(`<div class="absolute left-1/2 top-1/2 h-[72%] w-[46%] -translate-x-1/2 -translate-y-1/2 rounded-[1.45rem] border-4 border-primary shadow-[0_0_0_999px_rgba(0,0,0,0.36)]"${_scopeId}><span class="absolute -top-8 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-full bg-primary px-3 py-1 text-xs font-semibold text-white"${_scopeId}> Align ID card here </span><span class="absolute left-1/2 top-4 h-5 w-20 -translate-x-1/2 rounded-full border-2 border-white/80"${_scopeId}></span><span class="absolute bottom-4 left-4 right-4 h-10 rounded border-2 border-dashed border-white/70"${_scopeId}></span><span class="absolute left-4 top-4 h-8 w-8 rounded-tl-xl border-l-4 border-t-4 border-white"${_scopeId}></span><span class="absolute right-4 top-4 h-8 w-8 rounded-tr-xl border-r-4 border-t-4 border-white"${_scopeId}></span><span class="absolute bottom-4 left-4 h-8 w-8 rounded-bl-xl border-b-4 border-l-4 border-white"${_scopeId}></span><span class="absolute bottom-4 right-4 h-8 w-8 rounded-br-xl border-b-4 border-r-4 border-white"${_scopeId}></span></div>`);
            } else {
              _push2(`<div class="absolute left-1/2 top-1/2 h-[72%] w-[42%] -translate-x-1/2 -translate-y-1/2 rounded-[999px] border-4 border-primary shadow-[0_0_0_999px_rgba(0,0,0,0.36)]"${_scopeId}><span class="absolute -top-8 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-full bg-primary px-3 py-1 text-xs font-semibold text-white"${_scopeId}> Center face here </span><span class="absolute left-1/2 top-[38%] h-3 w-3 -translate-x-1/2 rounded-full bg-white/80"${_scopeId}></span><span class="absolute left-[34%] top-[28%] h-3 w-3 rounded-full bg-white/80"${_scopeId}></span><span class="absolute right-[34%] top-[28%] h-3 w-3 rounded-full bg-white/80"${_scopeId}></span></div>`);
            }
            _push2(`</div></div><div class="flex flex-wrap items-center justify-between gap-2"${_scopeId}><p class="text-xs text-text-muted"${_scopeId}>${ssrInterpolate(cameraReady.value ? cameraStep.value === "id" ? "Camera ready. Align the whole ID card inside the mask." : "Camera ready. Keep your face centered and well lit." : "Opening camera...")}</p><div class="flex gap-2"${_scopeId}>`);
            if (capturedIdBlob.value) {
              _push2(`<button class="inline-flex items-center gap-1 rounded-md border px-3 py-2 text-xs"${_scopeId}> Retake ID </button>`);
            } else {
              _push2(`<!---->`);
            }
            _push2(`<button class="inline-flex items-center gap-1 rounded-md border px-3 py-2 text-xs"${_scopeId}>`);
            _push2(ssrRenderComponent(unref(IconRefresh), { size: 14 }, null, _parent2, _scopeId));
            _push2(` Restart camera </button></div></div>`);
            if (error.value) {
              _push2(`<p class="rounded-md border border-danger/30 bg-danger-soft p-3 text-xs text-danger"${_scopeId}>${ssrInterpolate(error.value)}</p>`);
            } else {
              _push2(`<!---->`);
            }
            _push2(`</div>`);
          } else {
            return [
              createVNode("div", { class: "space-y-4" }, [
                createVNode("div", { class: "grid grid-cols-2 gap-2 text-xs" }, [
                  createVNode("div", {
                    class: [
                      "rounded-lg border p-2 text-center",
                      capturedIdBlob.value ? "border-success bg-success-soft text-success" : cameraStep.value === "id" ? "border-primary bg-primary-soft text-primary" : "bg-surface-muted text-text-muted"
                    ]
                  }, " 1. Live ID scan ", 2),
                  createVNode("div", {
                    class: [
                      "rounded-lg border p-2 text-center",
                      capturedFaceBlob.value ? "border-success bg-success-soft text-success" : cameraStep.value === "face" ? "border-primary bg-primary-soft text-primary" : "bg-surface-muted text-text-muted"
                    ]
                  }, " 2. Live face scan ", 2)
                ]),
                createVNode("div", { class: "relative overflow-hidden rounded-xl border bg-black" }, [
                  createVNode("video", {
                    ref_key: "video",
                    ref: video,
                    class: "aspect-video w-full object-cover",
                    autoplay: "",
                    playsinline: "",
                    muted: ""
                  }, null, 512),
                  createVNode("div", { class: "pointer-events-none absolute inset-0 bg-black/10" }, [
                    cameraStep.value === "id" ? (openBlock(), createBlock("div", {
                      key: 0,
                      class: "absolute left-1/2 top-1/2 h-[72%] w-[46%] -translate-x-1/2 -translate-y-1/2 rounded-[1.45rem] border-4 border-primary shadow-[0_0_0_999px_rgba(0,0,0,0.36)]"
                    }, [
                      createVNode("span", { class: "absolute -top-8 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-full bg-primary px-3 py-1 text-xs font-semibold text-white" }, " Align ID card here "),
                      createVNode("span", { class: "absolute left-1/2 top-4 h-5 w-20 -translate-x-1/2 rounded-full border-2 border-white/80" }),
                      createVNode("span", { class: "absolute bottom-4 left-4 right-4 h-10 rounded border-2 border-dashed border-white/70" }),
                      createVNode("span", { class: "absolute left-4 top-4 h-8 w-8 rounded-tl-xl border-l-4 border-t-4 border-white" }),
                      createVNode("span", { class: "absolute right-4 top-4 h-8 w-8 rounded-tr-xl border-r-4 border-t-4 border-white" }),
                      createVNode("span", { class: "absolute bottom-4 left-4 h-8 w-8 rounded-bl-xl border-b-4 border-l-4 border-white" }),
                      createVNode("span", { class: "absolute bottom-4 right-4 h-8 w-8 rounded-br-xl border-b-4 border-r-4 border-white" })
                    ])) : (openBlock(), createBlock("div", {
                      key: 1,
                      class: "absolute left-1/2 top-1/2 h-[72%] w-[42%] -translate-x-1/2 -translate-y-1/2 rounded-[999px] border-4 border-primary shadow-[0_0_0_999px_rgba(0,0,0,0.36)]"
                    }, [
                      createVNode("span", { class: "absolute -top-8 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-full bg-primary px-3 py-1 text-xs font-semibold text-white" }, " Center face here "),
                      createVNode("span", { class: "absolute left-1/2 top-[38%] h-3 w-3 -translate-x-1/2 rounded-full bg-white/80" }),
                      createVNode("span", { class: "absolute left-[34%] top-[28%] h-3 w-3 rounded-full bg-white/80" }),
                      createVNode("span", { class: "absolute right-[34%] top-[28%] h-3 w-3 rounded-full bg-white/80" })
                    ]))
                  ])
                ]),
                createVNode("div", { class: "flex flex-wrap items-center justify-between gap-2" }, [
                  createVNode("p", { class: "text-xs text-text-muted" }, toDisplayString(cameraReady.value ? cameraStep.value === "id" ? "Camera ready. Align the whole ID card inside the mask." : "Camera ready. Keep your face centered and well lit." : "Opening camera..."), 1),
                  createVNode("div", { class: "flex gap-2" }, [
                    capturedIdBlob.value ? (openBlock(), createBlock("button", {
                      key: 0,
                      class: "inline-flex items-center gap-1 rounded-md border px-3 py-2 text-xs",
                      onClick: retakeId
                    }, " Retake ID ")) : createCommentVNode("", true),
                    createVNode("button", {
                      class: "inline-flex items-center gap-1 rounded-md border px-3 py-2 text-xs",
                      onClick: startCamera
                    }, [
                      createVNode(unref(IconRefresh), { size: 14 }),
                      createTextVNode(" Restart camera ")
                    ])
                  ])
                ]),
                error.value ? (openBlock(), createBlock("p", {
                  key: 0,
                  class: "rounded-md border border-danger/30 bg-danger-soft p-3 text-xs text-danger"
                }, toDisplayString(error.value), 1)) : createCommentVNode("", true)
              ])
            ];
          }
        }),
        _: 1
      }, _parent));
      _push(`</div>`);
    };
  }
});
const _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
  const ssrContext = useSSRContext();
  (ssrContext.modules || (ssrContext.modules = /* @__PURE__ */ new Set())).add("resources/js/modules/verification/StudentVerification.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : void 0;
};
export {
  _sfc_main as default
};
