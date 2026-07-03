
DROP POLICY IF EXISTS "Users can insert their own login events" ON public.login_events;
REVOKE INSERT ON public.login_events FROM authenticated;

CREATE OR REPLACE FUNCTION public.record_login_event(_user_agent text)
RETURNS void
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public
AS $$
BEGIN
  IF auth.uid() IS NULL THEN
    RAISE EXCEPTION 'not authenticated';
  END IF;
  INSERT INTO public.login_events (user_id, user_agent)
  VALUES (auth.uid(), _user_agent);
END;
$$;

REVOKE ALL ON FUNCTION public.record_login_event(text) FROM PUBLIC;
GRANT EXECUTE ON FUNCTION public.record_login_event(text) TO authenticated;
