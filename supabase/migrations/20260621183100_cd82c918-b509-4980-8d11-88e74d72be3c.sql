
-- =========== BATCHES ===========
CREATE TABLE public.batches (
  id text PRIMARY KEY,
  name text NOT NULL,
  academic_year text NOT NULL,
  semester text NOT NULL,
  status text NOT NULL DEFAULT 'open',
  total_grantees int NOT NULL DEFAULT 0,
  active int NOT NULL DEFAULT 0,
  pending int NOT NULL DEFAULT 0,
  validated int NOT NULL DEFAULT 0,
  created_at timestamptz NOT NULL DEFAULT now()
);
GRANT SELECT ON public.batches TO anon, authenticated;
GRANT ALL ON public.batches TO service_role;
GRANT INSERT, UPDATE, DELETE ON public.batches TO authenticated;
ALTER TABLE public.batches ENABLE ROW LEVEL SECURITY;
CREATE POLICY "batches read all auth" ON public.batches FOR SELECT TO authenticated USING (true);
CREATE POLICY "batches staff write" ON public.batches FOR ALL TO authenticated
  USING (public.is_staff(auth.uid())) WITH CHECK (public.is_staff(auth.uid()));

-- =========== GRANTEES ===========
CREATE TABLE public.grantees (
  id text PRIMARY KEY,
  student_number text NOT NULL UNIQUE,
  first_name text NOT NULL,
  last_name text NOT NULL,
  middle_name text,
  birthdate date,
  email text,
  contact text,
  university text,
  program text,
  year_level int,
  batch_id text REFERENCES public.batches(id) ON DELETE SET NULL,
  batch text,
  account_status text NOT NULL DEFAULT 'active',
  submission_status text NOT NULL DEFAULT 'not_submitted',
  eligibility text NOT NULL DEFAULT 'pending',
  risk text NOT NULL DEFAULT 'low',
  gwa numeric(4,2),
  profile_completion int NOT NULL DEFAULT 0,
  notes text,
  created_at timestamptz NOT NULL DEFAULT now()
);
GRANT SELECT, INSERT, UPDATE, DELETE ON public.grantees TO authenticated;
GRANT ALL ON public.grantees TO service_role;
ALTER TABLE public.grantees ENABLE ROW LEVEL SECURITY;
CREATE POLICY "grantees staff all" ON public.grantees FOR ALL TO authenticated
  USING (public.is_staff(auth.uid())) WITH CHECK (public.is_staff(auth.uid()));
CREATE POLICY "grantees self read" ON public.grantees FOR SELECT TO authenticated
  USING (student_number = (SELECT student_number FROM public.profiles WHERE id = auth.uid()));

-- =========== ACADEMIC RECORDS ===========
CREATE TABLE public.academic_records (
  grantee_id text PRIMARY KEY REFERENCES public.grantees(id) ON DELETE CASCADE,
  student_number text NOT NULL,
  grantee_name text NOT NULL,
  program text,
  cumulative_gwa numeric(4,2),
  retention_passed boolean NOT NULL DEFAULT true,
  recommendation text NOT NULL DEFAULT 'for_evaluation',
  semesters jsonb NOT NULL DEFAULT '[]'::jsonb,
  updated_at timestamptz NOT NULL DEFAULT now()
);
GRANT SELECT, INSERT, UPDATE, DELETE ON public.academic_records TO authenticated;
GRANT ALL ON public.academic_records TO service_role;
ALTER TABLE public.academic_records ENABLE ROW LEVEL SECURITY;
CREATE POLICY "academic staff all" ON public.academic_records FOR ALL TO authenticated
  USING (public.is_staff(auth.uid())) WITH CHECK (public.is_staff(auth.uid()));
CREATE POLICY "academic self read" ON public.academic_records FOR SELECT TO authenticated
  USING (student_number = (SELECT student_number FROM public.profiles WHERE id = auth.uid()));

