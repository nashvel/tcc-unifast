<script setup lang="ts">
import { ref } from "vue";
import { IconGitBranch } from "@tabler/icons-vue";
import PageHeader from "@/components/ui/PageHeader.vue";

type FlowNode = {
  id: string;
  label: string;
  type: "start" | "process" | "decision" | "end";
  x: number;
  y: number;
};

type FlowEdge = {
  from: string;
  to: string;
  label?: string;
};

const flows = ref([
  {
    id: "auth",
    name: "Authentication Flow",
    nodes: [
      { id: "start", label: "User visits login", type: "start", x: 200, y: 30 },
      { id: "login", label: "Login form", type: "process", x: 200, y: 100 },
      { id: "validate", label: "Credentials valid?", type: "decision", x: 200, y: 180 },
      { id: "token", label: "Issue Sanctum token", type: "process", x: 350, y: 180 },
      { id: "role", label: "Check role", type: "decision", x: 350, y: 260 },
      { id: "developer", label: "Developer dashboard", type: "end", x: 100, y: 340 },
      { id: "admin", label: "Admin dashboard", type: "end", x: 250, y: 340 },
      { id: "staff", label: "Staff dashboard", type: "end", x: 400, y: 340 },
      { id: "student", label: "Student dashboard", type: "end", x: 550, y: 340 },
      { id: "error", label: "Show error", type: "process", x: 50, y: 180 },
    ] as FlowNode[],
    edges: [
      { from: "start", to: "login" },
      { from: "login", to: "validate" },
      { from: "validate", to: "error", label: "No" },
      { from: "validate", to: "token", label: "Yes" },
      { from: "token", to: "role" },
      { from: "role", to: "developer", label: "developer" },
      { from: "role", to: "admin", label: "admin" },
      { from: "role", to: "staff", label: "staff" },
      { from: "role", to: "student", label: "student" },
      { from: "error", to: "login" },
    ] as FlowEdge[],
  },
  {
    id: "document",
    name: "Document Submission Flow",
    nodes: [
      { id: "start", label: "Student uploads doc", type: "start", x: 200, y: 30 },
      { id: "ocr", label: "OCR processing", type: "process", x: 200, y: 100 },
      { id: "face", label: "Face detection", type: "process", x: 200, y: 170 },
      { id: "quality", label: "Quality OK?", type: "decision", x: 200, y: 240 },
      { id: "auto", label: "Auto-approve", type: "process", x: 350, y: 240 },
      { id: "manual", label: "Manual review queue", type: "process", x: 50, y: 240 },
      { id: "staff", label: "Staff reviews", type: "process", x: 50, y: 310 },
      { id: "decision", label: "Approve?", type: "decision", x: 50, y: 380 },
      { id: "approved", label: "Approved", type: "end", x: 150, y: 450 },
      { id: "rejected", label: "Rejected", type: "end", x: -50, y: 450 },
      { id: "done", label: "Document saved", type: "end", x: 350, y: 310 },
    ] as FlowNode[],
    edges: [
      { from: "start", to: "ocr" },
      { from: "ocr", to: "face" },
      { from: "face", to: "quality" },
      { from: "quality", to: "auto", label: "High quality" },
      { from: "quality", to: "manual", label: "Low quality" },
      { from: "auto", to: "done" },
      { from: "manual", to: "staff" },
      { from: "staff", to: "decision" },
      { from: "decision", to: "approved", label: "Yes" },
      { from: "decision", to: "rejected", label: "No" },
      { from: "approved", to: "done" },
    ] as FlowEdge[],
  },
  {
    id: "kyc",
    name: "KYC Verification Flow",
    nodes: [
      { id: "start", label: "Student submits KYC", type: "start", x: 200, y: 30 },
      { id: "match", label: "Match with masterlist", type: "decision", x: 200, y: 110 },
      { id: "activate", label: "Activate account", type: "process", x: 350, y: 110 },
      { id: "mismatch", label: "Show mismatches", type: "process", x: 50, y: 110 },
      { id: "retry", label: "Student corrects", type: "process", x: 50, y: 190 },
      { id: "success", label: "KYC verified", type: "end", x: 350, y: 190 },
    ] as FlowNode[],
    edges: [
      { from: "start", to: "match" },
      { from: "match", to: "activate", label: "Match" },
      { from: "match", to: "mismatch", label: "Mismatch" },
      { from: "activate", to: "success" },
      { from: "mismatch", to: "retry" },
      { from: "retry", to: "start" },
    ] as FlowEdge[],
  },
]);

