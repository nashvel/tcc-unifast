import type { CapacitorConfig } from "@capacitor/cli";

const productionUrl = "https://tcc-unifast.nashvel.online";
const serverUrl = process.env.CAPACITOR_SERVER_URL?.trim() || productionUrl;
const parsedServerUrl = new URL(serverUrl);
const allowCleartext = process.env.CAPACITOR_ALLOW_CLEARTEXT === "true";

if (parsedServerUrl.protocol !== "https:" && !allowCleartext) {
  throw new Error(
    "CAPACITOR_SERVER_URL must use HTTPS. Set CAPACITOR_ALLOW_CLEARTEXT=true only for local development.",
  );
}

const config: CapacitorConfig = {
  appId: "ph.edu.tcc.unifast",
  appName: "TCC UniFAST",
  webDir: "www",
  server: {
    url: parsedServerUrl.toString(),
    cleartext: allowCleartext,
    errorPath: "offline.html",
  },
  android: {
    allowMixedContent: false,
  },
};

export default config;
