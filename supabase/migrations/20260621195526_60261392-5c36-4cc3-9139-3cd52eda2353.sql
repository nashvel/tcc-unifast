
CREATE POLICY "staff dir self read"
ON public.staff_directory
FOR SELECT
TO authenticated
USING (id = auth.uid()::text AND is_staff(auth.uid()));
