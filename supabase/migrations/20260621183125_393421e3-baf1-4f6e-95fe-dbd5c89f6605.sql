
DROP POLICY IF EXISTS "audit auth insert" ON public.audit_logs;
CREATE POLICY "audit staff insert" ON public.audit_logs FOR INSERT TO authenticated
  WITH CHECK (public.is_staff(auth.uid()));
