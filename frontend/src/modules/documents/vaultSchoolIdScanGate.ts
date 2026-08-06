/** Session gate: documents page must accept precheck/consent before opening live Slot 1 scan. */

const STORAGE_KEY = "vault_school_id_scan_ready";
const MAX_AGE_MS = 2 * 60 * 60 * 1000; // 2 hours

type GatePayload = {
  at: number;
};

export function markVaultSchoolIdScanReady(): void {
  const payload: GatePayload = { at: Date.now() };
  try {
    sessionStorage.setItem(STORAGE_KEY, JSON.stringify(payload));
  } catch {
    // Private mode / quota — scan page will redirect back.
  }
}

export function isVaultSchoolIdScanReady(): boolean {
  try {
    const raw = sessionStorage.getItem(STORAGE_KEY);
    if (!raw) return false;
    const parsed = JSON.parse(raw) as GatePayload;
    if (!parsed?.at || typeof parsed.at !== "number") return false;
    if (Date.now() - parsed.at > MAX_AGE_MS) {
      sessionStorage.removeItem(STORAGE_KEY);
      return false;
    }
    return true;
  } catch {
    return false;
  }
}

export function clearVaultSchoolIdScanReady(): void {
  try {
    sessionStorage.removeItem(STORAGE_KEY);
  } catch {
    // ignore
  }
}
