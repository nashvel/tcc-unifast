
REVOKE EXECUTE ON FUNCTION public.profiles_lock_student_number() FROM PUBLIC, anon, authenticated;
REVOKE EXECUTE ON FUNCTION public.documents_lock_risk_score() FROM PUBLIC, anon, authenticated;
REVOKE EXECUTE ON FUNCTION public.security_memory_before_update() FROM PUBLIC, anon, authenticated;
REVOKE EXECUTE ON FUNCTION public.security_memory_snapshot_revision() FROM PUBLIC, anon, authenticated;
REVOKE EXECUTE ON FUNCTION public.handle_new_user() FROM PUBLIC, anon, authenticated;
