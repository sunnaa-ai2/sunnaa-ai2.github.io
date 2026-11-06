-- =====================================================================
--  Spark صُنّاع AI 2 — قاعدة البيانات
--  انسخ هذا الملف كاملًا والصقه في Supabase → SQL Editor → Run
-- =====================================================================

-- 1) المسؤولون (يرسلون الدعوات ويحذفون المحتوى)
create table if not exists public.admins (email text primary key);
insert into public.admins (email) values
  ('azanmd0592344829@gmail.com'),
  ('ahmedafana652001@gmail.com')
on conflict do nothing;
alter table public.admins enable row level security;   -- لا أحد يقرأه مباشرة

-- 2) الملفات الشخصية (وجود ملف = عضو)
create table if not exists public.profiles (
  id          uuid primary key references auth.users(id) on delete cascade,
  full_name   text not null check (char_length(full_name) between 2 and 60),
  bio         text check (bio is null or char_length(bio) <= 300),
  avatar_url  text,
  created_at  timestamptz not null default now()
);

-- 3) الدعوات (مرة واحدة + مربوطة بالإيميل)
create table if not exists public.invites (
  token       uuid primary key default gen_random_uuid(),
  email       text not null check (position('@' in email) > 1),
  created_by  uuid default auth.uid(),
  created_at  timestamptz not null default now(),
  used_at     timestamptz,
  used_by     uuid
);

-- 4) الأعمال
create table if not exists public.works (
  id           uuid primary key default gen_random_uuid(),
  user_id      uuid not null default auth.uid() references public.profiles(id) on delete cascade,
  exercise_no  int  not null check (exercise_no between 1 and 999),
  kind         text not null check (kind in ('image','pdf','file','link')),
  url          text not null check (url ~* '^https?://'),
  file_path    text,
  created_at   timestamptz not null default now(),
  updated_at   timestamptz
);
create index if not exists works_user_idx on public.works(user_id, created_at);

-- 5) الإعجاب / عدم الإعجاب
create table if not exists public.reactions (
  work_id    uuid not null references public.works(id) on delete cascade,
  user_id    uuid not null default auth.uid() references public.profiles(id) on delete cascade,
  value      smallint not null check (value in (1,-1)),
  created_at timestamptz not null default now(),
  primary key (work_id, user_id)
);

-- 6) التعليقات
create table if not exists public.comments (
  id         uuid primary key default gen_random_uuid(),
  work_id    uuid not null references public.works(id) on delete cascade,
  user_id    uuid not null default auth.uid() references public.profiles(id) on delete cascade,
  body       text not null check (char_length(body) between 1 and 1000),
  created_at timestamptz not null default now()
);
create index if not exists comments_work_idx on public.comments(work_id, created_at);

-- ---------------------------------------------------------------------
--  دوال مساعدة
-- ---------------------------------------------------------------------
create or replace function public.is_admin() returns boolean
language sql stable security definer set search_path = public as $$
  select exists (select 1 from public.admins
                 where lower(email) = lower(coalesce(auth.jwt() ->> 'email', '')));
$$;

create or replace function public.is_member() returns boolean
language sql stable security definer set search_path = public as $$
  select exists (select 1 from public.profiles where id = auth.uid());
$$;

-- الانضمام برابط دعوة (أو أدمن بدون دعوة)
create or replace function public.join_with_invite(p_token uuid, p_name text) returns void
language plpgsql security definer set search_path = public as $$
declare
  v_email text := lower(coalesce(auth.jwt() ->> 'email', ''));
  inv public.invites;
begin
  if auth.uid() is null then raise exception 'NOT_LOGGED_IN'; end if;
  if exists (select 1 from public.profiles where id = auth.uid()) then return; end if;

  if p_token is null then
    if not public.is_admin() then raise exception 'INVITE_REQUIRED'; end if;
  else
    select * into inv from public.invites where token = p_token for update;
    if not found                      then raise exception 'INVITE_INVALID'; end if;
    if inv.used_at is not null        then raise exception 'INVITE_USED'; end if;
    if lower(inv.email) <> v_email    then raise exception 'INVITE_EMAIL_MISMATCH'; end if;
    update public.invites set used_at = now(), used_by = auth.uid() where token = p_token;
  end if;

  insert into public.profiles (id, full_name) values (auth.uid(), trim(p_name));
end;
$$;

-- حذف المستخدم لحسابه بالكامل (يحذف الملف والأعمال والتعليقات والتفاعلات تلقائيًا)
create or replace function public.delete_my_account() returns void
language plpgsql security definer set search_path = public as $$
begin
  if auth.uid() is null then raise exception 'NOT_LOGGED_IN'; end if;
  delete from public.profiles where id = auth.uid();
  delete from auth.users where id = auth.uid();
end;
$$;
revoke all on function public.delete_my_account() from public, anon;
grant execute on function public.delete_my_account() to authenticated;

