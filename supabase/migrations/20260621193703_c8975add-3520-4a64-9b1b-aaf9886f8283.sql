
-- Tighten avatar storage SELECT: own folder or staff
DROP POLICY IF EXISTS "Avatars are readable by authenticated users" ON storage.objects;

CREATE POLICY "Users read own avatar or staff reads all"
ON storage.objects FOR SELECT
TO authenticated
USING (
  bucket_id = 'avatars'
  AND (
    (auth.uid())::text = (storage.foldername(name))[1]
    OR public.is_staff(auth.uid())
  )
);

-- Harden user_roles: split the broad ALL policy into explicit per-command admin policies,
-- so INSERT is gated by an explicit admin WITH CHECK and there is no ambiguity.
DROP POLICY IF EXISTS "admins manage roles" ON public.user_roles;

CREATE POLICY "admins insert roles"
ON public.user_roles FOR INSERT
TO authenticated
WITH CHECK (public.has_role(auth.uid(), 'admin'));

CREATE POLICY "admins update roles"
ON public.user_roles FOR UPDATE
TO authenticated
USING (public.has_role(auth.uid(), 'admin'))
WITH CHECK (public.has_role(auth.uid(), 'admin'));

CREATE POLICY "admins delete roles"
ON public.user_roles FOR DELETE
TO authenticated
USING (public.has_role(auth.uid(), 'admin'));

CREATE POLICY "admins read all roles"
ON public.user_roles FOR SELECT
TO authenticated
USING (public.has_role(auth.uid(), 'admin'));