-- =========== ANNOUNCEMENTS ===========
CREATE TABLE public.announcements (
  id text PRIMARY KEY,
  title text NOT NULL,
  body text NOT NULL,
  audience text NOT NULL DEFAULT 'all',
  audience_label text,
  channels text[] NOT NULL DEFAULT '{in_app}',
  status text NOT NULL DEFAULT 'draft',
  published_at timestamptz,
  scheduled_for timestamptz,
  author text,
  reach int,
  opens int,
  created_at timestamptz NOT NULL DEFAULT now()
);
GRANT SELECT, INSERT, UPDATE, DELETE ON public.announcements TO authenticated;
GRANT ALL ON public.announcements TO service_role;
ALTER TABLE public.announcements ENABLE ROW LEVEL SECURITY;
CREATE POLICY "announcements read auth" ON public.announcements FOR SELECT TO authenticated USING (true);
CREATE POLICY "announcements staff write" ON public.announcements FOR ALL TO authenticated
  USING (public.is_staff(auth.uid())) WITH CHECK (public.is_staff(auth.uid()));

-- =========== AUDIT LOGS ===========
CREATE TABLE public.audit_logs (
  id text PRIMARY KEY,
  "user" text NOT NULL,
  role text NOT NULL,
  action text NOT NULL,
  module text NOT NULL,
  target text NOT NULL,
  ip text,
  timestamp timestamptz NOT NULL DEFAULT now(),
  before jsonb,
  after jsonb
);
GRANT SELECT, INSERT ON public.audit_logs TO authenticated;
GRANT ALL ON public.audit_logs TO service_role;
ALTER TABLE public.audit_logs ENABLE ROW LEVEL SECURITY;
CREATE POLICY "audit staff read" ON public.audit_logs FOR SELECT TO authenticated
  USING (public.is_staff(auth.uid()));
CREATE POLICY "audit auth insert" ON public.audit_logs FOR INSERT TO authenticated WITH CHECK (true);

-- =========== STAFF DIRECTORY (system users list) ===========
CREATE TABLE public.staff_directory (
  id text PRIMARY KEY,
  username text NOT NULL UNIQUE,
  full_name text NOT NULL,
  email text NOT NULL,
  role text NOT NULL,
  active boolean NOT NULL DEFAULT true,
  mfa boolean NOT NULL DEFAULT false,
  last_login timestamptz,
  created_at timestamptz NOT NULL DEFAULT now()
);
GRANT SELECT, INSERT, UPDATE, DELETE ON public.staff_directory TO authenticated;
GRANT ALL ON public.staff_directory TO service_role;
ALTER TABLE public.staff_directory ENABLE ROW LEVEL SECURITY;
CREATE POLICY "staff dir staff read" ON public.staff_directory FOR SELECT TO authenticated
  USING (public.is_staff(auth.uid()));
CREATE POLICY "staff dir admin write" ON public.staff_directory FOR ALL TO authenticated
  USING (public.has_role(auth.uid(), 'admin')) WITH CHECK (public.has_role(auth.uid(), 'admin'));

-- =========== SEED DATA ===========
INSERT INTO public.batches (id,name,academic_year,semester,status,total_grantees,active,pending,validated,created_at) VALUES
('b1','AY 2024-2025 Sem 1','2024-2025','1st Semester','open',1240,980,180,740,'2025-08-01'),
('b2','AY 2024-2025 Sem 2','2024-2025','2nd Semester','open',1180,410,620,220,'2026-01-10'),
('b3','AY 2023-2024 Sem 2','2023-2024','2nd Semester','closed',1305,1290,0,1290,'2024-01-15'),
('b4','AY 2023-2024 Sem 1','2023-2024','1st Semester','archived',1198,1190,0,1190,'2023-08-12'),
('b5','AY 2022-2023 Sem 2','2022-2023','2nd Semester','archived',1102,1090,0,1090,'2023-01-15'),
('b6','AY 2022-2023 Sem 1','2022-2023','1st Semester','archived',1080,1070,0,1070,'2022-08-12');

