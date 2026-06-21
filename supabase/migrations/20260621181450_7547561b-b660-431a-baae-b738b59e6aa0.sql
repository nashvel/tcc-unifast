-- ============ ROLES ============
create type public.app_role as enum ('admin', 'staff', 'head', 'student');

create table public.profiles (
  id uuid primary key references auth.users(id) on delete cascade,
  full_name text not null default '',
  email text,
  student_number text,
  university text,
  program text,
  year_level int,
  contact text,
  birthdate date,
  created_at timestamptz not null default now()
);
grant select, insert, update, delete on public.profiles to authenticated;
grant all on public.profiles to service_role;
alter table public.profiles enable row level security;

create table public.user_roles (
  id uuid primary key default gen_random_uuid(),
  user_id uuid not null references auth.users(id) on delete cascade,
  role app_role not null,
  unique(user_id, role)
);
grant select on public.user_roles to authenticated;
grant all on public.user_roles to service_role;
alter table public.user_roles enable row level security;

create or replace function public.has_role(_user_id uuid, _role app_role)
returns boolean language sql stable security definer set search_path = public as $$
  select exists (select 1 from public.user_roles where user_id = _user_id and role = _role)
$$;

create or replace function public.is_staff(_user_id uuid)
returns boolean language sql stable security definer set search_path = public as $$
  select exists (
    select 1 from public.user_roles
    where user_id = _user_id and role in ('admin','staff','head')
  )
$$;

create policy "users read own profile" on public.profiles
  for select to authenticated using (id = auth.uid());
create policy "staff read all profiles" on public.profiles
  for select to authenticated using (public.is_staff(auth.uid()));
create policy "users update own profile" on public.profiles
  for update to authenticated using (id = auth.uid());

create policy "users read own roles" on public.user_roles
  for select to authenticated using (user_id = auth.uid());
create policy "staff read all roles" on public.user_roles
  for select to authenticated using (public.is_staff(auth.uid()));
create policy "admins manage roles" on public.user_roles
  for all to authenticated
  using (public.has_role(auth.uid(), 'admin'))
  with check (public.has_role(auth.uid(), 'admin'));

-- Auto-create profile + default student role on signup
create or replace function public.handle_new_user()
returns trigger language plpgsql security definer set search_path = public as $$
begin
  insert into public.profiles (id, full_name, email)
  values (new.id, coalesce(new.raw_user_meta_data->>'full_name',''), new.email)
  on conflict (id) do nothing;
  insert into public.user_roles (user_id, role)
  values (new.id, 'student')
  on conflict do nothing;
  return new;
end;
$$;

drop trigger if exists on_auth_user_created on auth.users;
create trigger on_auth_user_created
  after insert on auth.users
  for each row execute function public.handle_new_user();

-- ============ MASTERLIST ============
create table public.masterlist (
  id uuid primary key default gen_random_uuid(),
  student_number text not null,
  first_name text not null,
  last_name text not null,
  middle_name text,
  birthdate date,
  email text,
  contact text,
  university text,
  program text,
  year_level int,
  batch text,
  account_status text not null default 'pending_activation',
  imported_at timestamptz not null default now()
);
grant select, insert, update, delete on public.masterlist to authenticated;
grant all on public.masterlist to service_role;
alter table public.masterlist enable row level security;
create policy "staff read masterlist" on public.masterlist
  for select to authenticated using (public.is_staff(auth.uid()));
create policy "admin/staff write masterlist" on public.masterlist
  for insert to authenticated with check (
    public.has_role(auth.uid(), 'admin') or public.has_role(auth.uid(), 'staff')
  );
create policy "admin/staff update masterlist" on public.masterlist
  for update to authenticated using (
    public.has_role(auth.uid(), 'admin') or public.has_role(auth.uid(), 'staff')
  );
create policy "admin delete masterlist" on public.masterlist
  for delete to authenticated using (public.has_role(auth.uid(), 'admin'));

-- ============ DOCUMENTS ============
create table public.documents (
  id uuid primary key default gen_random_uuid(),
  owner_id uuid references auth.users(id) on delete cascade,
  grantee_name text not null,
  student_number text not null,
  type text not null,
  filename text not null,
  uploaded_at timestamptz not null default now(),
  status text not null default 'pending',
  risk_score int not null default 0,
  remarks text,
  ocr jsonb,
  exif jsonb
);
grant select, insert, update, delete on public.documents to authenticated;
grant all on public.documents to service_role;
alter table public.documents enable row level security;
create policy "students read own docs" on public.documents
  for select to authenticated using (owner_id = auth.uid());
create policy "students insert own docs" on public.documents
  for insert to authenticated with check (owner_id = auth.uid());
create policy "staff read all docs" on public.documents
  for select to authenticated using (public.is_staff(auth.uid()));
create policy "staff update all docs" on public.documents
  for update to authenticated using (public.is_staff(auth.uid()));
create policy "admin delete docs" on public.documents
  for delete to authenticated using (public.has_role(auth.uid(), 'admin'));

-- ============ NOTIFICATIONS ============
create table public.notifications (
  id uuid primary key default gen_random_uuid(),
  user_id uuid not null references auth.users(id) on delete cascade,
  title text not null,
  body text,
  type text not null default 'info',
  read boolean not null default false,
  created_at timestamptz not null default now()
);
grant select, insert, update, delete on public.notifications to authenticated;
grant all on public.notifications to service_role;
alter table public.notifications enable row level security;
create policy "users see own notifications" on public.notifications
  for select to authenticated using (user_id = auth.uid());
create policy "users update own notifications" on public.notifications
  for update to authenticated using (user_id = auth.uid());
create policy "staff create notifications" on public.notifications
  for insert to authenticated with check (public.is_staff(auth.uid()));