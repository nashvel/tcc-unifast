import { createTccApp } from "./createApp";
import { installAuditLogger } from "@/services/audit";

const { app, router } = createTccApp();
installAuditLogger(router);

router.isReady().then(() => {
  app.mount("#app");
});