revoke all on function public.join_with_invite(uuid, text) from public, anon;
grant execute on function public.join_with_invite(uuid, text) to authenticated;
grant execute on function public.is_admin() to anon, authenticated;
grant execute on function public.is_member() to anon, authenticated;

-- ---------------------------------------------------------------------
--  صلاحيات الصفوف (RLS)
-- ---------------------------------------------------------------------
alter table public.profiles  enable row level security;
alter table public.invites   enable row level security;
alter table public.works     enable row level security;
alter table public.reactions enable row level security;
alter table public.comments  enable row level security;

-- الملفات الشخصية: الكل يقرأ، صاحبها يعدّل، الأدمن يعدّل ويحذف. الإنشاء فقط عبر join_with_invite
drop policy if exists p_sel on public.profiles;
drop policy if exists p_upd on public.profiles;
drop policy if exists p_del on public.profiles;
create policy p_sel on public.profiles for select using (true);
create policy p_upd on public.profiles for update to authenticated
  using (id = auth.uid() or public.is_admin()) with check (id = auth.uid() or public.is_admin());
create policy p_del on public.profiles for delete to authenticated using (public.is_admin());

-- الدعوات: الأدمن فقط
drop policy if exists i_sel on public.invites;
drop policy if exists i_ins on public.invites;
drop policy if exists i_del on public.invites;
create policy i_sel on public.invites for select to authenticated using (public.is_admin());
create policy i_ins on public.invites for insert to authenticated
  with check (public.is_admin() and created_by = auth.uid() and used_at is null);
create policy i_del on public.invites for delete to authenticated using (public.is_admin());

-- الأعمال: الكل يقرأ، العضو ينشر لنفسه، صاحب العمل أو الأدمن يعدّل ويحذف
drop policy if exists w_sel on public.works;
drop policy if exists w_ins on public.works;
drop policy if exists w_upd on public.works;
drop policy if exists w_del on public.works;
create policy w_sel on public.works for select using (true);
create policy w_ins on public.works for insert to authenticated
  with check (user_id = auth.uid() and public.is_member());
create policy w_upd on public.works for update to authenticated
  using (user_id = auth.uid() or public.is_admin())
  with check (user_id = auth.uid() or public.is_admin());
create policy w_del on public.works for delete to authenticated
  using (user_id = auth.uid() or public.is_admin());

-- التفاعل: الإعجاب يراه الجميع؛ عدم الإعجاب يراه صاحبه وصاحب العمل والأدمن فقط
drop policy if exists r_sel on public.reactions;
drop policy if exists r_ins on public.reactions;
drop policy if exists r_upd on public.reactions;
drop policy if exists r_del on public.reactions;
create policy r_sel on public.reactions for select using (
  value = 1
  or user_id = auth.uid()
  or public.is_admin()
  or exists (select 1 from public.works w where w.id = work_id and w.user_id = auth.uid())
);
create policy r_ins on public.reactions for insert to authenticated
  with check (user_id = auth.uid() and public.is_member());
create policy r_upd on public.reactions for update to authenticated
  using (user_id = auth.uid()) with check (user_id = auth.uid() and public.is_member());
create policy r_del on public.reactions for delete to authenticated using (user_id = auth.uid());

-- التعليقات: الكل يقرأ، العضو يكتب، الكاتب أو الأدمن يحذف
drop policy if exists c_sel on public.comments;
drop policy if exists c_ins on public.comments;
drop policy if exists c_del on public.comments;
create policy c_sel on public.comments for select using (true);
create policy c_ins on public.comments for insert to authenticated
  with check (user_id = auth.uid() and public.is_member());
create policy c_del on public.comments for delete to authenticated
  using (user_id = auth.uid() or public.is_admin());

-- ---------------------------------------------------------------------
--  تخزين الصور والملفات (أي صيغة، حتى 50MB)
-- ---------------------------------------------------------------------
insert into storage.buckets (id, name, public, file_size_limit, allowed_mime_types)
values ('uploads', 'uploads', true, 52428800, null)
on conflict (id) do update set file_size_limit = excluded.file_size_limit,
                               allowed_mime_types = excluded.allowed_mime_types;

-- للسماح بنوع "ملف" في قاعدة موجودة مسبقًا
alter table public.works drop constraint if exists works_kind_check;
alter table public.works add constraint works_kind_check check (kind in ('image','pdf','file','link'));

drop policy if exists up_ins on storage.objects;
drop policy if exists up_del on storage.objects;
create policy up_ins on storage.objects for insert to authenticated
  with check (bucket_id = 'uploads'
              and (storage.foldername(name))[1] = auth.uid()::text
              and public.is_member());
create policy up_del on storage.objects for delete to authenticated
  using (bucket_id = 'uploads'
         and ((storage.foldername(name))[1] = auth.uid()::text or public.is_admin()));

-- لإضافة مسؤول جديد لاحقًا:
-- insert into public.admins (email) values ('new.admin@gmail.com');
