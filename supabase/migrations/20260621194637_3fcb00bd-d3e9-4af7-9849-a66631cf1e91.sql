
-- Category enum
DO $$ BEGIN
  CREATE TYPE public.security_memory_category AS ENUM ('invariant','scanner_guidance','accepted_risk','note');
EXCEPTION WHEN duplicate_object THEN NULL; END $$;

DO $$ BEGIN
  CREATE TYPE public.security_memory_status AS ENUM ('active','archived');
EXCEPTION WHEN duplicate_object THEN NULL; END $$;

-- Main entries table
CREATE TABLE public.security_memory_entries (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  title text NOT NULL,
  body text NOT NULL,
  category public.security_memory_category NOT NULL DEFAULT 'note',
  status public.security_memory_status NOT NULL DEFAULT 'active',
  related_finding_id text,
  version integer NOT NULL DEFAULT 1,
  created_by uuid REFERENCES auth.users(id) ON DELETE SET NULL,
  updated_by uuid REFERENCES auth.users(id) ON DELETE SET NULL,
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now()
);

GRANT SELECT, INSERT, UPDATE, DELETE ON public.security_memory_entries TO authenticated;
GRANT ALL ON public.security_memory_entries TO service_role;

ALTER TABLE public.security_memory_entries ENABLE ROW LEVEL SECURITY;

CREATE POLICY "sec mem admin read"   ON public.security_memory_entries FOR SELECT TO authenticated USING (has_role(auth.uid(),'admin'::app_role));
CREATE POLICY "sec mem admin insert" ON public.security_memory_entries FOR INSERT TO authenticated WITH CHECK (has_role(auth.uid(),'admin'::app_role));
CREATE POLICY "sec mem admin update" ON public.security_memory_entries FOR UPDATE TO authenticated USING (has_role(auth.uid(),'admin'::app_role)) WITH CHECK (has_role(auth.uid(),'admin'::app_role));
CREATE POLICY "sec mem admin delete" ON public.security_memory_entries FOR DELETE TO authenticated USING (has_role(auth.uid(),'admin'::app_role));

-- Revisions (history)
CREATE TABLE public.security_memory_revisions (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  entry_id uuid NOT NULL REFERENCES public.security_memory_entries(id) ON DELETE CASCADE,
  version integer NOT NULL,
  title text NOT NULL,
  body text NOT NULL,
  category public.security_memory_category NOT NULL,
  status public.security_memory_status NOT NULL,
  related_finding_id text,
  change_summary text,
  changed_by uuid REFERENCES auth.users(id) ON DELETE SET NULL,
  created_at timestamptz NOT NULL DEFAULT now()
);

GRANT SELECT, INSERT ON public.security_memory_revisions TO authenticated;
GRANT ALL ON public.security_memory_revisions TO service_role;

ALTER TABLE public.security_memory_revisions ENABLE ROW LEVEL SECURITY;

CREATE POLICY "sec mem rev admin read"   ON public.security_memory_revisions FOR SELECT TO authenticated USING (has_role(auth.uid(),'admin'::app_role));
CREATE POLICY "sec mem rev admin insert" ON public.security_memory_revisions FOR INSERT TO authenticated WITH CHECK (has_role(auth.uid(),'admin'::app_role));

-- Updated-at + version + auto-revision trigger
CREATE OR REPLACE FUNCTION public.security_memory_before_update()
RETURNS trigger
LANGUAGE plpgsql
SET search_path = public
AS $$
BEGIN
  NEW.updated_at = now();
  IF NEW.title IS DISTINCT FROM OLD.title
     OR NEW.body IS DISTINCT FROM OLD.body
     OR NEW.category IS DISTINCT FROM OLD.category
     OR NEW.status IS DISTINCT FROM OLD.status
     OR NEW.related_finding_id IS DISTINCT FROM OLD.related_finding_id
  THEN
    NEW.version = OLD.version + 1;
  END IF;
  RETURN NEW;
END;
$$;

CREATE TRIGGER security_memory_entries_before_update
BEFORE UPDATE ON public.security_memory_entries
FOR EACH ROW EXECUTE FUNCTION public.security_memory_before_update();

CREATE OR REPLACE FUNCTION public.security_memory_snapshot_revision()
RETURNS trigger
LANGUAGE plpgsql
SET search_path = public
AS $$
BEGIN
  INSERT INTO public.security_memory_revisions
    (entry_id, version, title, body, category, status, related_finding_id, change_summary, changed_by)
  VALUES
    (NEW.id, NEW.version, NEW.title, NEW.body, NEW.category, NEW.status, NEW.related_finding_id, NULL, NEW.updated_by);
  RETURN NEW;
END;
$$;

CREATE TRIGGER security_memory_entries_after_write
AFTER INSERT OR UPDATE ON public.security_memory_entries
FOR EACH ROW EXECUTE FUNCTION public.security_memory_snapshot_revision();
