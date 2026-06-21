
-- Announcements: restrict reads to published; staff keep full visibility
DROP POLICY IF EXISTS "announcements read auth" ON public.announcements;

CREATE POLICY "announcements read published"
ON public.announcements
FOR SELECT
TO authenticated
USING (
  status = 'published'
  AND (published_at IS NULL OR published_at <= now())
);

CREATE POLICY "announcements staff read all"
ON public.announcements
FOR SELECT
TO authenticated
USING (is_staff(auth.uid()));

-- Staff directory: admin-only reads
DROP POLICY IF EXISTS "staff dir staff read" ON public.staff_directory;

CREATE POLICY "staff dir admin read"
ON public.staff_directory
FOR SELECT
TO authenticated
USING (has_role(auth.uid(), 'admin'::app_role));
