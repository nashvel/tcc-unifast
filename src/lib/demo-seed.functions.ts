import { createServerFn } from "@tanstack/react-start";

export const DEMO_USERS = [
  { role: "admin" as const, email: "admin@unifast.gov.ph", password: "DemoAdmin123!", fullName: "System Administrator" },
  { role: "head" as const, email: "r.santos@unifast.gov.ph", password: "DemoHead123!", fullName: "Ricardo Santos" },
  { role: "staff" as const, email: "j.cruz@unifast.gov.ph", password: "DemoStaff123!", fullName: "Jessica Cruz" },
  { role: "student" as const, email: "mc.delacruz@plm.edu.ph", password: "DemoStudent123!", fullName: "Maria Clara Dela Cruz" },
];

/**
 * Idempotently provisions the four demo accounts, profiles, roles,
 * and a small slice of sample masterlist / documents / notifications.
 * Public on purpose — this is a prototype demo seeder.
 */
export const seedDemo = createServerFn({ method: "POST" }).handler(async () => {
  const { supabaseAdmin } = await import("@/integrations/supabase/client.server");

  // 1. Ensure demo users + roles
  const userMap: Record<string, string> = {};
  for (const u of DEMO_USERS) {
    let userId: string | undefined;
    const { data: existing } = await supabaseAdmin.auth.admin.listUsers({ page: 1, perPage: 200 });
    const found = existing?.users.find((x) => x.email?.toLowerCase() === u.email.toLowerCase());
    if (found) {
      userId = found.id;
    } else {
      const { data, error } = await supabaseAdmin.auth.admin.createUser({
        email: u.email,
        password: u.password,
        email_confirm: true,
        user_metadata: { full_name: u.fullName },
      });
      if (error || !data.user) throw new Error(`Failed to create ${u.email}: ${error?.message}`);
      userId = data.user.id;
    }
    userMap[u.email] = userId!;

    // Profile (full_name + role-appropriate fields)
    await supabaseAdmin.from("profiles").upsert({
      id: userId!,
      full_name: u.fullName,
      email: u.email,
      ...(u.role === "student"
        ? { student_number: "2024-10000", university: "Pamantasan ng Lungsod ng Maynila", program: "BS Computer Science", year_level: 2, contact: "+639171234567", birthdate: "2003-05-14" }
        : {}),
    });

    // Roles: wipe defaults, set the demo role
    await supabaseAdmin.from("user_roles").delete().eq("user_id", userId!);
    await supabaseAdmin.from("user_roles").insert({ user_id: userId!, role: u.role });
  }

  // 2. Seed masterlist (idempotent by student_number)
  const masterRows = [
    { student_number: "2024-10000", first_name: "Maria Clara", last_name: "Dela Cruz", middle_name: "Reyes", birthdate: "2003-05-14", email: "mc.delacruz@plm.edu.ph", contact: "+639171234567", university: "Pamantasan ng Lungsod ng Maynila", program: "BS Computer Science", year_level: 2, batch: "AY 2024-2025 Sem 1", account_status: "active" },
    { student_number: "2024-10001", first_name: "Juan Miguel", last_name: "Santos", birthdate: "2004-02-20", email: "jm.santos@up.edu.ph", contact: "+639181234568", university: "University of the Philippines Diliman", program: "BS Civil Engineering", year_level: 1, batch: "AY 2024-2025 Sem 1", account_status: "pending_activation" },
    { student_number: "2024-10002", first_name: "Andrea Nicole", last_name: "Reyes", birthdate: "2002-11-03", email: "an.reyes@pup.edu.ph", contact: "+639192234569", university: "Polytechnic University of the Philippines", program: "BS Accountancy", year_level: 3, batch: "AY 2024-2025 Sem 1", account_status: "active" },
    { student_number: "2024-10003", first_name: "Joshua", last_name: "Tan", birthdate: "2003-07-22", email: "j.tan@dlsud.edu.ph", contact: "+639172234570", university: "De La Salle University - Dasmariñas", program: "BS Information Technology", year_level: 2, batch: "AY 2024-2025 Sem 1", account_status: "inactive" },
    { student_number: "2024-10004", first_name: "Patricia Mae", last_name: "Lim", birthdate: "2004-09-30", email: "pm.lim@adamson.edu.ph", contact: "+639183234571", university: "Adamson University", program: "BS Chemical Engineering", year_level: 1, batch: "AY 2024-2025 Sem 1", account_status: "pending_activation" },
    { student_number: "2024-10005", first_name: "Marco", last_name: "Villanueva", birthdate: "2002-04-17", email: "m.villanueva@feu.edu.ph", contact: "+639174234572", university: "Far Eastern University", program: "BS Architecture", year_level: 3, batch: "AY 2024-2025 Sem 1", account_status: "active" },
    { student_number: "2024-10006", first_name: "Bea", last_name: "Mendoza", birthdate: "2004-12-05", email: "b.mendoza@ust.edu.ph", contact: "+639195234573", university: "University of Santo Tomas", program: "BS Nursing", year_level: 1, batch: "AY 2024-2025 Sem 1", account_status: "inactive" },
    { student_number: "2024-10007", first_name: "Daniel", last_name: "Garcia", birthdate: "2003-03-11", email: "d.garcia@mapua.edu.ph", contact: "+639176234574", university: "Mapua University", program: "BS Electronics Engineering", year_level: 2, batch: "AY 2024-2025 Sem 1", account_status: "active" },
  ];
  const { data: existingMaster } = await supabaseAdmin.from("masterlist").select("student_number");
  const seenSn = new Set((existingMaster ?? []).map((r) => r.student_number));
  const newRows = masterRows.filter((r) => !seenSn.has(r.student_number));
  if (newRows.length) await supabaseAdmin.from("masterlist").insert(newRows);

  // 3. Seed documents (owned by student) — only if student has none yet
  const studentId = userMap["mc.delacruz@plm.edu.ph"];
  const { count: docCount } = await supabaseAdmin.from("documents").select("*", { count: "exact", head: true }).eq("owner_id", studentId);
  if (!docCount) {
    const sampleDocs = [
      { owner_id: studentId, grantee_name: "Maria Clara Dela Cruz", student_number: "2024-10000", type: "Certificate of Registration", filename: "COR_delacruz_sem1.pdf", status: "pending", risk_score: 18, ocr: { name: "Maria Clara Dela Cruz", studentNo: "2024-10000", units: "21" }, exif: { device: "iPhone 13", takenAt: "2026-06-18 09:10", gps: "14.5995, 120.9842" } },
      { owner_id: studentId, grantee_name: "Maria Clara Dela Cruz", student_number: "2024-10000", type: "Grade Report / TOR", filename: "TOR_delacruz.pdf", status: "approved", risk_score: 8, remarks: "All grades match academic record.", ocr: { name: "Maria Clara Dela Cruz", gwa: "1.62" }, exif: { device: "Scanner", takenAt: "2026-06-17 16:30" } },
      { owner_id: studentId, grantee_name: "Maria Clara Dela Cruz", student_number: "2024-10000", type: "Valid Government ID", filename: "ID_delacruz.jpg", status: "approved", risk_score: 12, ocr: { name: "Maria Clara Dela Cruz", idNo: "1234-5678-9012" } },
      { owner_id: studentId, grantee_name: "Maria Clara Dela Cruz", student_number: "2024-10000", type: "Birth Certificate (PSA)", filename: "PSA_delacruz.pdf", status: "resubmission", risk_score: 45, remarks: "Document is blurred. Please re-upload a clear copy." },
      // Other grantees (no owner — staff-only review queue)
      { owner_id: null, grantee_name: "Andrea Nicole Reyes", student_number: "2024-10002", type: "Valid Government ID", filename: "ID_reyes.jpg", status: "suspicious", risk_score: 82, remarks: "Possible image tampering detected.", ocr: { name: "Andrea N. Reyes" }, exif: { device: "Photoshop 25.0" } },
      { owner_id: null, grantee_name: "Marco Villanueva", student_number: "2024-10005", type: "Certificate of Indigency", filename: "indigency_villanueva.pdf", status: "rejected", risk_score: 60, remarks: "Signature does not match barangay records." },
      { owner_id: null, grantee_name: "Bea Mendoza", student_number: "2024-10006", type: "Certificate of Registration", filename: "COR_mendoza.pdf", status: "approved", risk_score: 5 },
      { owner_id: null, grantee_name: "Daniel Garcia", student_number: "2024-10007", type: "Grade Report / TOR", filename: "TOR_garcia.pdf", status: "pending", risk_score: 30 },
    ];
    await supabaseAdmin.from("documents").insert(sampleDocs);
  }

  // 4. Seed notifications per user (skip if any exist)
  for (const u of DEMO_USERS) {
    const uid = userMap[u.email];
    const { count } = await supabaseAdmin.from("notifications").select("*", { count: "exact", head: true }).eq("user_id", uid);
    if (count) continue;
    const items = u.role === "student"
      ? [
          { user_id: uid, title: "Document approved", body: "Your Grade Report has been approved.", type: "success" },
          { user_id: uid, title: "Resubmission required", body: "Your Birth Certificate needs to be re-uploaded.", type: "warning" },
          { user_id: uid, title: "New announcement", body: "Submission deadline extended to June 30.", type: "info" },
        ]
      : [
          { user_id: uid, title: "Pending reviews", body: "5 documents are awaiting validation.", type: "info" },
          { user_id: uid, title: "Suspicious document flagged", body: "ID — Reyes flagged with risk score 82.", type: "danger" },
          { user_id: uid, title: "Masterlist import complete", body: "AY 2024-2025 Sem 1 imported successfully.", type: "success" },
        ];
    await supabaseAdmin.from("notifications").insert(items);
  }

  return { ok: true, accounts: DEMO_USERS.map(({ email, password, role }) => ({ email, password, role })) };
});
