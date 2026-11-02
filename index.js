const express = require('express');
const crypto = require('crypto');
const fs = require('fs');
const path = require('path');
const app = express();
app.disable('x-powered-by');
app.use(express.json({ limit: '1mb' })); // foto QRIS / bukti transfer dikirim sebagai data kecil
app.use((req, res, next) => {
  res.set({ 'X-Content-Type-Options': 'nosniff', 'X-Frame-Options': 'DENY', 'Referrer-Policy': 'same-origin' });
  // halaman admin, login, dan API tidak boleh disimpan cache (tombol Back tidak bisa membuka dasbor setelah logout)
  if (req.path.startsWith('/api') || req.path === '/admin' || req.path === '/login') res.set('Cache-Control', 'no-store');
  next();
});

let CSS_TEXT = '';
try { CSS_TEXT = fs.readFileSync(path.join(__dirname, 'style.css'), 'utf8'); }
catch (e) { console.error('style.css tidak terbaca:', e.message); }
app.get('/style.css', (req, res) => res.type('css').send(CSS_TEXT));

/* ================= Auth: cookie bertanda tangan ================= */
const secret = () => process.env.SESSION_SECRET || '';
const sign = (d) => crypto.createHmac('sha256', secret()).update(d).digest('base64url');
const hash = (v) => crypto.createHash('sha256').update(String(v)).digest();
const safeEqual = (a, b) => crypto.timingSafeEqual(hash(a), hash(b));
// maxAge kosong (null) = cookie sesi: hilang saat browser ditutup. Diisi = diingat di perangkat.
const cookie = (name, v, maxAge) => `${name}=${v}; HttpOnly; Secure; SameSite=Strict; Path=/` + (maxAge == null ? '' : `; Max-Age=${maxAge}`);

function tokenOf(obj, ttlMs = 7 * 864e5) {
  const p = Buffer.from(JSON.stringify({ ...obj, exp: Date.now() + ttlMs })).toString('base64url');
  return p + '.' + sign(p);
}
function readTok(req, name) {
  const m = new RegExp('(?:^|;\\s*)' + name + '=([^;]+)').exec(req.headers.cookie || '');
  if (!m || !secret()) return null;
  const [p, s] = m[1].split('.');
  if (!p || !s || !safeEqual(sign(p), s)) return null;
  try {
    const d = JSON.parse(Buffer.from(p, 'base64url').toString());
    return d.exp > Date.now() ? d : null;
  } catch { return null; }
}
const readAdmin = (req) => { const t = readTok(req, 'session'); return t && t.u ? t.u : null; };
const readBuyer = (req) => { const t = readTok(req, 'bs'); return t && t.id ? t.id : null; };
const adminOnly = (req, res, next) => (readAdmin(req) ? next() : res.status(401).json({ ok: false, status: false }));
const buyerOnly = (req, res, next) => {
  const id = readBuyer(req);
  if (!id) return res.status(401).json({ status: false, error: 'Silakan masuk dulu.' });
  req.buyerId = id;
  next();
};

const hashPw = (pw) => {
  const salt = crypto.randomBytes(16).toString('hex');
  return salt + ':' + crypto.scryptSync(pw, salt, 32).toString('hex');
};
const checkPw = (pw, stored) => {
  const [salt, h] = String(stored).split(':');
  if (!salt || !h) return false;
  const x = crypto.scryptSync(pw, salt, 32);
  const y = Buffer.from(h, 'hex');
  return x.length === y.length && crypto.timingSafeEqual(x, y);
};

// Pembatas sederhana: maksimal 10 percobaan per 10 menit per IP
const hits = new Map();
const limited = (req, k) => {
  const key = k + ((req.headers['x-forwarded-for'] || '').split(',')[0] || 'x');
  const now = Date.now();
  const a = (hits.get(key) || []).filter((t) => now - t < 600000);
  a.push(now);
  hits.set(key, a);
  return a.length > 10;
};

/* ================= Supabase (REST, kunci server) ================= */
const sb = (t) => `${process.env.SUPABASE_URL}/rest/v1/${t}`;
const dbHead = () => {
  const k = process.env.SUPABASE_SERVICE_KEY || '';
  const h = { apikey: k, 'Content-Type': 'application/json' };
  if (k.startsWith('eyJ')) h.Authorization = 'Bearer ' + k; // hanya untuk kunci legacy (JWT)
  return h;
};
async function rest(table, qs = '', opt = {}) {
  const r = await fetch(sb(table) + qs, {
    method: opt.method || 'GET',
    headers: { ...dbHead(), ...(opt.prefer ? { Prefer: opt.prefer } : {}) },
    body: opt.body !== undefined ? JSON.stringify(opt.body) : undefined,
  });
  if (!r.ok) {
    const e = new Error('Supabase error ' + r.status + ' ' + (await r.text()).slice(0, 200));
    e.status = r.status;
    throw e;
  }
  const t = await r.text();
  return t ? JSON.parse(t) : null;
}
async function rpc(fn, args) {
  const r = await fetch(`${process.env.SUPABASE_URL}/rest/v1/rpc/${fn}`, { method: 'POST', headers: dbHead(), body: JSON.stringify(args) });
  if (!r.ok) throw new Error('Supabase error ' + r.status + ' ' + (await r.text()).slice(0, 200));
  return r.json();
}
const getSettings = async () => Object.fromEntries((await rest('settings', '?select=key,value&key=neq.pay_qris')).map((x) => [x.key, x.value]));
const saveSettings = (obj) =>
  rest('settings', '', { method: 'POST', prefer: 'resolution=merge-duplicates', body: Object.entries(obj).map(([key, value]) => ({ key, value })) });