INSERT INTO public.staff_directory (id,username,full_name,email,role,active,mfa,last_login) VALUES
('u1','admin','System Administrator','admin@unifast.gov.ph','Admin',true,true,'2026-06-20 08:11'),
('u2','r.santos','Ricardo Santos','r.santos@unifast.gov.ph','Office Head',true,true,'2026-06-20 07:55'),
('u3','j.cruz','Jessica Cruz','j.cruz@unifast.gov.ph','UniFAST Staff',true,false,'2026-06-20 09:02'),
('u4','p.tan','Patricia Tan','p.tan@unifast.gov.ph','UniFAST Staff',true,false,'2026-06-19 17:40'),
('u5','k.aquino','Kris Aquino','k.aquino@unifast.gov.ph','UniFAST Staff',false,false,'2026-05-14 11:21'),
('u6','l.reyes','Liza Reyes','l.reyes@unifast.gov.ph','UniFAST Staff',true,true,'2026-06-20 06:30'),
('u7','m.bautista','Mario Bautista','m.bautista@unifast.gov.ph','Office Head',true,true,'2026-06-19 21:05'),
('u8','c.domingo','Carla Domingo','c.domingo@unifast.gov.ph','UniFAST Staff',true,false,'2026-06-18 15:42');

INSERT INTO public.announcements (id,title,body,audience,audience_label,channels,status,published_at,scheduled_for,author,reach,opens) VALUES
('a1','Submission Deadline Extended to June 30','Due to system maintenance, the submission deadline for AY 2024-2025 Sem 1 has been extended to June 30, 2026.','all','All Students',ARRAY['in_app','email','sms'],'published','2026-06-19 10:00',NULL,'UniFAST Office',1240,1102),
('a2','Resubmission Required for Rejected Documents','Please review your rejected documents and re-upload corrected versions within 7 days.','rejected','Students with Rejected Docs',ARRAY['in_app','email'],'published','2026-06-17 08:30',NULL,'Validation Team',87,79),
('a3','Eligibility Results Will Be Released July 10','Eligibility evaluation for this semester will be released on July 10, 2026.','eligible','Eligible Grantees',ARRAY['in_app','email'],'scheduled',NULL,'2026-07-01 09:00','UniFAST Office',NULL,NULL),
('a4','Draft: Welcome New Grantees','Welcome to the TES Program — here is what to expect in your first semester.','batch','Batch AY 2024-2025 Sem 1',ARRAY['in_app'],'draft',NULL,NULL,'Admin',NULL,NULL),
('a5','Orientation Webinar on July 5','Mandatory online orientation for all new grantees. Link will be sent via email.','batch','Batch AY 2024-2025 Sem 1',ARRAY['in_app','email'],'published','2026-06-10 11:00',NULL,'Office Head',540,488),
('a6','Reminder: Complete Profile Information','Please complete your profile to avoid validation delays.','pending','Pending Activation',ARRAY['in_app','sms'],'published','2026-06-05 16:00',NULL,'Validation Team',312,261),
('a7','System Maintenance Notice','The portal will be unavailable on June 25, 2026 from 1:00 AM to 4:00 AM.','all','All Users',ARRAY['in_app','email'],'scheduled',NULL,'2026-06-24 09:00','Admin',NULL,NULL);

