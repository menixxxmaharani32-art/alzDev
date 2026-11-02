-- Jalankan sekali di Supabase -> SQL Editor -> New query -> Run
create table if not exists push_subs (
  id bigserial primary key,
  endpoint_hash text unique not null,
  endpoint text not null,
  p256dh text default '',
  auth text default '',
  created_at timestamptz default now()
);
create table if not exists push_events (
  id bigserial primary key,
  type text,
  title text,
  body text,
  url text,
  created_at timestamptz default now()
);
create table if not exists push_config (
  k text primary key,
  v text
);
-- Kunci rahasia VAPID ada di push_config: aktifkan RLS tanpa policy
-- supaya hanya server (service key) yang bisa membaca.
alter table push_subs enable row level security;
alter table push_events enable row level security;
alter table push_config enable row level security;