const num = (v) => Number(v) || 0;
const mask = (v) => (v ? v.slice(0, 4) + '••••' + v.slice(-4) : '');
const enc = encodeURIComponent;
const clean = (o, keys) =>
  Object.fromEntries(keys.filter((k) => o && o[k] !== undefined && String(o[k]).trim() !== '').map((k) => [k, String(o[k]).trim()]));
const run = (fn) => async (req, res) => {
  try { res.json(await fn(req, res)); } catch (e) { console.error(e.message); res.status(500).json({ status: false, ok: false, error: e.message }); }
};

// Panggil API Sprint Pedia memakai key yang tersimpan
async function provider(pathName, params) {
  const s = await getSettings();
  if (!s.secret_key || !s.api_key) return { status: false, error: 'Key belum diisi.' };
  const r = await fetch('https://sprintpedia.id/api/' + pathName, {
    method: 'POST',
    body: new URLSearchParams({ secret_key: s.secret_key, api_key: s.api_key, ...params }),
  });
  return r.json();
}

// Semua layanan dari database (cache 30 detik)
let cache = { t: 0, rows: null };
async function allServices() {
  if (cache.rows && Date.now() - cache.t < 30000) return cache.rows;
  const rows = [];
  for (let off = 0; ; off += 1000) {
    const part = await rest('services', '?select=id,category,name,price:data->>price,min:data->>min,max:data->>max&order=category.asc,name.asc,id.asc&limit=1000&offset=' + off);
    rows.push(...part);
    if (part.length < 1000) break;
  }
  cache = { t: Date.now(), rows };
  return rows;
}
const sellUnit = (cost, m) => Math.ceil(num(cost) * (1 + m / 100)); // harga jual per 1000