-- Audit logs seed
INSERT INTO public.audit_logs (id,"user",role,action,module,target,ip,timestamp,before,after) VALUES
('al1','admin','Admin','approve_document','Document Validation','Doc #d2 (TOR — Santos)','192.168.1.10','2026-06-20 14:21','{"status":"pending"}','{"status":"approved"}'),
('al2','r.santos','Office Head','create_batch','Batches','Batch AY 2024-2025 Sem 2','192.168.1.05','2026-06-19 09:02',NULL,NULL),
('al3','j.cruz','UniFAST Staff','publish_announcement','Announcements','Submission Deadline Extended','192.168.1.21','2026-06-19 10:00',NULL,NULL),
('al4','p.tan','UniFAST Staff','flag_suspicious','Document Validation','Doc #d3 (ID — Reyes)','192.168.1.22','2026-06-17 11:15','{"risk":35}','{"risk":82,"status":"suspicious"}'),
('al5','k.aquino','UniFAST Staff','import_masterlist','Masterlist','AY 2024-2025 Sem 1 (1,240 rows)','192.168.1.23','2025-08-12 16:40',NULL,NULL),
('al6','m.delacruz','Student Grantee','activate_account','Auth','Self','120.28.45.99','2025-08-15 21:11',NULL,NULL),
('al7','admin','Admin','reject_document','Document Validation','Doc #d6 (Indigency — Villanueva)','192.168.1.10','2026-06-15 14:35','{"status":"pending"}','{"status":"rejected"}'),
('al8','r.santos','Office Head','update_eligibility','Eligibility','Grantee #g2 (Santos)','192.168.1.05','2026-06-14 10:11','{"eligibility":"for_evaluation"}','{"eligibility":"eligible"}'),
('al9','j.cruz','UniFAST Staff','edit_grantee','Grantees','Grantee #g7 (Mendoza)','192.168.1.21','2026-06-13 16:30','{"contact":"+639175551111"}','{"contact":"+639175552222"}'),
('al10','p.tan','UniFAST Staff','request_resubmission','Document Validation','Doc #d4 (PSA — Tan)','192.168.1.22','2026-06-13 11:02','{"status":"pending"}','{"status":"resubmission"}'),
('al11','k.aquino','UniFAST Staff','login','Auth','Self','192.168.1.23','2026-06-13 08:00',NULL,NULL),
('al12','m.delacruz','Student Grantee','failed_login','Auth','k.aquino','120.28.45.99','2026-06-12 22:45',NULL,NULL),
('al13','admin','Admin','create_user','Users & Roles','p.tan (UniFAST Staff)','192.168.1.10','2026-06-10 09:21',NULL,NULL),
('al14','r.santos','Office Head','export_report','Reports','Grantee List — AY 2024-2025 Sem 1 (PDF)','192.168.1.05','2026-06-09 14:00',NULL,NULL),
('al15','j.cruz','UniFAST Staff','export_report','Reports','Document Validation — Q2 (Excel)','192.168.1.21','2026-06-09 14:05',NULL,NULL),
('al16','p.tan','UniFAST Staff','override_eligibility','Eligibility','Grantee #g15','192.168.1.22','2026-06-08 17:55','{"eligibility":"ineligible"}','{"eligibility":"eligible"}'),
('al17','k.aquino','UniFAST Staff','archive_batch','Batches','AY 2022-2023 Sem 2','192.168.1.23','2026-06-05 10:00',NULL,NULL),
('al18','m.delacruz','Student Grantee','reset_password','Users & Roles','k.aquino','120.28.45.99','2026-06-04 11:00',NULL,NULL),
('al19','admin','Admin','approve_document','Document Validation','Doc #d7 (COR — Mendoza)','192.168.1.10','2026-06-03 09:42','{"status":"pending"}','{"status":"approved"}'),
('al20','r.santos','Office Head','publish_announcement','Announcements','Resubmission Required','192.168.1.05','2026-06-02 08:30',NULL,NULL),
('al21','j.cruz','UniFAST Staff','edit_settings','Settings','Auto-approve risk threshold','192.168.1.21','2026-06-01 16:11','{"value":30}','{"value":20}'),
('al22','p.tan','UniFAST Staff','approve_document','Document Validation','Doc #d8 (TOR — Garcia)','192.168.1.22','2026-05-31 14:00','{"status":"pending"}','{"status":"approved"}');

-- Grantees seed (64 rows generated to match mockGrantees)
DO $$
DECLARE
  fns text[] := ARRAY['Maria Clara','Juan Miguel','Andrea Nicole','Joshua','Patricia Mae','Marco','Bea','Daniel','Sophia','Liam','Isabella','Gabriel','Althea','Rafael','Janelle','Kyle','Mikaela','Lance','Trisha','Nathaniel','Yna','Carlos','Camille','Earl','Janine','Paolo','Erika','Jericho','Loraine','Vincent'];
  lns text[] := ARRAY['Dela Cruz','Santos','Reyes','Tan','Lim','Villanueva','Mendoza','Garcia','Aquino','Perez','Bautista','Castillo','Domingo','Esguerra','Fernandez','Gutierrez','Hernandez','Ignacio','Jimenez','Kintanar'];
  unis text[] := ARRAY['Pamantasan ng Lungsod ng Maynila','University of the Philippines Diliman','Polytechnic University of the Philippines','De La Salle University - Dasmariñas','Adamson University','Far Eastern University','University of Santo Tomas','Mapua University','Ateneo de Davao University','Saint Louis University'];
  progs text[] := ARRAY['BS Computer Science','BS Civil Engineering','BS Accountancy','BS Information Technology','BS Chemical Engineering','BS Architecture','BS Nursing','BS Electronics Engineering','AB Political Science','BS Psychology'];
  subs text[] := ARRAY['not_submitted','submitted','under_review','approved','rejected','resubmission_required'];
  eligs text[] := ARRAY['eligible','ineligible','pending','for_evaluation'];
  risks text[] := ARRAY['low','low','low','medium','high'];
  accts text[] := ARRAY['active','active','active','inactive','pending_activation','locked'];
  emails_d text[] := ARRAY['plm','up','pup','ust','feu'];
  i int;
  fn text; ln text; mn text;
