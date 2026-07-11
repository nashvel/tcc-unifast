-- ============ Enums ============
DO $$ BEGIN
  CREATE TYPE public.support_category AS ENUM ('bug','question','request');
EXCEPTION WHEN duplicate_object THEN NULL; END $$;

DO $$ BEGIN
  CREATE TYPE public.support_priority AS ENUM ('low','normal','high');
EXCEPTION WHEN duplicate_object THEN NULL; END $$;

DO $$ BEGIN
  CREATE TYPE public.support_status AS ENUM ('open','in_progress','resolved','closed');
EXCEPTION WHEN duplicate_object THEN NULL; END $$;

-- ============ updated_at helper (idempotent) ============
CREATE OR REPLACE FUNCTION public.set_updated_at()
RETURNS trigger
LANGUAGE plpgsql
SET search_path = public
AS $$
BEGIN
  NEW.updated_at = now();
  RETURN NEW;
END;
$$;

-- ============ support_tickets ============
CREATE TABLE public.support_tickets (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  subject text NOT NULL,
  body text NOT NULL,
  category public.support_category NOT NULL DEFAULT 'question',
  priority public.support_priority NOT NULL DEFAULT 'normal',
  status public.support_status NOT NULL DEFAULT 'open',
  created_by uuid NOT NULL REFERENCES auth.users(id) ON DELETE CASCADE,
  assigned_to uuid REFERENCES auth.users(id) ON DELETE SET NULL,
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now()
);

GRANT SELECT, INSERT, UPDATE ON public.support_tickets TO authenticated;
GRANT ALL ON public.support_tickets TO service_role;

ALTER TABLE public.support_tickets ENABLE ROW LEVEL SECURITY;

-- Staff/head/admin can create tickets, always as themselves
CREATE POLICY "staff_and_admin_create_tickets"
ON public.support_tickets FOR INSERT TO authenticated
WITH CHECK (
  created_by = auth.uid()
  AND (
    public.has_role(auth.uid(),'admin')
    OR public.has_role(auth.uid(),'staff')
    OR public.has_role(auth.uid(),'head')
  )
);

-- Owner can read their tickets; admin can read all
CREATE POLICY "owner_or_admin_read_tickets"
ON public.support_tickets FOR SELECT TO authenticated
USING (
  created_by = auth.uid()
  OR public.has_role(auth.uid(),'admin')
);

-- Owner can update their own ticket body/subject while open; admin can update anything
CREATE POLICY "owner_or_admin_update_tickets"
ON public.support_tickets FOR UPDATE TO authenticated
USING (
  created_by = auth.uid()
  OR public.has_role(auth.uid(),'admin')
)
WITH CHECK (
  created_by = auth.uid()
  OR public.has_role(auth.uid(),'admin')
);

CREATE TRIGGER support_tickets_set_updated_at
BEFORE UPDATE ON public.support_tickets
FOR EACH ROW EXECUTE FUNCTION public.set_updated_at();

CREATE INDEX support_tickets_created_by_idx ON public.support_tickets(created_by);
CREATE INDEX support_tickets_status_idx ON public.support_tickets(status);
CREATE INDEX support_tickets_updated_at_idx ON public.support_tickets(updated_at DESC);

-- ============ support_ticket_messages ============
CREATE TABLE public.support_ticket_messages (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  ticket_id uuid NOT NULL REFERENCES public.support_tickets(id) ON DELETE CASCADE,
  author_id uuid NOT NULL REFERENCES auth.users(id) ON DELETE CASCADE,
  body text NOT NULL,
  is_internal boolean NOT NULL DEFAULT false,
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now()
);

GRANT SELECT, INSERT ON public.support_ticket_messages TO authenticated;
GRANT ALL ON public.support_ticket_messages TO service_role;

ALTER TABLE public.support_ticket_messages ENABLE ROW LEVEL SECURITY;

-- Read: owner sees non-internal messages on own tickets; admin sees all
CREATE POLICY "read_ticket_messages"
ON public.support_ticket_messages FOR SELECT TO authenticated
USING (
  public.has_role(auth.uid(),'admin')
  OR (
    NOT is_internal
    AND EXISTS (
      SELECT 1 FROM public.support_tickets t
      WHERE t.id = ticket_id AND t.created_by = auth.uid()
    )
  )
);

-- Insert: admin can post anywhere (incl. internal). Owner can post non-internal replies on own ticket.
CREATE POLICY "insert_ticket_messages"
ON public.support_ticket_messages FOR INSERT TO authenticated
WITH CHECK (
  author_id = auth.uid()
  AND (
    public.has_role(auth.uid(),'admin')
    OR (
      is_internal = false
      AND EXISTS (
        SELECT 1 FROM public.support_tickets t
        WHERE t.id = ticket_id AND t.created_by = auth.uid()
      )
    )
  )
);

CREATE INDEX support_ticket_messages_ticket_idx
  ON public.support_ticket_messages(ticket_id, created_at);

-- Bump parent ticket updated_at on new message
CREATE OR REPLACE FUNCTION public.support_ticket_bump_updated()
RETURNS trigger
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public
AS $$
BEGIN
  UPDATE public.support_tickets SET updated_at = now() WHERE id = NEW.ticket_id;
  RETURN NEW;
END;
$$;

CREATE TRIGGER support_ticket_messages_bump_parent
AFTER INSERT ON public.support_ticket_messages
FOR EACH ROW EXECUTE FUNCTION public.support_ticket_bump_updated();
