
CREATE OR REPLACE FUNCTION public.profiles_lock_student_number()
RETURNS trigger
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public
AS $$
BEGIN
  IF NEW.student_number IS DISTINCT FROM OLD.student_number THEN
    IF NOT public.is_staff(auth.uid()) THEN
      NEW.student_number := OLD.student_number;
    END IF;
  END IF;
  RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS profiles_lock_student_number ON public.profiles;
CREATE TRIGGER profiles_lock_student_number
BEFORE UPDATE ON public.profiles
FOR EACH ROW EXECUTE FUNCTION public.profiles_lock_student_number();
