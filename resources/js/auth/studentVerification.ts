import { reactive } from "vue";

const key = "tcc_student_identity_verified";
const hasStorage = () => typeof localStorage !== "undefined";

export const studentVerification = reactive({
  verified: hasStorage() && localStorage.getItem(key) === "1",
});

export function markStudentVerified() {
  studentVerification.verified = true;
  if (hasStorage()) localStorage.setItem(key, "1");
}

export function resetStudentVerification() {
  studentVerification.verified = false;
  if (hasStorage()) localStorage.removeItem(key);
}