/* ================= API: admin ================= */
// Rapikan nilai: buang spasi/baris baru, tanda kutip pembungkus (sering ikut tersalin ke Vercel), dan samakan bentuk karakter unicode
const norm = (v) => {
  let t = String(v == null ? '' : v).normalize('NFKC').trim();
  if (t.length >= 2 && /^(["'`“”‘’]).*(["'`“”‘’])$/s.test(t)) t = t.slice(1, -1).trim();
  return t;
};
const envOf = (...names) => { for (const n of names) if (process.env[n] != null && norm(process.env[n]) !== '') return norm(process.env[n]); return ''; };

app.post('/api/login', (req, res) => {
  const ADMIN_USER = envOf('ADMIN_USER', 'ADMIN_USERNAME');
  const ADMIN_PASS = envOf('ADMIN_PASS', 'ADMIN_PASSWORD');
  if (!ADMIN_USER || !ADMIN_PASS || !secret())
    return res.status(500).json({ ok: false, error: 'Environment variable belum diatur di Vercel (ADMIN_USER, ADMIN_PASS, SESSION_SECRET) atau belum di-redeploy.' });
  if (limited(req, 'admin')) return res.status(429).json({ ok: false, error: 'Terlalu banyak percobaan. Coba lagi nanti.' });
  // username tidak peka huruf besar/kecil; spasi, kutip, dan karakter unicode dinormalkan di kedua sisi
  const username = norm((req.body || {}).username);
  const password = norm((req.body || {}).password);
  const okUser = safeEqual(username.toLowerCase(), ADMIN_USER.toLowerCase());
  const okPass = safeEqual(password, ADMIN_PASS);
  if (!(okUser && okPass)) {
    // hanya tampil di Vercel > Logs; tidak memuat isi username/password
    console.warn('Login admin gagal: username ' + (okUser ? 'cocok' : 'beda') + ' (env ' + ADMIN_USER.length + ' huruf, input ' + username.length + '), password ' + (okPass ? 'cocok' : 'beda') + ' (env ' + ADMIN_PASS.length + ' huruf, input ' + password.length + ')');
    return res.status(401).json({ ok: false, error: 'Username atau password salah.' });
  }
  const keep = !!(req.body || {}).remember; // "Ingat saya": 30 hari di perangkat ini
  res.setHeader('Set-Cookie', cookie('session', tokenOf({ u: username }, keep ? 30 * 864e5 : 12 * 36e5), keep ? 30 * 86400 : null));
  res.json({ ok: true });
});
app.post('/api/logout', (req, res) => { res.setHeader('Set-Cookie', cookie('session', '', 0)); res.json({ ok: true }); });

app.get('/api/settings', adminOnly, run(async () => {
  const s = await getSettings();
  return { ok: true, secret_key: mask(s.secret_key), api_key: mask(s.api_key), markup_percent: s.markup_percent || '0' };
}));
app.post('/api/settings', adminOnly, run(async (req, res) => {
  const { secret_key, api_key, markup_percent } = req.body || {};
  const data = {};
  if (secret_key) data.secret_key = String(secret_key);
  if (api_key) data.api_key = String(api_key);
  if (markup_percent !== undefined && String(markup_percent).trim() !== '') {
    const m = Number(markup_percent);
    if (!(m >= 0 && m <= 1000)) { res.status(400); return { ok: false, error: 'Markup harus antara 0 dan 1000 persen.' }; }
    data.markup_percent = String(m);
  }
  if (!Object.keys(data).length) { res.status(400); return { ok: false, error: 'Tidak ada yang diubah.' }; }
  await saveSettings(data);
  return { ok: true };
}));

app.get('/api/profile', adminOnly, run(() => provider('profile')));

app.post('/api/services/sync', adminOnly, run(async () => {
  const d = await provider('services');
  if (!d.status || !Array.isArray(d.data)) return d;
  const now = new Date().toISOString();
  const rows = d.data.map((x) => ({ id: String(x.id), category: x.category || '', name: x.name || x.title || '', data: x, updated_at: now }));
  for (let i = 0; i < rows.length; i += 500)
    await rest('services', '', { method: 'POST', prefer: 'resolution=merge-duplicates', body: rows.slice(i, i + 500) });
  cache.rows = null;
  return { status: true, count: rows.length };
}));
// Riwayat pesanan semua pembeli (admin): bisa dicari per username dan difilter per status
app.get('/api/admin/orders', adminOnly, run(async (req) => {
  const q = String(req.query.q || '').toLowerCase().replace(/[^a-z0-9_]/g, '');
  const want = String(req.query.status || 'all');
  let filter = '';
  if (q) {
    const us = await rest('users', '?select=id&username=ilike.*' + q + '*&limit=50');
    if (!us.length) return { status: true, data: [] };
    filter = '&user_id=in.(' + us.map((u) => enc(u.id)).join(',') + ')';
  }
  const tail = '&order=id.desc&limit=300' + filter;
  let rows;
  try { rows = await rest('orders', '?select=id,user_id,service_name,target,quantity,price,status,created_at' + tail); }
  catch (e) { rows = await rest('orders', '?select=id,user_id,service_name,target,quantity,price,status' + tail); }
  rows = rows.map((o) => ({ ...o, grp: stGroup(o.status) })).filter((o) => want === 'all' || o.grp === want).slice(0, 100);
  const ids = [...new Set(rows.map((o) => o.user_id).filter(Boolean))];
  const names = {};
  if (ids.length) (await rest('users', '?select=id,username&id=in.(' + ids.map(enc).join(',') + ')')).forEach((u) => { names[u.id] = u.username; });
  return { status: true, data: rows.map((o) => ({ id: o.id, username: o.user_id ? (names[o.user_id] || '(akun dihapus)') : 'Admin', service_name: o.service_name, target: o.target, quantity: o.quantity, price: o.price, grp: o.grp, created_at: o.created_at || null })) };
}));
app.get('/api/admin/users', adminOnly, run(async (req) => {
  const q = String(req.query.q || '').toLowerCase().replace(/[^a-z0-9_]/g, '');
  const rows = await rest('users', '?select=username,balance,created_at&order=created_at.desc&limit=100' + (q ? '&username=ilike.*' + q + '*' : ''));
  return { status: true, data: rows };
}));
app.post('/api/admin/topup', adminOnly, run(async (req) => {
  const username = String((req.body || {}).username || '').trim().toLowerCase();
  const amount = parseInt((req.body || {}).amount, 10);
  if (!username || !amount) return { status: false, error: 'Isi username dan jumlah saldo.' };
  const [u] = await rest('users', '?select=id&username=eq.' + enc(username));
  if (!u) return { status: false, error: 'Pembeli tidak ditemukan.' };
  const nb = await rpc('adjust_balance', { p_user: u.id, p_delta: amount });
  if (nb === null) return { status: false, error: 'Saldo tidak boleh menjadi minus.' };
  return { status: true, balance: nb };
}));

app.post('/api/admin/users/delete', adminOnly, run(async (req) => {
  const username = String((req.body || {}).username || '').trim().toLowerCase();
  if (!username) return { status: false, error: 'Username wajib diisi.' };
  const [u] = await rest('users', '?select=id&username=eq.' + enc(username));
  if (!u) return { status: false, error: 'Pembeli tidak ditemukan.' };
  // riwayat pesanan & deposit dihapus dulu supaya tidak terhalang relasi (foreign key) di Supabase
  await rest('orders', '?user_id=eq.' + enc(u.id), { method: 'DELETE' });
  await rest('deposits', '?user_id=eq.' + enc(u.id), { method: 'DELETE' });
  await rest('users', '?id=eq.' + enc(u.id), { method: 'DELETE' });
  return { status: true };
}));

/* ================= API: publik & pembeli ================= */
// Layanan rekomendasi: daftar id layanan (urut) disimpan di tabel settings, kunci "recommended"
const recIds = (s) => { try { const a = JSON.parse(s.recommended || '[]'); return Array.isArray(a) ? a.map(String) : []; } catch (e) { return []; } };
app.get('/api/catalog', buyerOnly, run(async () => {
  const st = await getSettings();
  const m = num(st.markup_percent);
  const rows = await allServices();
  return { status: true, rec: recIds(st), data: rows.map((x) => ({ id: x.id, category: x.category, name: x.name, price: sellUnit(x.price, m), min: num(x.min), max: num(x.max) })) };
}));

app.post('/api/buyer/register', run(async (req, res) => {
  if (limited(req, 'reg')) return { status: false, error: 'Terlalu banyak percobaan. Coba lagi nanti.' };
  const username = String((req.body || {}).username || '').trim().toLowerCase();
  const password = String((req.body || {}).password || '');
  if (!/^[a-z0-9_]{3,20}$/.test(username)) return { status: false, error: 'Username 3-20 karakter: huruf kecil, angka, atau garis bawah.' };
  if (password.length < 6 || password.length > 72) return { status: false, error: 'Password minimal 6 karakter.' };
  try {
    await rest('users', '', { method: 'POST', body: { username, password_hash: hashPw(password) } });
    await notify('user', '👤 Pembeli baru', username + ' baru saja mendaftar.', '/admin#us');
    return { status: true }; // tidak otomatis login: pembeli diminta masuk sendiri
  } catch (e) {
    if (e.status === 409) return { status: false, error: 'Username sudah dipakai.' };
    throw e;
  }
}));
app.post('/api/buyer/login', run(async (req, res) => {
  if (limited(req, 'blogin')) return { status: false, error: 'Terlalu banyak percobaan. Coba lagi nanti.' };
  const username = String((req.body || {}).username || '').trim().toLowerCase();
  const password = String((req.body || {}).password || '');
  const [u] = username ? await rest('users', '?select=id,password_hash&username=eq.' + enc(username)) : [];
  if (!u || !checkPw(password, u.password_hash)) return { status: false, error: 'Username atau password salah.' };
  const keep = !!(req.body || {}).remember; // "Ingat saya": 30 hari di perangkat ini
  res.setHeader('Set-Cookie', cookie('bs', tokenOf({ id: u.id }, keep ? 30 * 864e5 : 864e5), keep ? 30 * 86400 : null));
  return { status: true };
}));
app.post('/api/buyer/logout', (req, res) => { res.setHeader('Set-Cookie', cookie('bs', '', 0)); res.json({ status: true }); });
app.get('/api/buyer/me', buyerOnly, run(async (req) => {
  const [u] = await rest('users', '?select=username,balance&id=eq.' + enc(req.buyerId));
  return u ? { status: true, username: u.username, balance: num(u.balance) } : { status: false, error: 'Akun tidak ditemukan.' };
}));
app.get('/api/buyer/orders', buyerOnly, run(async (req) => {
  await maybeQueue();
  const tail = '&order=id.desc&limit=20&user_id=eq.' + enc(req.buyerId);
  let data;
  try { data = await rest('orders', '?select=id,service_name,target,quantity,price,status' + tail); }
  catch (e) { data = await rest('orders', '?select=id,target,price' + tail); } // cadangan jika kolom belum ada
  return { status: true, data };
}));

// Notifikasi pesanan baru ke HP admin (error apa pun diabaikan, tidak mengganggu pesanan)
async function notifyOrder(uid, serviceName, qty, total, queued) {
  try {
    const [u] = await rest('users', '?select=username&id=eq.' + enc(uid));
    await notify('order', '🛒 Pesanan baru' + (queued ? ' (antre)' : ''),
      ((u && u.username) || 'Pembeli') + ' memesan ' + serviceName + ' x' + qty + ' (Rp ' + Math.round(total).toLocaleString('id-ID') + ')', '/admin#ord');
  } catch (e) { console.error('notifyOrder:', e.message); }
}

// Saldo dipotong dulu (atomik); jika provider menolak, saldo dikembalikan.
app.post('/api/buyer/order', buyerOnly, run(async (req) => {
  const { service, target, quantity } = req.body || {};
  const qty = parseInt(quantity, 10);
  const tgt = String(target || '').trim();
  if (!service || !tgt || !(qty > 0)) return { status: false, error: 'Lengkapi link target dan jumlah.' };
  const [sv] = await rest('services', '?select=id,name,price:data->>price,min:data->>min,max:data->>max&id=eq.' + enc(String(service)));
  if (!sv) return { status: false, error: 'Layanan tidak ditemukan.' };
  const min = num(sv.min), max = num(sv.max);
  if (qty < min || (max && qty > max)) return { status: false, error: 'Jumlah harus antara ' + min + ' dan ' + max + '.' };
  const m = num((await getSettings()).markup_percent);
  const total = Math.ceil((qty * sellUnit(sv.price, m)) / 1000);
  if (!(total > 0)) return { status: false, error: 'Harga layanan belum valid. Hubungi admin.' };
  const uid = req.buyerId;
  const left = await rpc('adjust_balance', { p_user: uid, p_delta: -total });
  if (left === null) return { status: false, error: 'Saldo tidak cukup untuk pesanan ini.' };
  const refund = () => rpc('adjust_balance', { p_user: uid, p_delta: total });
  const cost = (num(sv.price) * qty) / 1000; // modal di Sprint Pedia
  const bal = await providerBalance();
  let queue = bal !== null && bal < cost; // saldo Sprint Pedia kurang: pesanan ditahan (Pending)
  let d = null;
  if (!queue) {
    try { d = await provider('order', { service: sv.id, target: tgt, quantity: String(qty) }); }
    catch (e) { await refund(); throw e; }
    if (!d || !d.status) {
      if (noBalance(d)) queue = true;
      else {
        console.error('Provider menolak pesanan:', JSON.stringify(d));
        await refund();
        return { status: false, error: 'Pesanan gagal diproses. Saldo Anda dikembalikan. Coba lagi nanti atau hubungi admin.' };
      }
    }
  }
  if (queue) {
    try {
      await rest('orders', '', { method: 'POST', body: { user_id: uid, service_id: sv.id, service_name: sv.name, target: tgt, quantity: qty, price: total, provider_order_id: null, status: 'queued' } });
    } catch (e) {
      console.error('Gagal mengantrekan pesanan:', e.message);
      await refund();
      return { status: false, error: 'Pesanan gagal diproses. Saldo Anda dikembalikan. Coba lagi nanti atau hubungi admin.' };
    }
    await notifyOrder(uid, sv.name, qty, total, true);
    return { status: true, queued: true, total, balance: left };
  }
  const pid = d.data && (d.data.id || d.data.order_id);
  try {
    await rest('orders', '', { method: 'POST', body: { user_id: uid, service_id: sv.id, service_name: sv.name, target: tgt, quantity: qty, price: total, provider_order_id: pid ? String(pid) : null } });
  } catch (e) { console.error('Gagal mencatat pesanan:', e.message); }
  await notifyOrder(uid, sv.name, qty, total, false);
  return { status: true, total, balance: left };
}));

app.post('/api/buyer/status', buyerOnly, run(async (req) => {
  const id = parseInt((req.body || {}).id, 10);
  if (!id) return { status: false, error: 'Pesanan tidak valid.' };
  const [o] = await rest('orders', '?select=id,status,provider_order_id&id=eq.' + id + '&user_id=eq.' + enc(req.buyerId));
  if (!o) return { status: false, error: 'Pesanan tidak ditemukan.' };
  if (!o.provider_order_id && ['queued', 'sending'].includes(o.status)) return { status: true, state: 'pending' };
  if (!o.provider_order_id) return { status: false, error: 'Status belum bisa dicek untuk pesanan ini.' };
  const d = await provider('status', { id: o.provider_order_id });
  const st = d && d.status && d.data && typeof d.data === 'object' ? d.data.status : null;
  if (!st) return { status: false, error: 'Status belum tersedia.' };
  await rest('orders', '?id=eq.' + id, { method: 'PATCH', body: { status: String(st) } });
  return { status: true, state: String(st) };
}));

// Contoh data mentah layanan dari Sprint Pedia (untuk mengecek field apa saja yang dikirim API)
app.get('/api/admin/service-sample', adminOnly, run(async () => ({
  status: true,
  data: await rest('services', '?select=id,category,name,data&limit=3'),
})));

/* ---- Admin: layanan rekomendasi ---- */
const svcRow = (r, m) => ({ id: String(r.id), category: r.category, name: r.name, price: sellUnit(r.price, m) });
app.get('/api/admin/recommended', adminOnly, run(async () => {
  const s = await getSettings();
  const ids = recIds(s);
  if (!ids.length) return { status: true, data: [] };
  const m = num(s.markup_percent);
  const rows = await rest('services', '?select=id,category,name,price:data->>price&id=in.(' + ids.map(enc).join(',') + ')');
  const by = Object.fromEntries(rows.map((r) => [String(r.id), r]));
  return { status: true, data: ids.filter((i) => by[i]).map((i) => svcRow(by[i], m)) };
}));
app.post('/api/admin/recommended', adminOnly, run(async (req, res) => {
  const raw = (req.body || {}).ids;
  if (!Array.isArray(raw)) { res.status(400); return { status: false, error: 'Data tidak valid.' }; }
  const ids = [...new Set(raw.map((x) => String(x).trim()).filter((x) => x && x.length <= 40))];
  if (ids.length > 10) { res.status(400); return { status: false, error: 'Maksimal 10 layanan rekomendasi.' }; }
  await saveSettings({ recommended: JSON.stringify(ids) });
  return { status: true };
}));
app.get('/api/admin/services/search', adminOnly, run(async (req) => {
  const q = String(req.query.q || '').replace(/[^\p{L}\p{N} ]/gu, ' ').trim().replace(/\s+/g, '*');
  if (q.length < 2) return { status: true, data: [] };
  const m = num((await getSettings()).markup_percent);
  const rows = await rest('services', '?select=id,category,name,price:data->>price&or=(name.ilike.*' + enc(q) + '*,category.ilike.*' + enc(q) + '*)&order=name.asc&limit=30');
  return { status: true, data: rows.map((r) => svcRow(r, m)) };
}));

// Pendapatan penjualan = jumlah harga semua pesanan pembeli (hari ini & bulan ini dihitung menurut WIB)
app.get('/api/admin/revenue', adminOnly, run(async () => {
  // "Pendapatan" = UNTUNG: harga jual ke pembeli dikurangi modal di Sprint Pedia (hanya pesanan sukses).
  // Modal dihitung dari harga layanan saat ini x jumlah; jika data layanan/jumlah tidak ada, diperkirakan dari markup.
  const mk = num((await getSettings()).markup_percent);
  const cost = {};
  try { (await allServices()).forEach((x) => { cost[String(x.id)] = num(x.price); }); } catch (e) { /* pakai perkiraan markup */ }
  const profitOf = (o) => {
    const p = num(o.price), per = cost[String(o.service_id)], q = num(o.quantity);
    const modal = per !== undefined && q > 0 ? (per * q) / 1000 : p / (1 + mk / 100);
    return p - modal;
  };
  const sels = ['price,status,created_at,service_id,quantity', 'price,status,created_at', 'price,status'];
  let si = 0, hasDate = true, total = 0, today = 0, month = 0, count = 0, daily = {};
  const wib = (t) => new Date(new Date(t).getTime() + 7 * 36e5).toISOString();
  const nowW = wib(Date.now());
  const dayKey = nowW.slice(0, 10), monKey = nowW.slice(0, 7);
  for (let off = 0; off < 100000; off += 1000) {
    let part;
    try { part = await rest('orders', '?select=' + sels[si] + '&order=id.asc&limit=1000&offset=' + off); }
    catch (e) {
      if (off === 0 && si < sels.length - 1) { si++; hasDate = si < 2; off -= 1000; continue; }
      throw e;
    }
    for (const o of part) {
      if (stGroup(o.status) !== 'success') continue; // hanya pesanan yang sukses
      const p = profitOf(o);
      total += p; count++;
      if (hasDate && o.created_at) {
        const w = wib(o.created_at);
        const dk = w.slice(0, 10); (daily[dk] = daily[dk] || [0, 0]); daily[dk][0] += p; daily[dk][1]++;
        if (w.slice(0, 7) === monKey) { month += p; if (w.slice(0, 10) === dayKey) today += p; }
      }
    }
    if (part.length < 1000) break;
  }
  Object.keys(daily).forEach((k) => { daily[k][0] = Math.round(daily[k][0]); });
  return { status: true, total: Math.round(total), today: Math.round(today), month: Math.round(month), orders: count, has_date: hasDate, daily, now: dayKey };
}));

// Statistik pesanan per layanan (dihitung dari pesanan di web ini)
const stGroup = (s) => {
  const t = String(s || '').toLowerCase();
  if (!t || /pend|wait/.test(t)) return 'pending';
  if (/part/.test(t)) return 'partial';
  if (/succ|complet|sukses|selesai|done/.test(t)) return 'success';
  if (/err|fail|gagal|cancel|refund|reject/.test(t)) return 'error';
  if (/proc|progress|run/.test(t)) return 'processing';
  return 'pending';
};
// Perbarui status pesanan yang belum selesai langsung dari Sprint Pedia
async function syncOrders(limit) {
  let rows;
  try { rows = await rest('orders', '?select=id,status,provider_order_id&provider_order_id=not.is.null&order=id.desc&limit=300'); }
  catch (e) { return { checked: 0, updated: 0 }; }
  const todo = rows.filter((o) => ['pending', 'processing'].includes(stGroup(o.status))).slice(0, limit);
  let updated = 0;
  await Promise.all(todo.map(async (o) => {
    try {
      const d = await provider('status', { id: o.provider_order_id });
      const st = d && d.status && d.data && typeof d.data === 'object' ? d.data.status : null;
      if (st && String(st) !== String(o.status)) { await rest('orders', '?id=eq.' + o.id, { method: 'PATCH', body: { status: String(st) } }); updated++; }
    } catch (e) { /* lewati, dicoba lagi nanti */ }
  }));
  return { checked: todo.length, updated };
}
let lastSync = 0;
app.post('/api/admin/orders/sync', adminOnly, run(async () => {
  const q = await processQueue(20);
  const sy = await syncOrders(30);
  return { status: true, ...sy, processed: q.processed, queued: q.waiting };
}));

/* ---- Antrean pesanan saat saldo Sprint Pedia kurang ---- */
const noBalance = (d) => /saldo|balance|insufficient|funds/i.test(JSON.stringify(d || ''));
async function providerBalance() {
  try {
    const d = await provider('profile');
    const b = d && d.status && d.data ? Number(d.data.balance) : NaN;
    return Number.isFinite(b) ? b : null;
  } catch (e) { return null; }
}
let queueBusy = false, lastQueue = 0;
async function processQueue(limit) {
  const out = { processed: 0, waiting: 0 };
  if (queueBusy) return out;
  queueBusy = true;
  try {
    let rows;
    try { rows = await rest('orders', '?select=id,user_id,service_id,target,quantity,price&status=eq.queued&order=id.asc&limit=' + limit); }
    catch (e) { return out; }
    if (rows.length) {
      const ids = [...new Set(rows.map((o) => String(o.service_id)))];
      const svs = await rest('services', '?select=id,price:data->>price&id=in.(' + ids.map(enc).join(',') + ')');
      const priceOf = Object.fromEntries(svs.map((x) => [String(x.id), num(x.price)]));
      let bal = await providerBalance();
      const back = (id) => rest('orders', '?id=eq.' + id, { method: 'PATCH', body: { status: 'queued' } }).catch(() => {});
      for (const o of rows) {
        const cost = ((priceOf[String(o.service_id)] || 0) * num(o.quantity)) / 1000;
        if (bal !== null && bal < cost) break; // saldo Sprint Pedia belum cukup: tetap antre
        // klaim dulu (hanya jika masih antre) supaya tidak terkirim dua kali
        const [c] = (await rest('orders', '?select=id&id=eq.' + o.id + '&status=eq.queued', { method: 'PATCH', prefer: 'return=representation', body: { status: 'sending' } })) || [];
        if (!c) continue;
        let d;
        try { d = await provider('order', { service: String(o.service_id), target: o.target, quantity: String(o.quantity) }); }
        catch (e) { await back(o.id); break; }
        if (d && d.status) {
          const pid = d.data && (d.data.id || d.data.order_id);
          await rest('orders', '?id=eq.' + o.id, { method: 'PATCH', body: { status: 'pending', provider_order_id: pid ? String(pid) : null } });
          out.processed++;
          if (bal !== null) bal -= cost;
        } else if (noBalance(d) || (d && d.error === 'Key belum diisi.')) { await back(o.id); break; }
        else {
          console.error('Pesanan antre ditolak:', JSON.stringify(d));
          await rpc('adjust_balance', { p_user: o.user_id, p_delta: num(o.price) }).catch(() => {}); // saldo pembeli dikembalikan
          await rest('orders', '?id=eq.' + o.id, { method: 'PATCH', body: { status: 'error' } }).catch(() => {});
        }
      }
    }
    try { out.waiting = (await rest('orders', '?select=id&status=eq.queued&limit=1000')).length; } catch (e) { /* abaikan */ }
    return out;
  } finally { queueBusy = false; }
}
const maybeQueue = async () => { // dipicu aktivitas pembeli, maksimal 1x per menit
  if (Date.now() - lastQueue < 60000) return;
  lastQueue = Date.now();
  await processQueue(5).catch(() => {});
};

async function tally(filter) {
  const counts = { pending: 0, processing: 0, success: 0, partial: 0, error: 0 };
  let total = 0, qsum = 0, qn = 0;
  const last = [];
  const tries = ['id,status,quantity,created_at', 'id,status,quantity', 'id,status', 'id']; // jika ada kolom yang belum ada, pakai yang tersedia
  let ti = 0;
  for (let off = 0; off < 10000; off += 1000) {
    let part;
    try { part = await rest('orders', '?select=' + tries[ti] + '&order=id.desc&limit=1000&offset=' + off + filter); }
    catch (e) {
      if (off === 0 && ti < tries.length - 1) { ti++; off -= 1000; continue; }
      throw e;
    }
    part.forEach((o) => {
      const g = stGroup(o.status);
      counts[g]++; total++;
      const q = num(o.quantity);
      if (q > 0) { qsum += q; qn++; }
      if (last.length < 3) last.push({ qty: q || null, st: g, at: o.created_at || null });
    });
    if (part.length < 1000) break;
  }
  return { counts, total, avg: qn ? Math.round(qsum / qn) : 0, last };
}
app.get('/api/buyer/stats/:id', buyerOnly, run(async (req) => {
  const id = String(req.params.id || '');
  await maybeQueue();
  if (Date.now() - lastSync > 60000) { lastSync = Date.now(); await syncOrders(8).catch(() => {}); } // maksimal 1x per menit
  try {
    const r = await tally('&service_id=eq.' + enc(id));
    return { status: true, total: r.total, counts: r.counts, avg: r.avg, last: r.last };
  } catch (e) { console.error('stats:', e.message); return { status: false, error: 'Statistik gagal dimuat: ' + e.message.slice(0, 120) }; }
}));

// Refill (garansi) lewat Sprint Pedia
app.post('/api/buyer/refill', buyerOnly, run(async (req) => {
  if (limited(req, 'refill')) return { status: false, error: 'Terlalu banyak percobaan. Coba lagi nanti.' };
  const id = parseInt((req.body || {}).id, 10);
  if (!id) return { status: false, error: 'Pesanan tidak valid.' };
  const [o] = await rest('orders', '?select=id,status,provider_order_id&id=eq.' + id + '&user_id=eq.' + enc(req.buyerId));
  if (!o) return { status: false, error: 'Pesanan tidak ditemukan.' };
  if (!o.provider_order_id) return { status: false, error: 'Pesanan ini belum diproses, belum bisa refill.' };
  if (!['success', 'partial'].includes(stGroup(o.status))) return { status: false, error: 'Refill hanya bisa untuk pesanan yang sudah selesai (Sukses).' };
  const d = await provider('refill', { id: o.provider_order_id });
  if (d && d.status) return { status: true, message: 'Permintaan refill sudah dikirim ke provider.' };
  console.error('Refill ditolak:', JSON.stringify(d));
  const why = d && (typeof d.data === 'string' ? d.data : (d.error || d.message));
  return { status: false, error: why ? 'Refill ditolak: ' + String(why).slice(0, 160) : 'Refill ditolak. Layanan ini mungkin tidak mendukung refill atau belum waktunya.' };
}));

/* ================= Nomor WhatsApp bantuan ================= */
// Disimpan di tabel settings (kunci "wa_number"), tampil di halaman pembeli sebagai tombol chat
const normWa = (v) => {
  let d = String(v == null ? '' : v).replace(/\D/g, '');
  if (!d) return '';
  if (d.startsWith('0')) d = '62' + d.slice(1);
  else if (d.startsWith('8')) d = '62' + d;
  return d.length >= 9 && d.length <= 15 ? d : null;
};
app.get('/api/wa', run(async () => ({ status: true, wa: (await getSettings()).wa_number || '' })));
app.get('/api/admin/wa', adminOnly, run(async () => ({ status: true, wa: (await getSettings()).wa_number || '' })));
app.post('/api/admin/wa', adminOnly, run(async (req, res) => {
  const v = normWa((req.body || {}).wa);
  if (v === null) { res.status(400); return { status: false, error: 'Nomor WhatsApp tidak valid. Contoh: 08123456789.' }; }
  await saveSettings({ wa_number: v });
  return { status: true, wa: v };
}));

const { notify } = require('./push')(app, { rest, adminOnly, run, enc });
require('./deposit')(app, { rest, rpc, getSettings, saveSettings, adminOnly, buyerOnly, run, enc, notify });

/* ================= Halaman ================= */
const page = (title, body, script, cls) => `<!doctype html><html lang="id"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1"><title>${title}</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/style.css"></head><body class="${cls || ''}"><main class="${cls || ''}">${body}</main><script>${script}</script></body></html>`;

const LOGIN = page('Alzz Pedia', `
<div class="brand"><span class="logo">A</span><span>Alzz Pedia</span></div>
<h1>Masuk</h1><p class="muted">Masuk untuk melihat layanan dan memesan.</p>
<section class="box" id="auth"><form id="f" autocomplete="on">
<label for="u">Username</label><input id="u" name="username" autocomplete="username" autocapitalize="none" autocorrect="off" spellcheck="false" required>
<label for="p">Password</label><input id="p" name="password" type="password" autocomplete="current-password" required>
<label class="remember"><input type="checkbox" id="rem"><span>Ingat saya</span></label>
<div class="err" id="e" role="alert"></div>
<button type="submit" style="width:100%">Masuk</button></form>
<p class="muted" style="margin-top:18px;text-align:center">Belum punya akun? <a href="/#daftar" style="color:var(--accent);font-weight:700">Daftar</a></p></section>`, `
document.getElementById('f').onsubmit=async ev=>{ev.preventDefault();const e=document.getElementById('e');e.textContent='';
try{const r=await fetch('/api/login',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({username:document.getElementById('u').value.trim(),password:document.getElementById('p').value,remember:document.getElementById('rem').checked})});
const d=await r.json();if(d.ok)location.href='/admin';else e.textContent=d.error||'Gagal masuk.';}catch{e.textContent='Tidak bisa terhubung ke server.'}};`, 'auth');

const ADMIN_PAGE = require('./page-admin');
const BUYER_PAGE = require('./page-buyer');
const ADMIN = page(ADMIN_PAGE.title, ADMIN_PAGE.body, ADMIN_PAGE.script, 'wide adm');
const BUYER = page(BUYER_PAGE.title, BUYER_PAGE.body, BUYER_PAGE.script);

/* ================= Rute halaman ================= */
app.get('/', (req, res) => res.type('html').send(BUYER));
app.get('/login', (req, res) => (readAdmin(req) ? res.redirect('/admin') : res.type('html').send(LOGIN)));
app.get('/admin', (req, res) => (readAdmin(req) ? res.type('html').send(ADMIN) : res.redirect('/login')));

module.exports = app;