const selectedFlow = ref(flows.value[0]);

function getNodeColor(type: string) {
  switch (type) {
    case "start": return "fill-success text-white";
    case "process": return "fill-primary text-white";
    case "decision": return "fill-warning text-white";
    case "end": return "fill-info text-white";
    default: return "fill-surface text-text";
  }
}

function getNodeShape(type: string) {
  return type === "decision" ? "polygon" : type === "start" || type === "end" ? "ellipse" : "rect";
}
</script>

<template>
  <div>
    <PageHeader
      title="System Flow Charts"
      description="Visual representation of key system workflows."
    />

    <div class="mb-4 flex gap-2">
      <button
        v-for="flow in flows"
        :key="flow.id"
        :class="[
          'rounded-md border px-3 py-2 text-xs font-medium transition',
          selectedFlow.id === flow.id
            ? 'border-primary bg-primary text-white'
            : 'bg-surface text-text-muted hover:bg-surface-muted',
        ]"
        @click="selectedFlow = flow"
      >
        {{ flow.name }}
      </button>
    </div>

    <div class="rounded-lg border bg-surface p-6 overflow-x-auto">
      <svg
        :viewBox="`-50 0 500 500`"
        class="w-full max-w-2xl mx-auto"
        xmlns="http://www.w3.org/2000/svg"
      >
        <!-- Edges -->
        <g v-for="(edge, idx) in selectedFlow.edges" :key="idx">
          <line
            :x1="selectedFlow.nodes.find((n) => n.id === edge.from)!.x + 60"
            :y1="selectedFlow.nodes.find((n) => n.id === edge.from)!.y + 30"
            :x2="selectedFlow.nodes.find((n) => n.id === edge.to)!.x + 60"
            :y2="selectedFlow.nodes.find((n) => n.id === edge.to)!.y"
            stroke="#cbd5e1"
            stroke-width="2"
            marker-end="url(#arrow)"
          />
          <text
            v-if="edge.label"
            :x="(selectedFlow.nodes.find((n) => n.id === edge.from)!.x + selectedFlow.nodes.find((n) => n.id === edge.to)!.x) / 2 + 60"
            :y="(selectedFlow.nodes.find((n) => n.id === edge.from)!.y + selectedFlow.nodes.find((n) => n.id === edge.to)!.y) / 2 + 15"
            text-anchor="middle"
            class="fill-text-muted text-[10px]"
          >
            {{ edge.label }}
          </text>
        </g>

        <!-- Nodes -->
        <g v-for="node in selectedFlow.nodes" :key="node.id">
          <rect
            v-if="node.type === 'process'"
            :x="node.x + 10"
            :y="node.y"
            width="100"
            height="30"
            rx="4"
            :class="getNodeColor(node.type)"
          />
          <ellipse
            v-else-if="node.type === 'start' || node.type === 'end'"
            :cx="node.x + 60"
            :cy="node.y + 15"
            rx="50"
            ry="15"
            :class="getNodeColor(node.type)"
          />
          <polygon
            v-else
            :points="`${node.x + 60},${node.y} ${node.x + 120},${node.y + 15} ${node.x + 60},${node.y + 30} ${node.x},${node.y + 15}`"
            :class="getNodeColor(node.type)"
          />
          <text
            :x="node.x + 60"
            :y="node.y + 19"
            text-anchor="middle"
            class="fill-white text-[9px] font-medium pointer-events-none"
          >
            {{ node.label }}
          </text>
        </g>

        <defs>
          <marker id="arrow" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse">
            <path d="M 0 0 L 10 5 L 0 10 z" class="fill-slate-300" />
          </marker>
        </defs>
      </svg>
    </div>

    <div class="mt-4 grid gap-4 sm:grid-cols-4">
      <div class="flex items-center gap-2 text-xs">
        <span class="h-3 w-3 rounded bg-success" /> Start/End
      </div>
      <div class="flex items-center gap-2 text-xs">
        <span class="h-3 w-3 rounded bg-primary" /> Process
      </div>
      <div class="flex items-center gap-2 text-xs">
        <span class="h-3 w-3 rotate-45 bg-warning" /> Decision
      </div>
      <div class="flex items-center gap-2 text-xs">
        <span class="h-3 w-3 rounded bg-info" /> End State
      </div>
    </div>
  </div>
</template>
