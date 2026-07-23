import { onMounted, onUnmounted, ref, shallowRef } from "vue";
import type Echo from "laravel-echo";
import { authSession } from "@/auth/session";

type EchoInstance = Echo<"reverb" | "pusher">;

const echoRef = shallowRef<EchoInstance | null>(null);
const connected = ref(false);
const configured = ref(false);
let connecting: Promise<EchoInstance | null> | null = null;

function broadcastConfig() {
  const key = import.meta.env.VITE_REVERB_APP_KEY || import.meta.env.VITE_PUSHER_APP_KEY;
  if (!key) return null;

  const useReverb = Boolean(import.meta.env.VITE_REVERB_APP_KEY);
  return {
    key: String(key),
    useReverb,
    host: String(
      import.meta.env.VITE_REVERB_HOST ||
        import.meta.env.VITE_PUSHER_HOST ||
        window.location.hostname,
    ),
    port: Number(import.meta.env.VITE_REVERB_PORT || import.meta.env.VITE_PUSHER_PORT || 8080),
    scheme: String(import.meta.env.VITE_REVERB_SCHEME || import.meta.env.VITE_PUSHER_SCHEME || "http"),
    cluster: String(import.meta.env.VITE_PUSHER_APP_CLUSTER || "mt1"),
  };
}

export async function ensureEcho(): Promise<EchoInstance | null> {
  if (import.meta.env.SSR) return null;
  if (echoRef.value) return echoRef.value;
  if (connecting) return connecting;

  const config = broadcastConfig();
  configured.value = Boolean(config);
  if (!config) return null;

  connecting = (async () => {
    const [{ default: EchoCtor }, { default: Pusher }] = await Promise.all([
      import("laravel-echo"),
      import("pusher-js"),
    ]);
    (window as unknown as { Pusher: typeof Pusher }).Pusher = Pusher;

    const echo = new EchoCtor({
      broadcaster: config.useReverb ? "reverb" : "pusher",
      key: config.key,
      wsHost: config.host,
      wsPort: config.port,
      wssPort: config.port,
      forceTLS: config.scheme === "https",
      enabledTransports: ["ws", "wss"],
      cluster: config.cluster,
      authEndpoint: "/broadcasting/auth",
      auth: {
        headers: {
          "X-CSRF-TOKEN":
            document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "",
          Accept: "application/json",
        },
      },
    }) as EchoInstance;

    echoRef.value = echo;
    connected.value = true;
    return echo;
  })().finally(() => {
    connecting = null;
  });

  return connecting;
}

export function useEcho() {
  onMounted(() => {
    void ensureEcho();
  });

  return { echo: echoRef, connected, configured, ensureEcho };
}

export type NotificationPayload = {
  id: number;
  title: string;
  body: string;
  type: string;
  read: boolean;
  time: string;
};

/**
 * Subscribe to the authenticated user's private notification channel.
 * Returns an unsubscribe function.
 */
export async function subscribeUserNotifications(
  onNotification: (payload: NotificationPayload) => void,
): Promise<() => void> {
  const userId = authSession.user?.id;
  if (!userId) return () => undefined;

  const echo = await ensureEcho();
  if (!echo) return () => undefined;

  const channelName = `App.Models.User.${userId}`;
  const channel = echo.private(channelName);
  channel.listen(".notification.created", (payload: NotificationPayload) => {
    onNotification(payload);
  });

  return () => {
    echo.leave(channelName);
  };
}

export function useNotificationChannel(
  onNotification: (payload: NotificationPayload) => void,
) {
  let leave: (() => void) | undefined;

  onMounted(() => {
    void subscribeUserNotifications(onNotification).then((fn) => {
      leave = fn;
    });
  });

  onUnmounted(() => {
    leave?.();
  });
}