BEGIN
  FOR i IN 0..63 LOOP
    fn := fns[(i*3) % array_length(fns,1) + 1];
    ln := lns[(i*7) % array_length(lns,1) + 1];
    mn := lns[i % array_length(lns,1) + 1];
    INSERT INTO public.grantees (id,student_number,first_name,last_name,middle_name,birthdate,email,contact,university,program,year_level,batch_id,batch,account_status,submission_status,eligibility,risk,gwa,profile_completion,notes)
    VALUES (
      'g'||(i+1),
      '2024-'||lpad((10000+i)::text,5,'0'),
      fn, ln, mn,
      ('200'||((i%5)+1)||'-0'||((i%9)+1)||'-1'||(i%9))::date,
      lower(split_part(fn,' ',1))||'.'||lower(replace(ln,' ',''))||'@'||emails_d[i % 5 + 1]||'.edu.ph',
      '+63917'||substring((1000000+i*137)::text,1,7),
      unis[i % array_length(unis,1) + 1],
      progs[i % array_length(progs,1) + 1],
      (i%4)+1,
      CASE WHEN i<40 THEN 'b1' ELSE 'b2' END,
      CASE WHEN i<40 THEN 'AY 2024-2025 Sem 1' ELSE 'AY 2024-2025 Sem 2' END,
      accts[i % array_length(accts,1) + 1],
      subs[i % array_length(subs,1) + 1],
      eligs[i % array_length(eligs,1) + 1],
      risks[i % array_length(risks,1) + 1],
      round((1.25 + (i%30)*0.07)::numeric, 2),
      40 + (i*7) % 60,
      CASE WHEN i%6=0 THEN 'Flagged for late submission in previous semester.' ELSE NULL END
    );
  END LOOP;
END $$;

INSERT INTO public.academic_records (grantee_id,student_number,grantee_name,program,cumulative_gwa,retention_passed,recommendation,semesters) VALUES
('g1','2024-10000','Maria Clara Dela Cruz','BS Computer Science',1.62,true,'eligible','[{"semester":"AY 2023-24 Sem 1","gwa":1.55,"unitsTaken":21,"unitsPassed":21,"failed":[],"dropped":[]},{"semester":"AY 2023-24 Sem 2","gwa":1.70,"unitsTaken":24,"unitsPassed":24,"failed":[],"dropped":[]},{"semester":"AY 2024-25 Sem 1","gwa":1.60,"unitsTaken":21,"unitsPassed":21,"failed":[],"dropped":[]}]'::jsonb),
('g2','2024-10001','Juan Miguel Santos','BS Civil Engineering',2.10,true,'for_evaluation','[{"semester":"AY 2024-25 Sem 1","gwa":2.10,"unitsTaken":18,"unitsPassed":15,"failed":["Math 11"],"dropped":["PE 1"]}]'::jsonb),
('g3','2024-10002','Andrea Nicole Reyes','BS Accountancy',2.95,false,'ineligible','[{"semester":"AY 2023-24 Sem 1","gwa":2.80,"unitsTaken":21,"unitsPassed":18,"failed":["ACC 102"],"dropped":[]},{"semester":"AY 2023-24 Sem 2","gwa":3.10,"unitsTaken":24,"unitsPassed":18,"failed":["TAX 1","MGT 2"],"dropped":["PE 2"]}]'::jsonb);
