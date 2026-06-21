
CREATE OR REPLACE FUNCTION public.documents_lock_risk_score()
RETURNS trigger
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public
AS $$
BEGIN
  IF TG_OP = 'INSERT' THEN
    IF NOT public.is_staff(auth.uid()) THEN
      NEW.risk_score := 0;
    END IF;
  ELSIF TG_OP = 'UPDATE' THEN
    IF NEW.risk_score IS DISTINCT FROM OLD.risk_score AND NOT public.is_staff(auth.uid()) THEN
      NEW.risk_score := OLD.risk_score;
    END IF;
  END IF;
  RETURN NEW;
END;
$$;

REVOKE EXECUTE ON FUNCTION public.documents_lock_risk_score() FROM PUBLIC, anon, authenticated;

DROP TRIGGER IF EXISTS documents_lock_risk_score_ins ON public.documents;
DROP TRIGGER IF EXISTS documents_lock_risk_score_upd ON public.documents;

CREATE TRIGGER documents_lock_risk_score_ins
BEFORE INSERT ON public.documents
FOR EACH ROW EXECUTE FUNCTION public.documents_lock_risk_score();

CREATE TRIGGER documents_lock_risk_score_upd
BEFORE UPDATE ON public.documents
FOR EACH ROW EXECUTE FUNCTION public.documents_lock_risk_score();
